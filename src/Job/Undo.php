<?php
namespace DataRepositoryConnector\Job;

use Omeka\Job\AbstractJob;

class Undo extends AbstractJob
{
    public function perform()
    {
        $jobId = $this->getArg('previous_job');
        $comment = $this->getArg('comment');
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        // Delete items
        $response = $api->search('data_repo_items', ['job_id' => $jobId]);
        $dataItems = $response->getContent();
        $deletedItemCount = 0;
        $deletedFileCount = 0;
        if ($dataItems) {
            foreach ($dataItems as $dataItem) {
                $deletedFileCount += count($dataItem->item()->media());
                $api->delete('data_repo_items', $dataItem->id());
                $api->delete('items', $dataItem->item()->id());
                $deletedItemCount++;
            }
        }

        $commentParts = array_filter([
            $comment,
            $deletedItemCount ? $deletedItemCount . ' items deleted' : null,
            $deletedFileCount ? $deletedFileCount . ' files deleted' : null,
        ]);
        $comment = implode('; ', $commentParts);
        $dataRepoImportJson = [
            'o:job' => ['o:id' => $this->job->getId()],
            'comment' => $comment,
            'added_count' => 0,
            'updated_count' => 0,
            'added_files' => 0,
        ];
        $response = $api->create('data_repo_imports', $dataRepoImportJson);
        $jobArgs = $this->job->getArgs();
        $jobArgs['comment'] = $comment;
        $this->job->setArgs($jobArgs);
    }
}
