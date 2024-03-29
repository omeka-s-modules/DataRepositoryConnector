<?php
namespace DataRepositoryConnector\Form;

use Omeka\Form\Element\ItemSetSelect;
use Omeka\Form\Element\SiteSelect;
use Omeka\Settings\UserSettings;
use Omeka\Api\Manager as ApiManager;
use Laminas\Authentication\AuthenticationService;
use Laminas\Form\Form;

class InvenioForm extends Form
{
    /**
     * @var UserSettings
     */
    protected $userSettings;

    /**
     * @var AuthenticationService
     */
    protected $AuthenticationService;

    /**
     * @var ApiManager
     */
    protected $apiManager;

    public function init()
    {
        // Add hidden field with data repo service name for DataRepoSelecter service
        $this->add([
            'name' => 'data_repo_service',
            'type' => 'hidden',
            'attributes' => [
                'id' => 'data-repo-service',
                'value' => 'invenio',
            ],
        ]);
        
        // Add hidden field to designate dcterms metadata format
        $this->add([
            'name' => 'data_md_format',
            'type' => 'hidden',
            'attributes' => [
                'id' => 'data-md-format',
                'value' => 'dcterms',
            ],
        ]);
        
        $this->add([
            'name' => 'main_uri',
            'type' => 'url',
            'options' => [
                'label' => 'Main Invenio URL', // @translate
                'info' => 'Base URL of the Invenio site. Example: <a target="_blank" href="http://opendata.cern.ch">http://opendata.cern.ch</a>', // @translate
                'escape_info' => false,
            ],
            'attributes' => [
                'id' => 'main_uri',
                'required' => true,
            ],
        ]);
        
        $this->add([
            'name' => 'collection_id',
            'type' => 'text',
            'options' => [
                'label' => 'Search Query', // @translate
                'info' => 'Filter records using <a target="_blank" href="https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html#query-string-syntax">ElasticSearch query syntax</a>. Examples: "oceanography", "access_right:open"', // @translate
                'escape_info' => false,
            ],
            'attributes' => [
                'id' => 'collection_id',
            ],
        ]);

        $this->add([
            'name' => 'limit',
            'type' => 'text',
            'options' => [
                'label' => 'Limit', // @translate
                'info' => 'The maximum number of results to retrieve at once from Invenio instance. If you notice errors or missing data, try lowering this number. Increasing it might make imports faster.', // @translate
            ],
            'attributes' => [
                'id' => 'limit',
                'required' => 'true',
                'value' => '100',
            ],
        ]);
        
        $this->add([
            'name' => 'test_import',
            'type' => 'checkbox',
            'options' => [
                'label' => 'Test import', // @translate
                'info' => 'If checked, ONLY import the # of results indicated in Limit field above. Useful for testing and fine-tuning.', // @translate
            ],
            'attributes' => [
                'id' => 'test-import',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'ingest_files',
            'type' => 'checkbox',
            'options' => [
                'label' => 'Import files into Omeka S', // @translate
                'info' => 'If checked, all data files associated with a record will be imported into Omeka S.', // @translate
            ],
            'attributes' => [
                'id' => 'ingest-files',
            ],
        ]);

        $this->add([
            'name' => 'comment',
            'type' => 'textarea',
            'options' => [
                'label' => 'Comment', // @translate
                'info' => 'A note about the purpose or source of this import.', // @translate
            ],
            'attributes' => [
                'id' => 'comment',
            ],
        ]);

        $this->add([
            'name' => 'itemSets',
            'type' => ItemSetSelect::class,
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select item set(s)', // @translate
                'multiple' => true,
                'id' => 'item-sets',
            ],
            'options' => [
                'label' => 'Item sets', // @translate
                'info' => 'Optional. Import items into item set(s).', // @translate
                'empty_option' => ''
            ],
        ]);

        // Merge assign_new_item sites and default user sites
        $defaultAddSiteRepresentations = $this->getApiManager()->search('sites', ['assign_new_items' => true])->getContent();
        foreach ($defaultAddSiteRepresentations as $defaultAddSiteRepresentation) {
            $defaultAddSites[] = $defaultAddSiteRepresentation->id();
        }
        $defaultAddSiteStrings = $defaultAddSites ?? [];

        $userId = $this->getAuthenticationService()->getIdentity()->getId();
        $userDefaultSites = $userId ? $this->getUserSettings()->get('default_item_sites', null, $userId) : [];
        $userDefaultSiteStrings = $userDefaultSites ?? [];

        $sites = array_merge($defaultAddSiteStrings, $userDefaultSiteStrings);

        $this->add([
            'name' => 'itemSites',
            'type' => SiteSelect::class,
            'attributes' => [
                'value' => $sites,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select site(s)', // @translate
                'multiple' => true,
                'id' => 'item-sites',
            ],
            'options' => [
                'label' => 'Sites', // @translate
                'info' => 'Optional. Import items into site(s).', // @translate
                'empty_option' => '',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'itemSets',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'itemSites',
            'required' => false,
        ]);
    }

    /**
     * @param UserSettings $userSettings
     */
    public function setUserSettings(UserSettings $userSettings)
    {
        $this->userSettings = $userSettings;
    }

    /**
     * @return UserSettings
     */
    public function getUserSettings()
    {
        return $this->userSettings;
    }

    /**
     * @param AuthenticationService $AuthenticationService
     */
    public function setAuthenticationService(AuthenticationService $AuthenticationService)
    {
        $this->AuthenticationService = $AuthenticationService;
    }

    /**
     * @return AuthenticationService
     */
    public function getAuthenticationService()
    {
        return $this->AuthenticationService;
    }

    /**
     * @param ApiManager $apiManager
     */
    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }

    /**
     * @return ApiManager
     */
    public function getApiManager()
    {
        return $this->apiManager;
    }
}
