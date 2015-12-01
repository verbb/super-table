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
        return '0.3.8';
    }

    public function getSchemaVersion()
    {
        return '1.0.0';
    }

    public function getDeveloper()
    {
        return 'S. Group';
    }

    public function getDeveloperUrl()
    {
        return 'http://sgroup.com.au';
    }

    public function getPluginUrl()
    {
        return 'https://github.com/engram-design/SuperTable';
    }

    public function getDocumentationUrl()
    {
        return $this->getPluginUrl() . '/blob/master/README.md';
    }

    public function getReleaseFeedUrl()
    {
        return $this->getPluginUrl() . '/blob/master/changelog.json';
    }

    public function onBeforeInstall()
    {   
        // Craft 2.3.2615 getFieldsForElementsQuery()
        if (version_compare(craft()->getVersion() . '.' . craft()->getBuild(), '2.3.2615', '<')) {
            throw new Exception($this->getName() . ' requires Craft CMS 2.3.2615+ in order to run.');
        }
    }


    // =========================================================================
    // HOOKS
    // =========================================================================

    // FeedMe 1.4.0
    public function registerFeedMeMappingOptions()
    {
        return array(
            'SuperTable' => 'supertable/_plugins/feedMeOptions',
        );
    }

    public function prepForFeedMeFieldType($field, &$data, $handle)
    {
        craft()->superTable->prepForFeedMeFieldType($field, $data, $handle);
    }

    public function postForFeedMeFieldType(&$fieldData)
    {
        craft()->superTable->postForFeedMeFieldType($fieldData);
    }

    // Export 0.5.8
    public function registerExportOperation(&$data, $handle)
    {
        craft()->superTable->registerExportOperation($data, $handle);
    }
 
}
