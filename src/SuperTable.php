<?php
namespace verbb\supertable;

use verbb\supertable\base\PluginTrait;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;

use yii\base\Event;

class SuperTable extends Plugin
{
    // Properties
    // =========================================================================

    public string $schemaVersion = '4.0.0';
    public string $minVersionRequired = '2.7.1';


    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerFieldTypes();
    }


    // Private Methods
    // =========================================================================

    private function _registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = SuperTableField::class;
        });
    }

}
