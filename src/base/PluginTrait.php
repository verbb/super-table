<?php
namespace verbb\supertable\base;

use verbb\supertable\SuperTable;
use verbb\supertable\services\Service;
use verbb\base\BaseHelper;

use Craft;

use yii\log\Logger;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static SuperTable $plugin;


    // Static Methods
    // =========================================================================
    
    public static function log(string $message, array $params = []): void
    {
        $message = Craft::t('super-table', $message, $params);

        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'super-table');
    }

    public static function error(string $message, array $params = []): void
    {
        $message = Craft::t('super-table', $message, $params);

        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'super-table');
    }


    // Public Methods
    // =========================================================================

    public function getService(): Service
    {
        return $this->get('service');
    }


    // Private Methods
    // =========================================================================

    private function _registerComponents(): void
    {
        $this->setComponents([
            'service' => Service::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _registerLogTarget(): void
    {
        BaseHelper::setFileLogging('super-table');
    }

}