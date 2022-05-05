<?php
namespace DataRepositoryConnector\DataRepoSelector;

use Omeka\Api\Representation\ItemRepresentation;
use Laminas\Http\Response;

/**
 * Interface for different Data Repository Import Services.
 */
interface DataRepoSelectorInterface
{
    /**
     * Get a human-readable label for this Data Repo Service.
     *
     * @return string
     */
    public function getLabel();
    
    /**
     * Prepare Metadata fields from chosen vocabulary.
     *
     * @param string $dataMetadataFormat
     * @return array
     */
    public function prepareFieldIdMap($dataMetadataFormat);
    
    /**
     * Get initial response from Data Repository for iterating through records.
     *
     * @param string $siteUri
     * @param string $limit
     * @param string $localId
     * @param string $offset
     * @return Response
     */
    public function getResponse($siteUri, $limit, $localId, $offset);

    /**
     * Build Omeka Item Resource JSON, adding sites/sets, metadata and files if applicable.
     *
     * @param string $siteUri
     * @param string $dataMetadataFormat
     * @param bool $ingestFiles
     * @param array $itemData
     * @return array
     */
    public function buildResource($siteUri, $dataMetadataFormat, $ingestFiles, $itemData);

    /**
     * Process & save data repository record metadata to Omeka Item.
     *
     * @param Response $response
     * @param array $itemJson
     * @return array
     */
    public function processItemMetadata($response, $itemJson);
    
    /**
     * Process & save any files associated with data repository record.
     *
     * @param string $siteUri
     * @param array $itemData
     * @param array $itemJson
     * @return array
     */
    public function processItemFiles($itemData, $itemJson);
}
