<?php
namespace DataRepositoryConnector\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class DataRepositoryImportRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'added_count' => $this->resource->getAddedCount(),
            'updated_count' => $this->resource->getUpdatedCount(),
            'comment' => $this->resource->getComment(),
            'o:job' => $this->getReference(),
            'o:undo_job' => $this->getReference(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:DataRepositoryImport';
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }

    public function undoJob()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getUndoJob());
    }

    public function comment()
    {
        return $this->resource->getComment();
    }

    public function addedCount()
    {
        return $this->resource->getAddedCount();
    }

    public function updatedCount()
    {
        return $this->resource->getUpdatedCount();
    }
}
