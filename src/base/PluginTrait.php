<?php
namespace verbb\supertable\base;

use verbb\supertable\SuperTable;
use verbb\supertable\services\SuperTableService;
use verbb\supertable\services\SuperTableMatrixService;

use Craft;
use craft\log\FileTarget;

use yii\log\Logger;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static $plugin;


    // Public Methods
    // =========================================================================

    public function getService()
    {
        return $this->get('service');
    }

    public function getMatrixService()
    {
        return $this->get('matrixService');
    }

    private function _setPluginComponents()
    {
        $this->setComponents([
            'service' => SuperTableService::class,
            'matrixService' => SuperTableMatrixService::class,
        ]);
    }

    private function _setLogging()
    {
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/super-table.log'),
            'categories' => ['super-table'],
        ]);
    }

    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'super-table');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'super-table');
    }

}