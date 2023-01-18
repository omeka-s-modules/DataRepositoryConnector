<?php
namespace DataRepositoryConnector\DataRepoSelector;

use Laminas\Http\Client as HttpClient;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings as Settings;
use Omeka\Job\Exception;
use Laminas\Stdlib\Parameters;
use DateTime;

/**
 * Import records from Zenodo API
 */
class Zenodo implements DataRepoSelectorInterface
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
        return 'Zenodo'; // @translate
    }

    public function prepareFieldIdMap($dataMetadataFormat)
    {
        $this->dataMetadataFormat = $dataMetadataFormat;
        $this->fieldIdMap = [];

        // Depending on export format, prepare metadata fields
        switch($this->dataMetadataFormat) {
            case 'oai_dc':
                $this->prefix = 'dc';
                $this->namespace = 'http://purl.org/dc/elements/1.1/';
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
        // Iterate through pages
        $this->page = $this->page ? ++$this->page : 1; 
        
        // Reset parameters if paginating
        $this->client->resetParameters();
        $apiLink = $link . '/api/records/';
        $this->client->setUri($apiLink);
        $this->client->setParameterGet(['q' => 'communities:' . $localId,
                                        'size' => $limit,
                                        'page' => $this->page,
                                       ]);
        $collectionResponse = $this->client->send();
        if (!$collectionResponse->isSuccess()) {
            throw new Exception\RuntimeException(sprintf(
                'Requested "%s" got "%s".', $link, $collectionResponse->renderStatusLine()
            ));
        }
        
        $collection = json_decode($collectionResponse->getBody(), true);
        $responseArray['item_count'] = $collection['hits']['total'];
        $responseArray['collection_response'] = $collection['hits']['hits'];

        return $responseArray;
    }
    
    public function buildResource($siteUri, $dataMetadataFormat, $ingestFiles, $itemData)
    {
        $itemJson = [];
        $this->siteUri = $siteUri;

        // If no id value, do not import record
        if (!isset($itemData['id'])) {
            return;
        }

        $export = $this->siteUri . '/api/records/' . $itemData['id'];
        $this->client->setUri($export);

        // Change Accept header depending on metadata export format
        switch($this->dataMetadataFormat) {
            case 'oai_dc':
                $this->client->getRequest()->getHeaders()->addHeaderLine('Accept: application/x-dc+xml');
                break;
        }

        $response = $this->client->send();
        if (!$response->isSuccess()) {
            throw new Exception\RuntimeException(sprintf(
                'Requested "%s" got "%s".', $export, $response->renderStatusLine()
            ));
        }
        $itemJson = $this->processItemMetadata($response, $itemJson);
        
        if ($ingestFiles) {
            $itemJson = $this->processItemFiles($itemData, $itemJson);
        }
        $itemJson['dataLastModified'] = new DateTime($itemData['updated']);
        $itemJson['dataUri'] = $itemData['links']['doi'];
        return $itemJson;
    }

    public function processItemMetadata($response, $itemJson)
    {
        // Decode XML or JSON depending on response format
        switch($this->dataMetadataFormat) {
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
        foreach ($itemData['files'] as $file) {
            $fileURL = $file['links']['self'];
            $itemJson['o:media'][] = [
                'o:ingester' => 'url',
                'o:source' => $fileURL,
                'ingest_url' => $fileURL,
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        '@value' => $file['key'],
                        'property_id' => $this->fieldIdMap['title'],
                    ],
                ],
            ];
        }
        return $itemJson;
    }
}
