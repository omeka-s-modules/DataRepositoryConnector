<?php
namespace DataRepositoryConnector\Service\DataRepoSelector;

use DataRepositoryConnector\DataRepoSelector\Dataverse;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class DataverseFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $apiManager =  $services->get('Omeka\ApiManager');
        $client = $services->get('Omeka\HttpClient');

        return new Dataverse($apiManager, $client);
    }
}
