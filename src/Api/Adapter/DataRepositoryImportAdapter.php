<?php
namespace DataRepositoryConnector\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class DataRepositoryImportAdapter extends AbstractEntityAdapter
{
    public function getEntityClass()
    {
        return 'DataRepositoryConnector\Entity\DataRepositoryImport';
    }

    public function getResourceName()
    {
        return 'data_repo_imports';
    }

    public function getRepresentationClass()
    {
        return 'DataRepositoryConnector\Api\Representation\DataRepositoryImportRepresentation';
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        if (isset($data['o:job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:job']['o:id']);
            $entity->setJob($job);
        }

        if (isset($data['o:undo_job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:undo_job']['o:id']);
            $entity->setUndoJob($job);
        }

        if (isset($data['o:rerun_job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:rerun_job']['o:id']);
            $entity->setRerunJob($job);
        }

        if (isset($data['added_count'])) {
            $entity->setAddedCount($data['added_count']);
        }

        if (isset($data['updated_count'])) {
            $entity->setUpdatedCount($data['updated_count']);
        }

        if (isset($data['comment'])) {
            $entity->setComment($data['comment']);
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['job_id'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.job',
                $this->createNamedParameter($qb, $query['job_id']))
            );
        }
    }
}
