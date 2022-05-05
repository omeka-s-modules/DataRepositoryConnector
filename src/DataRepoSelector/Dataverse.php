<?php
namespace DataRepositoryConnector\DataRepoSelector;

use Laminas\Http\Client as HttpClient;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings as Settings;
use Laminas\Stdlib\Parameters;
use DateTime;

/**
 * Import records from Dataverse API
 */
class Dataverse implements DataRepoSelectorInterface
{
    /**
     * @var Settings
     */
    protected $settings;
    
    /**
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * @var HttpClient
     */
    protected $client;

    public function __construct(ApiManager $apiManager, HttpClient $client) {
        $this->apiManager = $apiManager;
        $this->client = $client;
    }
    
    public function getLabel()
    {
        return 'Dataverse'; // @translate
    }

    public function prepareFieldIdMap($dataMetadataFormat)
    {
        $this->dataMetadataFormat = $dataMetadataFormat;
        $this->fieldIdMap = [];

        // Depending on export format, prepare metadata fields
        switch($this->dataMetadataFormat) {
            case 'dcterms':
                $this->prefix = 'dcterms';
                $this->namespace = 'http://purl.org/dc/terms/';
                break;
            case 'oai_dc':
                $this->prefix = 'dc';
                $this->namespace = 'http://purl.org/dc/elements/1.1/';
                break;
            case 'schema.org':
                $this->namespace = 'http://schema.org/';
                break;
        }

        $properties = $this->apiManager->search('properties', [
            'vocabulary_namespace_uri' => $this->namespace,
        ])->getContent();
        foreach ($properties as $property) {
            $field = $property->localName();
            $this->fieldIdMap[$field] = $property->id();
        }
    }

    public function getResponse($link, $limit, $localId = null, $offset = 0)
    {
        $responseArray = [];

        $apiLink = $link . '/api/search';
        $this->client->setUri($apiLink);
        // If no dataverse id found, import from parent dataverse
        $subcollection = $localId ?: null;
        $this->client->setParameterGet(['q' => '*',
                                        'type' => 'dataset',
                                        'subtree' => $subcollection,
                                        'per_page' => $limit,
                                        'start' => $offset,
                                       ]);
        $response = $this->client->send();
        if (!$response->isSuccess()) {
            throw new Exception\RuntimeException(sprintf(
                'Requested "%s" got "%s".', $link, $response->renderStatusLine()
            ));
        }

        $collection = json_decode($response->getBody(), true);
        $responseArray['item_count'] = $collection['data']['total_count'];
        $responseArray['collection_response'] = $collection['data']['items'];

        return $responseArray;
    }
    
    public function buildResource($siteUri, $dataMetadataFormat, $ingestFiles, $itemData)
    {
        $itemJson = [];
        $this->siteUri = $siteUri;

        $export = $this->siteUri . '/api/datasets/export';
        $this->client->setUri($export);
        $this->client->setParameterGet(['exporter' => $dataMetadataFormat,
                                        'persistentId' => $itemData['global_id'],
                                       ]);
        $response = $this->client->send();
        if ($response) {
            $itemJson = $this->processItemMetadata($response, $itemJson);
        }
        
        if ($ingestFiles) {
            $itemJson = $this->processItemFiles($itemData, $itemJson);
        }
        $itemJson['dataLastModified'] = new DateTime($itemData['updatedAt']);
        $itemJson['dataUri'] = $itemData['url'];
        return $itemJson;
    }

    public function processItemMetadata($response, $itemJson)
    {
        // Decode XML or JSON depending on response format
        switch($this->dataMetadataFormat) {
            case 'ddi':
            case 'oai_ddi':
            case 'dcterms':
            case 'oai_dc':
                $itemMetadata = simplexml_load_string($response->getBody());
                $itemMetadataArray = $itemMetadata->children($this->prefix, true);
                break;
            case 'schema.org':
                $itemMetadataArray = json_decode($response->getBody(), true);
                break;
        }

        foreach ($itemMetadataArray as $key=>$value) {
            if (isset($this->fieldIdMap[$key])) {
                $fieldArray = [];
                $fieldArray['name'] = $key;
                $fieldArray['field_id'] = $this->fieldIdMap[$key];
            }
            if (!$fieldArray) {
                continue;
            }

            $valueArray = [];
            if (isset($itemMetadataArray->language)) {
                $valueArray['@language'] = (string)$itemMetadataArray->language;
            }
            // Handle nested arrays in metadata fields
            if (is_array($value)) {
                $iterate = function (&$value) use (&$iterate, &$valueArray, &$itemJson, $fieldArray) {
                    foreach ($value as $key => $value) {
                        if (is_array($value)) {
                            $iterate($value);
                        } else if (is_numeric($key) || $key == 'name' || $key == 'text') {
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                            $valueArray['property_id'] = $fieldArray['field_id'];
                            $itemJson[$fieldArray['name']][] = $valueArray;
                        } else if ($key == 'url') {
                            $valueArray['o:label'] = $key;
                            $valueArray['type'] = 'uri';
                            $valueArray['@id'] = (string)$value;
                            $valueArray['property_id'] = $fieldArray['field_id'];
                            $itemJson[$fieldArray['name']][] = $valueArray;
                        }
                    }
                };
                $iterate($value);
            } else {
                $valueArray['@value'] = (string)$value;
                $valueArray['type'] = 'literal';
                $valueArray['property_id'] = $fieldArray['field_id'];
                $itemJson[$fieldArray['name']][] = $valueArray;
            }
        }
        return $itemJson;
    }

    public function processItemFiles($itemData, $itemJson)
    {
        $itemID = $itemData['global_id'];
        $itemVersion = $itemData['majorVersion'];
        $fileMetadataURL = $this->siteUri . '/api/datasets/:persistentId/versions/' . $itemVersion . '/files?persistentId=' . $itemID;
        $this->client->setUri($fileMetadataURL);
        $response = $this->client->send();
        if ($response) {
            $fileMetadata = json_decode($response->getBody(), true);
            foreach ($fileMetadata['data'] as $file) {
                if ($file['restricted'] === false) {
                    $fileURL = $this->siteUri . '/api/access/datafile/' . $file['dataFile']['id'];
                    $itemJson['o:media'][] = [
                        'o:ingester' => 'url',
                        'o:source' => $fileURL,
                        'ingest_url' => $fileURL,
                        'dcterms:title' => [
                            [
                                'type' => 'literal',
                                '@value' => $file['dataFile']['filename'],
                                'property_id' => $this->fieldIdMap['title'],
                            ],
                        ],
                    ];
                }
            }
        }
        return $itemJson;
    }
}
