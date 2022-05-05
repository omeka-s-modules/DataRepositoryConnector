<?php
namespace DataRepositoryConnector\Service\Form;

use DataRepositoryConnector\Form\DataverseForm;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class DataverseFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new DataverseForm;
        $form->setUserSettings($services->get('Omeka\Settings\User'));
        $form->setAuthenticationService($services->get('Omeka\AuthenticationService'));
        $form->setApiManager($services->get('Omeka\ApiManager'));
        return $form;
    }
}
