<?php
namespace verbb\supertable;

use craft\services\Gc;
use verbb\supertable\base\PluginTrait;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\helpers\ProjectConfigData;
use verbb\supertable\records\SuperTableBlock;
use verbb\supertable\services\Service;
use verbb\supertable\variables\SuperTableVariable;
use verbb\supertable\migrations\Install;

use Craft;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\console\Controller as ConsoleController;
use craft\console\controllers\ResaveController;
use craft\events\DefineConsoleActionsEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\ProjectConfig;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

use craft\gatsbyhelper\events\RegisterIgnoredTypesEvent;
use craft\gatsbyhelper\services\Deltas;

use craft\fixfks\controllers\RestoreController;

use yii\base\Event;

class SuperTable extends Plugin
{
    // Properties
    // =========================================================================

    public bool $hasCpSettings = true;
    public string $schemaVersion = '3.0.0';
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

        $this->_registerComponents();
        $this->_registerLogTarget();
        $this->_registerVariables();
        $this->_registerFieldTypes();
        $this->_registerElementTypes();
        $this->_registerIntegrations();
        $this->_registerProjectConfigEventListeners();
        $this->_registerGarbageCollection();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
        }

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->_registerResaveCommand();
        }
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
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
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
            ->onAdd(Service::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleChangedBlockType'])
            ->onUpdate(Service::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleChangedBlockType'])
            ->onRemove(Service::CONFIG_BLOCKTYPE_KEY . '.{uid}', [$this->getService(), 'handleDeletedBlockType']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $event->config['superTableBlockTypes'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    private function _registerIntegrations(): void
    {
        // Support for Gatsby Helper
        if (class_exists(Deltas::class)) {
            Event::on(Deltas::class, Deltas::EVENT_REGISTER_IGNORED_TYPES, function(RegisterIgnoredTypesEvent $event) {
                $event->types[] = SuperTableBlockElement::class;
            });
        }

        // Support Fix Fks - https://github.com/craftcms/fix-fks
        if (class_exists(RestoreController::class)) {
            Event::on(RestoreController::class, RestoreController::EVENT_AFTER_RESTORE_FKS, function(Event $event) {
                (new Install)->addForeignKeys();

            });
        }
    }

    private function _registerResaveCommand(): void
    {
        if (!Craft::$app instanceof ConsoleApplication) {
            return;
        }

        Event::on(ResaveController::class, ConsoleController::EVENT_DEFINE_ACTIONS, function(DefineConsoleActionsEvent $event) {
            $event->actions['supertable-blocks'] = [
                'action' => function(): int {
                    $controller = Craft::$app->controller;

                    $criteria = [];
                    if ($controller->field) {
                        $criteria['field'] = explode(',', $controller->field);
                    }
                    if ($controller->ownerId) {
                        $criteria['ownerId'] = array_map(fn(string $id) => (int)$id, explode(',', (string)$controller->ownerId));
                    }
                    if ($controller->type) {
                        $criteria['type'] = explode(',', $controller->type);
                    }

                    return $controller->resaveElements(SuperTableBlockElement::class, $criteria);
                },
                'options' => [
                    'field',
                    'ownerId',
                    'type',
                ],
                'helpSummary' => 'Re-saves Super Table blocks.',
            ];
        });
    }

    /**
     * Register the things that need to be garbage collected
     */
    private function _registerGarbageCollection(): void
    {
        Event::on(Gc::class, Gc::EVENT_RUN, function(Event $event) {
            // Delete partial elements
            /** @var Gc $gc */
            $gc = $event->sender;
            $gc->deletePartialElements(SuperTableBlockElement::class, SuperTableBlock::tableName(), 'id');
        });
    }

}
