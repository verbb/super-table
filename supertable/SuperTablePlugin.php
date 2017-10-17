<?php
namespace Craft;

class SuperTablePlugin extends BasePlugin
{
    // =========================================================================
    // PLUGIN INFO
    // =========================================================================

    public function getName()
    {
        return Craft::t('Super Table');
    }

    public function getVersion()
    {
        return '1.0.6';
    }

    public function getSchemaVersion()
    {
        return '1.0.0';
    }

    public function getDeveloper()
    {
        return 'Verbb';
    }

    public function getDeveloperUrl()
    {
        return 'https://verbb.io';
    }

    public function getPluginUrl()
    {
        return 'https://github.com/verbb/super-table';
    }

    public function getDocumentationUrl()
    {
        return $this->getPluginUrl() . '/blob/master/README.md';
    }

    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/verbb/super-table/master/changelog.json';
    }

    public function onBeforeInstall()
    {
        $version = craft()->getVersion();

        // Craft 2.6.2951 deprecated `craft()->getBuild()`, so get the version number consistently
        if (version_compare(craft()->getVersion(), '2.6.2951', '<')) {
            $version = craft()->getVersion() . '.' . craft()->getBuild();
        }

        // Craft 2.3.2615 getFieldsForElementsQuery()
        if (version_compare($version, '2.3.2615', '<')) {
            throw new Exception($this->getName() . ' requires Craft CMS 2.3.2615+ in order to run.');
        }
    }

    public function init()
    {
        Craft::import('plugins.supertable.integrations.feedme.fields.SuperTableFeedMeFieldType');

        // Hook on to (any) element deletion event to cleanup Super Table Blocks for that element
        craft()->on('elements.onBeforeDeleteElements', function(Event $event) {
            craft()->superTable->onBeforeDeleteElements($event);
        });
    }


    // =========================================================================
    // HOOKS
    // =========================================================================

    // FeedMe 2.0.0
    public function registerFeedMeFieldTypes()
    {
        return array(
            new SuperTableFeedMeFieldType(),
        );
    }

    // Export 0.5.8
    public function registerExportOperation(&$data, $handle)
    {
        craft()->superTable->registerExportOperation($data, $handle);
    }
 
}
