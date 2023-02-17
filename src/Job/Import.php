<?php
namespace DataRepositoryConnector\Job;

use DateTime;
use Omeka\Job\AbstractJob;
use EasyRdf\Graph;
use EasyRdf\Resource as RdfResource;
use EasyRdf\RdfNamespace;
use Omeka\Job\Exception;

class Import extends AbstractJob
{
    protected $client;

    protected $propertyUriIdMap;

    protected $api;

    protected $itemSetArray;

    protected $itemSiteArray;

    protected $addedCount;

    protected $updatedCount;

    public function perform()
    {
        $dataRepoSelector = $this->getServiceLocator()->get('DataRepositoryConnector\DataRepoSelectorManager');
        $dataRepoSelectedService = $this->getArg('data_repo_service');
        $this->dataRepoService = $dataRepoSelector->get($dataRepoSelectedService);

        $this->siteUri = rtrim($this->getArg('main_uri'), '/');
        // Build collection URI & save to job args to link from past imports pages
        $collectionLink = $this->dataRepoService->buildCollectionLink($this->siteUri, $this->getArg('collection_id'));
        $jobArgs = $this->job->getArgs();
        $jobArgs['collection_link'] = $collectionLink;
        $this->job->setArgs($jobArgs);

        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $this->client = $this->getServiceLocator()->get('Omeka\HttpClient');
        $this->client->setOptions(['timeout' => 120]);
        $comment = $this->getArg('comment');
        $dataRepoImportJson = [
                            'o:job' => ['o:id' => $this->job->getId()],
                            'comment' => $comment,
                            'added_count' => 0,
                            'updated_count' => 0,
                          ];
        $response = $this->api->create('data_repo_imports', $dataRepoImportJson);
        $importRecordId = $response->getContent()->id();
        $this->addedCount = 0;
        $this->updatedCount = 0;
        $this->itemSetArray = $this->getArg('itemSets', false);
        $this->itemSiteArray = $this->getArg('itemSites', false);

        $this->importCollection($this->siteUri);

        $dataRepoImportJson = [
                            'o:job' => ['o:id' => $this->job->getId()],
                            'comment' => $comment,
                            'added_count' => $this->addedCount,
                            'updated_count' => $this->updatedCount,
                          ];
        $response = $this->api->update('data_repo_imports', $importRecordId, $dataRepoImportJson);
    }

    public function importCollection($siteUri)
    {
        $offset = 0;
        $hasNext = true;
        // Prepare metadata fields for given service export format
        $this->dataRepoService->preparefieldIdMap($this->getArg('data_md_format'));
        while ($hasNext) {
            $collectionResponse = $this->dataRepoService->getResponse($this->siteUri, $this->getArg('limit'), $this->getArg('collection_id'), $offset);
            if ($collectionResponse) {
                $itemCount = $collectionResponse['item_count'];
                $collectionResponse = $collectionResponse['collection_response'];

                // If no more results or test import checked, stop iterating
                if ($offset >= $itemCount || $this->getArg('test_import')) {
                    $hasNext = false;
                }
                
                $toCreate = [];
                $toUpdate = [];

                foreach ($collectionResponse as $index => $itemData) {

                    $resourceJson = $this->dataRepoService->buildResource($this->siteUri, $this->getArg('data_md_format'), $this->getArg('ingest_files'), $itemData);

                    if (empty($resourceJson)) {
                        continue;
                    }

                    // Assign sets & sites to item
                    if ($this->itemSetArray) {
                        $resourceJson['o:item_set'] = $this->itemSetArray;
                    }
                    if ($this->itemSiteArray) {
                        foreach ($this->itemSiteArray as $itemSite) {
                            $itemSites[] = $itemSite;
                        }
                        $resourceJson['o:site'] = $itemSites;
                    } else {
                        $resourceJson['o:site'] = [];
                    }
                
                    //see if the item has already been imported
                    $response = $this->api->search('data_repo_items', ['uri' => $resourceJson['dataUri']]);
                    $content = $response->getContent();
                    if (empty($content)) {
                        $dataRepoItem = false;
                        $omekaItem = false;
                    } else {
                        $dataRepoItem = $content[0];
                        $omekaItem = $dataRepoItem->item();
                    }
                
                    if ($omekaItem) {
                        $itemId = $omekaItem->id();
                        // keep existing item sets/sites, add any new item sets/sites
                        $existingItem = $this->api->search('items', ['id' => $itemId])->getContent();
                
                        $existingItemSets = array_keys($existingItem[0]->itemSets()) ?: [];
                        $newItemSets = $resourceJson['o:item_set'] ?: [];
                        $resourceJson['o:item_set'] = array_merge($existingItemSets, $newItemSets);
                
                        $existingItemSites = array_keys($existingItem[0]->sites()) ?: [];
                        $newItemSites = $resourceJson['o:site'] ?: [];
                        $resourceJson['o:site'] = array_merge($existingItemSites, $newItemSites);
                
                        $response = $this->api->update('items', $omekaItem->id(), $resourceJson);
                    } else {
                        $response = $this->api->create('items', $resourceJson);
                        $itemId = $response->getContent()->id();
                    }
                
                    $dataRepoItemJson = [
                                'o:job' => ['o:id' => $this->job->getId()],
                                'o:item' => ['o:id' => $itemId],
                                'uri' => $resourceJson['dataUri'],
                                'last_modified' => $resourceJson['dataLastModified'],
                              ];
                
                    if ($dataRepoItem) {
                        $response = $this->api->update('data_repo_items', $dataRepoItem->id(), $dataRepoItemJson);
                        $this->updatedCount++;
                    } else {
                        $this->addedCount++;
                        $response = $this->api->create('data_repo_items', $dataRepoItemJson);
                    }
                }

                $offset = $offset + $this->getArg('limit');
            }
        }
    }
}
