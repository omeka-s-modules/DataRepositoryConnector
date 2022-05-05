<?php
namespace DataRepositoryConnector\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class DataRepositoryItemRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'last_modified' => $this->resource->getLastModified(),
            'uri' => $this->resource->getUri(),
            'o:item' => $this->getReference(),
            'o:job' => $this->getReference(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:DataRepositoryItem';
    }

    public function lastModified()
    {
        return $this->resource->getlastModified();
    }

    public function uri()
    {
        return $this->resource->getUri();
    }

    public function item()
    {
        return $this->getAdapter('items')
            ->getRepresentation($this->resource->getItem());
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }
}
