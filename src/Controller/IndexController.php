<?php
namespace DataRepositoryConnector\Controller;

use DataRepositoryConnector\Form\DataverseForm;
use DataRepositoryConnector\Form\ZenodoForm;
use DataRepositoryConnector\Form\InvenioForm;
use DataRepositoryConnector\Form\CKANForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Stdlib\Message;

class IndexController extends AbstractActionController
{
    public function dataverseImportAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(DataverseForm::class);
        $view->setVariable('form', $form);
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $test_uri = $data['main_uri'] . '/api/info/version';
                // Check that the Dataverse is available
                if (! file_get_contents($test_uri)) {
                    $this->messenger()->addError('There was a problem connecting to the Dataverse'); // @translate
                    return $view;
                }
                $job = $this->jobDispatcher()->dispatch('DataRepositoryConnector\Job\Import', $data);
                //the DataRepoImport record is created in the job, so it doesn't
                //happen until the job is done
                $message = new Message(
                        '%s <a target="_blank" href="%s">%s</a>',
                        $this->translate('Importing in: '),
                        htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                        $this->translate('Job #') . $job->getId(),
                );
                $message->setEscapeHtml(false);
                $this->messenger()->addSuccess($message);
                $view->setVariable('job', $job);
                return $this->redirect()->toRoute('admin/data-repository-connector/past-imports');
            } else {
                $this->messenger()->addError('There was an error during validation'); // @translate
            }
        }

        return $view;
    }

    public function zenodoImportAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(ZenodoForm::class);
        $view->setVariable('form', $form);
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $job = $this->jobDispatcher()->dispatch('DataRepositoryConnector\Job\Import', $data);
                //the DataRepoImport record is created in the job, so it doesn't
                //happen until the job is done
                $message = new Message(
                        '%s <a target="_blank" href="%s">%s</a>',
                        $this->translate('Importing in: '),
                        htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                        $this->translate('Job #') . $job->getId(),
                );
                $message->setEscapeHtml(false);
                $this->messenger()->addSuccess($message);
                $view->setVariable('job', $job);
                return $this->redirect()->toRoute('admin/data-repository-connector/past-imports');
            } else {
                $this->messenger()->addError('There was an error during validation'); // @translate
            }
        }

        return $view;
    }

    public function invenioImportAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(InvenioForm::class);
        $view->setVariable('form', $form);
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $job = $this->jobDispatcher()->dispatch('DataRepositoryConnector\Job\Import', $data);
                //the DataRepoImport record is created in the job, so it doesn't
                //happen until the job is done
                $message = new Message(
                        '%s <a target="_blank" href="%s">%s</a>',
                        $this->translate('Importing in: '),
                        htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                        $this->translate('Job #') . $job->getId(),
                );
                $message->setEscapeHtml(false);
                $this->messenger()->addSuccess($message);
                $view->setVariable('job', $job);
                return $this->redirect()->toRoute('admin/data-repository-connector/past-imports');
            } else {
                $this->messenger()->addError('There was an error during validation'); // @translate
            }
        }

        return $view;
    }

    public function CkanImportAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(CKANForm::class);
        $view->setVariable('form', $form);
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $job = $this->jobDispatcher()->dispatch('DataRepositoryConnector\Job\Import', $data);
                //the DataRepoImport record is created in the job, so it doesn't
                //happen until the job is done
                $message = new Message(
                        '%s <a target="_blank" href="%s">%s</a>',
                        $this->translate('Importing in: '),
                        htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                        $this->translate('Job #') . $job->getId(),
                );
                $message->setEscapeHtml(false);
                $this->messenger()->addSuccess($message);
                $view->setVariable('job', $job);
                return $this->redirect()->toRoute('admin/data-repository-connector/past-imports');
            } else {
                $this->messenger()->addError('There was an error during validation'); // @translate
            }
        }

        return $view;
    }

    public function pastImportsAction()
    {
        $view = new ViewModel;
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            if (isset($data['jobActions'])) {
                $undoJobIds = [];
                $currentUndoJobLinks = [];
                $rerunJobIds = [];
                $currentRerunJobLinks = [];
                foreach ($data['jobActions'] as $jobId => $action) {
                    if ($action == 'undo') {
                        $undoJobIds[] = $jobId;
                        $job = $this->undoJob($jobId);
                        $currentUndoJobLinks[] = sprintf('<a target="_blank" href="%s">%s</a>', $this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]), $this->translate('Job #') . $job->getId());
                    }
                    if ($action == 'rerun') {
                        $rerunJobIds[] = $jobId;
                        $job = $this->rerunJob($jobId);
                        $currentRerunJobLinks[] = sprintf('<a target="_blank" href="%s">%s</a>', $this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]), $this->translate('Job #') . $job->getId());
                    }
                }
                if (!empty($undoJobIds)) {
                    $message = new Message(
                            '%s %s %s %s',
                            $this->translate('Undo in progress in: '),
                            implode(', ', $currentUndoJobLinks),
                            $this->translate(' for the following jobs: '),
                            implode(', ', $undoJobIds),
                    );
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                }
                if (!empty($rerunJobIds)) {
                    $message = new Message(
                            '%s %s %s %s',
                            $this->translate('Rerun in progress in: '),
                            implode(', ', $currentRerunJobLinks),
                            $this->translate(' for the following jobs: '),
                            implode(', ', $rerunJobIds),
                    );
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                }
            } else {
                $this->messenger()->addError('Error: no jobs selected'); // @translate
            }
            return $this->redirect()->toRoute('admin/data-repository-connector/past-imports');
        }
        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery() + [
            'page' => $page,
            'sort_by' => $this->params()->fromQuery('sort_by', 'id'),
            'sort_order' => $this->params()->fromQuery('sort_order', 'desc'),
        ];
        $response = $this->api()->search('data_repo_imports', $query);
        $this->paginator($response->getTotalResults(), $page);
        $this->browse()->setDefaults('data_repository_past_imports');
        $view->setVariable('imports', $response->getContent());
        return $view;
    }

    protected function undoJob($jobId)
    {
        $response = $this->api()->search('data_repo_imports', ['job_id' => $jobId]);
        $dataImport = $response->getContent()[0];
        // Get original import job args
        $deleteData = $dataImport->job()->args();
        $deleteData['previous_job'] = $jobId;
        $job = $this->jobDispatcher()->dispatch('DataRepositoryConnector\Job\Undo', $deleteData);
        $response = $this->api()->update('data_repo_imports',
                    $dataImport->id(),
                    [
                        'o:undo_job' => ['o:id' => $job->getId() ],
                    ]
                );
        return $job;
    }

    protected function rerunJob($jobId)
    {
        $response = $this->api()->search('data_repo_imports', ['job_id' => $jobId]);
        $dataImport = $response->getContent()[0];
        // Get original import job args to run again
        $rerunData = $dataImport->job()->args();
        $job = $this->jobDispatcher()->dispatch('DataRepositoryConnector\Job\Import', $rerunData);
        $response = $this->api()->update('data_repo_imports',
                    $dataImport->id(),
                    [
                        'o:rerun_job' => ['o:id' => $job->getId() ],
                    ]
                );
        return $job;
    }
}
