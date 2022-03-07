<?php
namespace verbb\supertable;

use verbb\supertable\base\PluginTrait;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\helpers\ProjectConfigData;
use verbb\supertable\models\SuperTableBlockTypeModel;
use verbb\supertable\services\SuperTableService;
use verbb\supertable\variables\SuperTableVariable;

use Craft;
use craft\base\Plugin;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\ProjectConfig;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

use NerdsAndCompany\Schematic\Schematic;
use NerdsAndCompany\Schematic\Events\ConverterEvent;

use barrelstrength\sproutbase\app\import\services\Importers;

use craft\gatsbyhelper\events\RegisterIgnoredTypesEvent;
use craft\gatsbyhelper\services\Deltas;

use yii\base\Event;

class SuperTable extends Plugin
{
    // Properties
    // =========================================================================

    public string $schemaVersion = '2.2.1';
    public bool $hasCpSettings = true;

    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_setPluginComponents();
        $this->_setLogging();
        $this->_registerCpRoutes();
        $this->_registerVariables();
        $this->_registerFieldTypes();
        $this->_registerElementTypes();
        $this->_registerIntegrations();
        $this->_registerProjectConfigEventListeners();
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('super-table/settings'));
    }


    // Private Methods
    // =========================================================================

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'super-table/settings' => 'super-table/plugin/settings',
            ]);
        });
    }


    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event): {
            $event->sender->set('superTable', SuperTableVariable::class);
        });
    }

    private function _registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = SuperTableField::class;
        });
    }

    private function _registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = SuperTableBlockElement::class;
        });
    }

    private function _registerProjectConfigEventListeners(): void
    {
        Craft::$app->projectConfig
            ->onAdd(SuperTableService::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleChangedBlockType'])
            ->onUpdate(SuperTableService::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleChangedBlockType'])
            ->onRemove(SuperTableService::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleDeletedBlockType']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function (RebuildConfigEvent $event) {
            $event->config['superTableBlockTypes'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    private function _registerIntegrations(): void
    {
        // Support for Schematic - https://github.com/nerds-and-company/schematic
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

        // Support for Sprout Import - https://github.com/barrelstrength/craft-sprout-import
        if (class_exists(Importers::class)) {
            Event::on(Importers::class, Importers::EVENT_REGISTER_IMPORTER_TYPES, function (RegisterComponentTypesEvent $event) {
                $event->types[] = 'verbb\supertable\integrations\sproutimport\importers\fields\SuperTableImporter';
            });
        }

        // Support for Gatsby Helper
        if (class_exists(Deltas::class)) {
            Event::on(Deltas::class, Deltas::EVENT_REGISTER_IGNORED_TYPES, function(RegisterIgnoredTypesEvent $event) {
              $event->types[] = SuperTableBlockElement::class;
          });
        }
    }

}
