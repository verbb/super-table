<?php
namespace verbb\supertable;

use verbb\supertable\fields\SuperTableField;
use verbb\supertable\models\SuperTableBlockTypeModel;
use verbb\supertable\services\SuperTableService;
use verbb\supertable\services\SuperTableMatrixService;
use verbb\supertable\variables\SuperTableVariable;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;

use NerdsAndCompany\Schematic\Schematic;
use NerdsAndCompany\Schematic\Events\ConverterEvent;

use barrelstrength\sproutbase\app\import\services\Importers;

use yii\base\Event;

class SuperTable extends Plugin
{
    // Static Properties
    // =========================================================================

    public static $plugin;


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        // Register Components (Services)
        $this->setComponents([
            'service' => SuperTableService::class,
            'matrixService' => SuperTableMatrixService::class,
        ]);

        Event::on(Fields::className(), Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = SuperTableField::class;
        });

        if (class_exists(Schematic::class)) {
            Event::on(Schematic::class, Schematic::EVENT_RESOLVE_CONVERTER, function (ConverterEvent $event) {
                if ($event->modelClass == SuperTableField::class) {
                    $event->converterClass = 'verbb\supertable\integrations\schematic\converters\fields\SuperTableSchematic';
                }

                if ($event->modelClass == SuperTableBlockTypeModel::class) {
                    $event->converterClass = 'verbb\supertable\integrations\schematic\converters\models\SuperTableBlockTypeSchematic';
                }
            });
        }

        if (class_exists(Importers::class)) {
            Event::on(Importers::class, Importers::EVENT_REGISTER_IMPORTER_TYPES, function (RegisterComponentTypesEvent $event) {
                $event->types[] = 'verbb\supertable\integrations\sproutimport\importers\fields\SuperTableImporter';
            });
        }

        // Setup Variables class (for backwards compatibility)
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function (Event $event) {
            $variable = $event->sender;
            $variable->set('superTable', SuperTableVariable::class);
        });
    }
}
