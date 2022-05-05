<?php
namespace DataRepositoryConnector\DataRepoSelector;

use Omeka\ServiceManager\AbstractPluginManager;

class Manager extends AbstractPluginManager
{
    protected $autoAddInvokableClass = false;

    protected $instanceOf = DataRepoSelectorInterface::class;

    public function get($name, array $options = null)
    {
        return parent::get($name, $options);
    }
}
