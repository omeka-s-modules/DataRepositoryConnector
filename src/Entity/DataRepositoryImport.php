<?php
namespace DataRepositoryConnector\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;

/**
 * @Entity
 */
class DataRepositoryImport extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\Job")
     * @JoinColumn(nullable=false)
     */
    protected $job;

    /**
     * @Column(type="integer")
     */
    protected $addedCount;

    /**
     * @Column(type="integer")
     */
    protected $updatedCount;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\Job")
     * @JoinColumn(nullable=true)
     */
    protected $undoJob;

    /**
     * @Column(type="string", nullable=true)
     */
    protected $comment;

    public function getId()
    {
        return $this->id;
    }

    public function setJob(Job $job)
    {
        $this->job = $job;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setUndoJob(Job $job)
    {
        $this->undoJob = $job;
    }

    public function getUndoJob()
    {
        return $this->undoJob;
    }

    public function setAddedCount($count)
    {
        $this->addedCount = $count;
    }

    public function getAddedCount()
    {
        return $this->addedCount;
    }

    public function setUpdatedCount($count)
    {
        $this->updatedCount = $count;
    }

    public function getUpdatedCount()
    {
        return $this->updatedCount;
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    public function getComment()
    {
        return $this->comment;
    }
}
