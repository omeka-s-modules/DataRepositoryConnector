<?php
namespace DataRepositoryConnector\Service\Form;

use DataRepositoryConnector\Form\CKANForm;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class CKANFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new CKANForm;
        $form->setUserSettings($services->get('Omeka\Settings\User'));
        $form->setAuthenticationService($services->get('Omeka\AuthenticationService'));
        $form->setApiManager($services->get('Omeka\ApiManager'));
        return $form;
    }
}
