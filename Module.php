<?php
namespace DataRepositoryConnector;

use Omeka\Module\AbstractModule;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
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
        $connection->exec("CREATE TABLE data_repository_import (id INT AUTO_INCREMENT NOT NULL, job_id INT NOT NULL, undo_job_id INT DEFAULT NULL, rerun_job_id INT DEFAULT NULL, added_count INT NOT NULL, updated_count INT NOT NULL, added_files INT NOT NULL, comment LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_72B61A47BE04EA9 (job_id), UNIQUE INDEX UNIQ_72B61A474C276F75 (undo_job_id), UNIQUE INDEX UNIQ_72B61A477071F49C (rerun_job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;");
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
        if (Comparator::lessThan($oldVersion, '1.4.0')) {
            $connection->exec("ALTER TABLE data_repository_import CHANGE comment comment LONGTEXT DEFAULT NULL;");
        }
        if (Comparator::lessThan($oldVersion, '1.5.0')) {
            $connection->exec("ALTER TABLE data_repository_import ADD added_files INT NOT NULL;");
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.sidebar',
            [$this, 'showSource']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.query',
            [$this, 'importSearch']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.search.query',
            [$this, 'mediaSearch']
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
            echo '<div class="meta-group">';
            echo '<h4>' . $view->translate('Original') . '</h4>';
            $linkLabel = $item->displayTitle() ?: $view->translate('Link');
            echo '<div class="value"><a href="' . $dataItem->uri() . '" target="_blank">' . $view->escapeHtml($linkLabel) . '</a></div>';
            echo '<div class="value">' . $view->translate('Last Modified: ') . ' ' . $view->i18n()->dateFormat($dataItem->lastModified()) . '</div></div>';
        }
    }

    public function importSearch($event)
    {
        $query = $event->getParam('request')->getContent();
        if (isset($query['data_import_id'])) {
            $qb = $event->getParam('queryBuilder');
            $adapter = $event->getTarget();
            $importItemAlias = $adapter->createAlias();
            $qb->innerJoin(
                \DataRepositoryConnector\Entity\DataRepositoryItem::class, $importItemAlias,
                'WITH', "$importItemAlias.item = omeka_root.id"
            )->andWhere($qb->expr()->eq(
                "$importItemAlias.job",
                $adapter->createNamedParameter($qb, $query['data_import_id'])
            ));
        }
    }

    public function mediaSearch($event)
    {
        $query = $event->getParam('request')->getContent();
        if (isset($query['data_import_id'])) {
            $qb = $event->getParam('queryBuilder');
            $adapter = $event->getTarget();
            $importItemAlias = $adapter->createAlias();
            $qb->innerJoin(
                \DataRepositoryConnector\Entity\DataRepositoryItem::class, $importItemAlias,
                'WITH', "$importItemAlias.item = omeka_root.item"
            )->andWhere($qb->expr()->eq(
                "$importItemAlias.job",
                $adapter->createNamedParameter($qb, $query['data_import_id'])
            ));
        }
    }
}
