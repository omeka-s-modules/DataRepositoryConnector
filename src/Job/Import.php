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

        $this->originalIdentityMap = $this->getServiceLocator()->get('Omeka\EntityManager')->getUnitOfWork()->getIdentityMap();
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

                    // Sleep and retry multiple times if API exception encountered
                    // to get around TOO MANY REQUESTS and other server-side exceptions
                    // especially for larger collections
                    $NUM_OF_ATTEMPTS = 5;
                    $attempts = 0;
                    do {
                        try {
                            $resourceJson = $this->dataRepoService->buildResource($this->siteUri, $this->getArg('data_md_format'), $this->getArg('ingest_files'), $itemData);
                        } catch (\Exception $e) {
                            $attempts++;
                            sleep(60);
                            continue;
                        }
                        break;
                    } while($attempts < $NUM_OF_ATTEMPTS);

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
                        $importRecord = false;
                    } else {
                        $importRecord = $content[0];
                    }
                
                    if ($importRecord) {
                        $itemId = $importRecord->item()->id();
                        // keep existing item sets/sites, add any new item sets/sites
                        $existingItem = $this->api->search('items', ['id' => $itemId])->getContent();
                
                        $existingItemSets = array_keys($existingItem[0]->itemSets()) ?: [];
                        $newItemSets = $resourceJson['o:item_set'] ?: [];
                        $resourceJson['o:item_set'] = array_merge($existingItemSets, $newItemSets);
                
                        $existingItemSites = array_keys($existingItem[0]->sites()) ?: [];
                        $newItemSites = $resourceJson['o:site'] ?: [];
                        $resourceJson['o:site'] = array_merge($existingItemSites, $newItemSites);

                        $resourceJson['id'] = $itemId;
                        $toUpdate[$importRecord->id()] = $resourceJson;
                    } else {
                        $toCreate["create" . $index] = $resourceJson;
                    }
                }
                $this->createItems($toCreate);
                $this->updateItems($toUpdate);

                $offset = $offset + $this->getArg('limit');
            }
        }
    }

    protected function createItems($toCreate)
    {
        $createResponse = $this->api->batchCreate('items', $toCreate, [], ['continueOnError' => true]);
        $this->addedCount = $this->addedCount + count($createResponse->getContent());

        $createImportRecordsJson = [];
        $createContent = $createResponse->getContent();

        foreach ($createContent as $id => $resourceReference) {
            //get the original data used for individual item creation
            $toCreateData = $toCreate[$id];

            $dataRepoItemJson = [
                            'o:job' => ['o:id' => $this->job->getId()],
                            'o:item' => ['o:id' => $resourceReference->id()],
                            'uri' => $toCreateData['dataUri'],
                            'last_modified' => $toCreateData['dataLastModified'],
                        ];
            $createImportRecordsJson[] = $dataRepoItemJson;
        }

        $createImportRecordResponse = $this->api->batchCreate('data_repo_items', $createImportRecordsJson, [], ['continueOnError' => true]);
    }

    protected function updateItems($toUpdate)
    {
        //  batchUpdate would be nice, but complexities abound. See https://github.com/omeka/omeka-s/issues/326
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $updateResponses = [];
        foreach ($toUpdate as $importRecordId => $itemJson) {
            $this->updatedCount = $this->updatedCount + 1;
            $updateResponses[$importRecordId] = $this->api->update('items', $itemJson['id'], $itemJson, [], [
                'flushEntityManager' => false,
                'continueOnError' => true
            ]);
        }

        foreach ($updateResponses as $importRecordId => $resourceReference) {
            $toUpdateData = $toUpdate[$importRecordId];
            $dataRepoItemJson = [
                            'o:job' => ['o:id' => $this->job->getId()],
                            'uri' => $toUpdateData['dataUri'],
                            'last_modified' => $toUpdateData['dataLastModified'],
                        ];
            $updateImportRecordResponse = $this->api->update('data_repo_items', $importRecordId, $dataRepoItemJson, [], [
                'flushEntityManager' => false,
                'continueOnError' => true
            ]);
        }
        $em->flush();
        $this->detachAllNewEntities($this->originalIdentityMap);
    }

    /**
     * Given an old copy of the Doctrine identity map, reset
     * the entity manager to that state by detaching all entities that
     * did not exist in the prior state.
     *
     * @internal This is a copy-paste of the functionality from the abstract entity adapter
     *
     * @param array $oldIdentityMap
     */
    protected function detachAllNewEntities(array $oldIdentityMap)
    {
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $identityMap = $entityManager->getUnitOfWork()->getIdentityMap();
        foreach ($identityMap as $entityClass => $entities) {
            foreach ($entities as $idHash => $entity) {
                if (!isset($oldIdentityMap[$entityClass][$idHash])) {
                    $entityManager->detach($entity);
                }
            }
        }
    }
}
