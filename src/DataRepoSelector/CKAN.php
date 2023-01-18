<?php
namespace DataRepositoryConnector\DataRepoSelector;

use Laminas\Http\Client as HttpClient;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings as Settings;
use Omeka\Job\Exception;
use Laminas\Stdlib\Parameters;
use DateTime;

/**
 * Import records from CKAN API
 */
class CKAN implements DataRepoSelectorInterface
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
        return 'CKAN'; // @translate
    }

    public function prepareFieldIdMap($dataMetadataFormat)
    {
        $this->dataMetadataFormat = $dataMetadataFormat;
        $this->fieldIdMap = [];

        // Depending on export format, prepare metadata fields
        switch($this->dataMetadataFormat) {
            case 'dcterms':
                $this->prefix = 'dct';
                $this->namespace = 'http://purl.org/dc/terms/';
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

        $apiLink = $link . '/api/3/action/package_search';
        $this->client->setUri($apiLink);
        // If no organization id found, import entire CKAN instance
        $subcollection = $localId ?: null;
        $this->client->setParameterGet(['q' => 'organization:' . $subcollection,
                                        'rows' => $limit,
                                        'start' => $offset,
                                       ]);
        $collectionResponse = $this->client->send();
        if (!$collectionResponse->isSuccess()) {
            throw new Exception\RuntimeException(sprintf(
                'Requested "%s" got "%s".', $link, $collectionResponse->renderStatusLine()
            ));
        }
        
        $collection = json_decode($collectionResponse->getBody(), true);
        $responseArray['item_count'] = $collection['result']['count'];
        $responseArray['collection_response'] = $collection['result']['results'];

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

        // Build URI for individual dataset
        $this->dataUri = $this->siteUri . '/dataset/' . $itemData['id'];
        // Add RDF suffix
        $this->client->setUri($this->dataUri . '.rdf');

        $response = $this->client->send();
        if (!$response->isSuccess()) {
            throw new Exception\RuntimeException(sprintf(
                'Requested "%s" got "%s".', $this->dataUri . '.rdf', $response->renderStatusLine()
            ));
        }
        $itemJson = $this->processItemMetadata($response, $itemJson);
        
        if ($ingestFiles) {
            $itemJson = $this->processItemFiles($itemData, $itemJson);
        }
        $itemJson['dataLastModified'] = new DateTime($itemData['metadata_modified']);
        // No URI available, so use record link
        $itemJson['dataUri'] = $this->dataUri;
        return $itemJson;
    }

    public function processItemMetadata($response, $itemJson)
    {    
        $itemMetadata = simplexml_load_string($response->getBody());
        
        // Most CKAN instances use dcat:dataset for data record XML container
        foreach ($itemMetadata->children('dcat', true) as $child) {    
            $itemMetadataArray = $child->children($this->prefix, true);
            // Extract dcat fields for keywords
            $itemDcatArray = $child->children('dcat', true);
        }
        
        foreach ($itemMetadataArray as $key => $value) {
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

            // Handle publisher value in nested foaf array
            if ($value->children('foaf', true)) {
                $iterate = function (&$value) use (&$iterate, &$valueArray, &$itemJson, $fieldArray) {
                    foreach ($value->children('foaf', true) as $key => $value) {
                        if ($value->children('foaf', true)) {
                            $iterate($value);
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
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
        
        foreach ($itemDcatArray as $key => $value) {
            // Save dcat keywords as dcterms.subject values
            if ($key == 'keyword') {
                $valueArray['@value'] = (string)$value;
                $valueArray['type'] = 'literal';
                $valueArray['property_id'] = $this->fieldIdMap['subject'];
                $itemJson['subject'][] = $valueArray;
            }
        }

        return $itemJson;
    }

    public function processItemFiles($itemData, $itemJson)
    {
        foreach ($itemData['resources'] as $file) {
            $fileURL = $file['url'];
            $itemJson['o:media'][] = [
                'o:ingester' => 'url',
                'o:source' => $fileURL,
                'ingest_url' => $fileURL,
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        '@value' => $file['name'],
                        'property_id' => $this->fieldIdMap['title'],
                    ],
                ],
            ];
        }
        return $itemJson;
    }
}
