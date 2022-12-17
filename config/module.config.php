<?php
return [
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => OMEKA_PATH . '/modules/DataRepositoryConnector/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'DataRepositoryConnector\DataRepoSelectorManager' => DataRepositoryConnector\Service\DataRepoSelector\ManagerFactory::class,
        ],
    ],
    'data_repo_services' => [
        'factories' => [
            'dataverse' => DataRepositoryConnector\Service\DataRepoSelector\DataverseFactory::class,
            'zenodo' => DataRepositoryConnector\Service\DataRepoSelector\ZenodoFactory::class,
            'invenio' => DataRepositoryConnector\Service\DataRepoSelector\InvenioFactory::class,
            'ckan' => DataRepositoryConnector\Service\DataRepoSelector\CKANFactory::class,
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'data_repo_items' => 'DataRepositoryConnector\Api\Adapter\DataRepositoryItemAdapter',
            'data_repo_imports' => 'DataRepositoryConnector\Api\Adapter\DataRepositoryImportAdapter',
        ],
    ],
    'controllers' => [
        'invokables' => [
            'DataRepositoryConnector\Controller\Index' => 'DataRepositoryConnector\Controller\IndexController',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            OMEKA_PATH . '/modules/DataRepositoryConnector/view',
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            OMEKA_PATH . '/modules/DataRepositoryConnector/src/Entity',
        ],
        'proxy_paths' => [
            OMEKA_PATH . '/modules/DataRepositoryConnector/data/doctrine-proxies',
        ],
    ],
    'form_elements' => [
        'factories' => [
            'DataRepositoryConnector\Form\DataverseForm' => 'DataRepositoryConnector\Service\Form\DataverseFormFactory',
            'DataRepositoryConnector\Form\ZenodoForm' => 'DataRepositoryConnector\Service\Form\ZenodoFormFactory',
            'DataRepositoryConnector\Form\InvenioForm' => 'DataRepositoryConnector\Service\Form\InvenioFormFactory',
            'DataRepositoryConnector\Form\CKANForm' => 'DataRepositoryConnector\Service\Form\CKANFormFactory',
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'data-repository-connector' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/data-repository-connector',
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'dataverse-import' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/dataverse-import',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'DataRepositoryConnector\Controller',
                                        'controller' => 'Index',
                                        'action' => 'dataverse-import',
                                    ],
                                ],
                            ],
                            'zenodo-import' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/zenodo-import',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'DataRepositoryConnector\Controller',
                                        'controller' => 'Index',
                                        'action' => 'zenodo-import',
                                    ],
                                ],
                            ],
                            'invenio-import' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/invenio-import',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'DataRepositoryConnector\Controller',
                                        'controller' => 'Index',
                                        'action' => 'invenio-import',
                                    ],
                                ],
                            ],
                            'ckan-import' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/ckan-import',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'DataRepositoryConnector\Controller',
                                        'controller' => 'Index',
                                        'action' => 'ckan-import',
                                    ],
                                ],
                            ],
                            'past-imports' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/past-imports',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'DataRepositoryConnector\Controller',
                                        'controller' => 'Index',
                                        'action' => 'past-imports',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Data Repository Connector', // @translate
                'route' => 'admin/data-repository-connector/past-imports',
                'resource' => 'DataRepositoryConnector\Controller\Index',
                'pages' => [
                    [
                        'label' => 'Dataverse', // @translate
                        'route' => 'admin/data-repository-connector/dataverse-import',
                        'controller' => 'Index',
                        'resource' => 'DataRepositoryConnector\Controller\Index',
                    ],
                    [
                        'label' => 'Zenodo', // @translate
                        'route' => 'admin/data-repository-connector/zenodo-import',
                        'controller' => 'Index',
                        'resource' => 'DataRepositoryConnector\Controller\Index',
                    ],
                    [
                        'label' => 'Invenio', // @translate
                        'route' => 'admin/data-repository-connector/invenio-import',
                        'controller' => 'Index',
                        'resource' => 'DataRepositoryConnector\Controller\Index',
                    ],
                    [
                        'label' => 'CKAN', // @translate
                        'route' => 'admin/data-repository-connector/ckan-import',
                        'controller' => 'Index',
                        'resource' => 'DataRepositoryConnector\Controller\Index',
                    ],
                    [
                        'label' => 'Past Imports', // @translate
                        'route' => 'admin/data-repository-connector/past-imports',
                        'controller' => 'Index',
                        'action' => 'past-imports',
                        'resource' => 'DataRepositoryConnector\Controller\Index',
                    ],
                ],
            ],
        ],
    ],
];
