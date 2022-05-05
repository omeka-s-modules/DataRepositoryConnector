<?php
namespace DataRepositoryConnector\Job;

use Omeka\Job\AbstractJob;

class Undo extends AbstractJob
{
    public function perform()
    {
        $jobId = $this->getArg('jobId');
        echo $jobId;
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $response = $api->search('data_repo_items', ['job_id' => $jobId]);
        $dataItems = $response->getContent();
        if ($dataItems) {
            foreach ($dataItems as $dataItem) {
                $dataResponse = $api->delete('data_repo_items', $dataItem->id());
                $itemResponse = $api->delete('items', $dataItem->item()->id());
            }
        }
    }
}
