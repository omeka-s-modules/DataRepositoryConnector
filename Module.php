<?php
namespace DataRepositoryConnector;

use Omeka\Module\AbstractModule;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use FedoraConnector\Form\ConfigForm;
use Composer\Semver\Comparator;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            null,
            ['DataRepositoryConnector\Api\Adapter\DataRepositoryItemAdapter'],
            ['search', 'read']
            );
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec("CREATE TABLE data_repository_item (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, job_id INT NOT NULL, uri VARCHAR(255) NOT NULL, last_modified DATETIME NOT NULL, UNIQUE INDEX UNIQ_D984EBE1126F525E (item_id), INDEX IDX_D984EBE1BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;");
        $connection->exec("CREATE TABLE data_repository_import (id INT AUTO_INCREMENT NOT NULL, job_id INT NOT NULL, undo_job_id INT DEFAULT NULL, rerun_job_id INT DEFAULT NULL, added_count INT NOT NULL, updated_count INT NOT NULL, comment VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_72B61A47BE04EA9 (job_id), UNIQUE INDEX UNIQ_72B61A474C276F75 (undo_job_id), UNIQUE INDEX UNIQ_72B61A477071F49C (rerun_job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE =  InnoDB;");
        $connection->exec("ALTER TABLE data_repository_item ADD CONSTRAINT FK_D984EBE1126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE;");
        $connection->exec("ALTER TABLE data_repository_item ADD CONSTRAINT FK_D984EBE1BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);");
        $connection->exec("ALTER TABLE data_repository_import ADD CONSTRAINT FK_72B61A47BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)");
        $connection->exec("ALTER TABLE data_repository_import ADD CONSTRAINT FK_72B61A474C276F75 FOREIGN KEY (undo_job_id) REFERENCES job (id);");
        $connection->exec("ALTER TABLE data_repository_import ADD CONSTRAINT FK_72B61A477071F49C FOREIGN KEY (rerun_job_id) REFERENCES job (id);");
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec("ALTER TABLE data_repository_item DROP FOREIGN KEY FK_D984EBE1126F525E;");
        $connection->exec("ALTER TABLE data_repository_item DROP FOREIGN KEY FK_D984EBE1BE04EA9;");
        $connection->exec("ALTER TABLE data_repository_import DROP FOREIGN KEY FK_72B61A47BE04EA9;");
        $connection->exec("ALTER TABLE data_repository_import DROP FOREIGN KEY FK_72B61A474C276F75;");
        $connection->exec('DROP TABLE data_repository_item');
        $connection->exec('DROP TABLE data_repository_import');
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services)
    {
        $connection = $services->get('Omeka\Connection');
        if (Comparator::lessThan($oldVersion, '1.2.0')) {
            $connection->exec("ALTER TABLE data_repository_import ADD rerun_job_id INT DEFAULT NULL AFTER undo_job_id;");
            $connection->exec("ALTER TABLE data_repository_import ADD CONSTRAINT FK_72B61A477071F49C FOREIGN KEY (rerun_job_id) REFERENCES job (id);");
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
                'Omeka\Controller\Admin\Item',
                'view.show.after',
                [$this, 'showSource']
                );
    }

    public function showSource($event)
    {
        $view = $event->getTarget();
        $item = $view->item;
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $response = $api->search('data_repo_items', ['item_id' => $item->id()]);
        $dataItems = $response->getContent();
        if ($dataItems) {
            $dataItem = $dataItems[0];
            echo '<h3>' . $view->translate('Original') . '</h3>';
            echo '<p>' . $view->translate('Last Modified') . ' ' . $view->i18n()->dateFormat($dataItem->lastModified()) . '</p>';
            echo '<p><a href="' . $dataItem->uri() . '">' . $view->translate('Link') . '</a></p>';
        }
    }
}
