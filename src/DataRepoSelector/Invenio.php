<?php
namespace DataRepositoryConnector\DataRepoSelector;

use Laminas\Http\Client as HttpClient;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings as Settings;
use Omeka\Job\Exception;
use Laminas\Stdlib\Parameters;
use DateTime;

/**
 * Import records from Invenio API
 */
class Invenio implements DataRepoSelectorInterface
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
        return 'Invenio'; // @translate
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

    public function getResponse($link, $limit, $searchQuery = null, $offset = 0)
    {
        $responseArray = [];
        // Iterate through pages
        $this->page = $this->page ? ++$this->page : 1;

        // Reset parameters if paginating
        $this->client->resetParameters();
        $apiLink = $link . '/api/records';
        $this->client->setUri($apiLink);

        // Get iterable response
        $this->client->setParameterGet(['q' => $searchQuery,
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

        $export = $this->siteUri . '/api/records/' . $itemData['id'];
        $this->client->setUri($export);

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

        if (isset($itemData['updated'])) {
            $itemJson['dataLastModified'] = new DateTime($itemData['updated']);
        } else {
            $itemJson['dataLastModified'] = new DateTime($itemData['metadata']['_updated']);
        }

        if (isset($itemData['links']['doi'])) {
            $itemJson['dataUri'] = $itemData['links']['doi'];
        } elseif (isset($itemData['links']['self'])) {
            $itemJson['dataUri'] = $itemData['links']['self'];
        } else {
            $itemJson['dataUri'] = $export;
        }

        return $itemJson;
    }

    public function processItemMetadata($response, $itemJson)
    {
        $itemMetadataArray = json_decode($response->getBody(), true);
        foreach ($itemMetadataArray['metadata'] as $key => $value) {
            $fieldArray = [];
            $valueArray = [];
            if (isset($itemMetadataArray['metadata']['language']) && is_string($itemMetadataArray['metadata']['language'])) {
                $valueArray['@language'] = $itemMetadataArray['metadata']['language'];
            } else if (isset($itemMetadataArray['metadata']['languages']) && is_string($itemMetadataArray['metadata']['language'][0])) {
                $valueArray['@language'] = (string)$itemMetadataArray['metadata']['languages'][0];
            }
            
            switch ($key) {
                // Use higher-level descriptive keys over generic fieldnames
                case 'titles':
                    $fieldArray['name'] = 'title';
                    $fieldArray['field_id'] = $this->fieldIdMap['title'];
                    break;
                case 'authors':
                    $fieldArray['name'] = 'creator';
                    $fieldArray['field_id'] = $this->fieldIdMap['creator'];
                    break;
                case 'publishers':
                    $fieldArray['name'] = 'publisher';
                    $fieldArray['field_id'] = $this->fieldIdMap['publisher'];
                    break;
                case 'identifiers':
                case 'issn':
                case 'isbn':
                    $fieldArray['name'] = 'identifier';
                    $fieldArray['field_id'] = $this->fieldIdMap['identifier'];
                    break;
                case 'categories':
                case 'keywords':
                    $fieldArray['name'] = 'subject';
                    $fieldArray['field_id'] = $this->fieldIdMap['subject'];
                    break;
                // Crosswalk key to DC equivalent where necessary
                case 'publicationYear':
                case 'publication_date':
                case 'date_published':
                    $key = 'available';
                    break;
                case 'date_created':
                    $key = 'created';
                    break;
                case 'externalID':
                case 'callNumber':
                    $key = 'identifier';
                    break;
                case 'formats':
                    $key = 'format';
                    break;
                case 'license':
                    $key = 'rights';
                    break;
            }

            // Handle nested arrays and Dublin Core crosswalking
            if (is_array($value)) {
                $iterate = function (&$value) use (&$iterate, &$valueArray, &$itemJson, $fieldArray, $metaKey) {
                    foreach ($value as $key => $value) {
                        if (is_array($value)) {
                            $iterate($value);
                        } else if (isset($this->fieldIdMap[$key]) && (!in_array($key, ['title','type', 'date', 'relation', 'identifier'], true))) {
                            $fieldArray['name'] = $key;
                            $fieldArray['field_id'] = $this->fieldIdMap[$key];
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                        } else if ($key === 'creator_name') {
                            $fieldArray['name'] = 'creator';
                            $fieldArray['field_id'] = $this->fieldIdMap['creator'];
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                        } else if ($key === 'contributor_name') {
                            $fieldArray['name'] = 'contributor';
                            $fieldArray['field_id'] = $this->fieldIdMap['contributor'];
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                        } else if ($key === 'keyword') {
                            $fieldArray['name'] = 'subject';
                            $fieldArray['field_id'] = $this->fieldIdMap['subject'];
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                        } else if ($key === 'attribution') {
                            $fieldArray['name'] = 'accessRights';
                            $fieldArray['field_id'] = $this->fieldIdMap['accessRights'];
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                        } else if (($key === 'name' || $key === 'title') && isset($fieldArray['name'], $fieldArray['field_id'])) {
                            // Field already set at higher level above
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                        } else if (($key === 'primary' || $key === 'secondary' || is_numeric($key)) && isset($fieldArray['name']) && $fieldArray['name'] === 'subject') {
                            // Field already set at higher level above
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                            $key = 'keyword';
                        } else if (($key === 'oai' || $key === 'value' || $key === 'doi') && isset($fieldArray['name']) && $fieldArray['name'] === 'identifier') {
                            // Field already set at higher level above
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                        } else if (($key === 'id' ) && isset($fieldArray['name']) && $fieldArray['name'] === 'license') {
                            // Field already set at higher level above
                            $valueArray['@value'] = (string)$value;
                            $valueArray['type'] = 'literal';
                        } else {
                            continue;
                        }

                        // Skip duplicates and entries without values
                        if (is_numeric($key) || !isset($valueArray['@value'])) {
                            continue;
                        }
                        $valueArray['property_id'] = $fieldArray['field_id'];
                        $itemJson[$fieldArray['name']][] = $valueArray;
                    }
                };
                $iterate($value);
            } else if (isset($this->fieldIdMap[$key])) {
                $fieldArray['name'] = $key;
                $fieldArray['field_id'] = $this->fieldIdMap[$key];
                $valueArray['@value'] = (string)$value;
                $valueArray['type'] = 'literal';
                $valueArray['property_id'] = $fieldArray['field_id'];
                $itemJson[$fieldArray['name']][] = $valueArray;
            } else if (strtolower($key) === 'doi') {
                $fieldArray['name'] = 'identifier';
                $fieldArray['field_id'] = $this->fieldIdMap['identifier'];
                $valueArray['@value'] = (string)$value;
                $valueArray['type'] = 'literal';
                $valueArray['property_id'] = $fieldArray['field_id'];
                $itemJson[$fieldArray['name']][] = $valueArray;
            }

            if (!$fieldArray) {
                continue;
            }
        }

        return $itemJson;
    }

    public function processItemFiles($itemData, $itemJson)
    {
        if (!$itemData['files']) {
            return $itemJson;
        }
        
        foreach ($itemData['files'] as $file) {
            if ($file['links']['self']) {
                $fileURL = $file['links']['self'];
            } else if ($file['ePIC_PID']) {
                $fileURL = $file['ePIC_PID'];
            } else {
                continue;
            }
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
