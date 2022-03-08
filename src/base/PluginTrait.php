<?php
namespace verbb\supertable\base;

use verbb\supertable\SuperTable;
use verbb\supertable\services\SuperTableService;

use Craft;

use yii\log\Logger;

use verbb\base\BaseHelper;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static SuperTable $plugin;


    // Static Methods
    // =========================================================================
    
    public static function log($message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'super-table');
    }

    public static function error($message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'super-table');
    }


    // Public Methods
    // =========================================================================

    public function getService(): SuperTableService
    {
        return $this->get('service');
    }


    // Private Methods
    // =========================================================================

    private function _setPluginComponents(): void
    {
        $this->setComponents([
            'service' => SuperTableService::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _setLogging(): void
    {
        BaseHelper::setFileLogging('super-table');
    }

}