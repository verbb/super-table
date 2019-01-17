<?php
namespace verbb\supertable\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\services\ProjectConfig;

use yii\db\Expression;

class m190117_000002_initial_yml_and_snapshot extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (Craft::$app->getConfig()->getGeneral()->useProjectConfigFile) {
            $configDir = Craft::$app->getPath()->getConfigPath();
            $configFile = $configDir . '/' . ProjectConfig::CONFIG_FILENAME;
            
            if (file_exists($configFile)) {
                $backupFile = ProjectConfig::CONFIG_FILENAME . '.' . StringHelper::randomString(10);
                echo "    > renaming project.yaml to {$backupFile} ... ";
                rename($configFile, $configDir . '/' . $backupFile);
            }
        }
        
        $configData = $this->_getProjectConfigData();
        $projectConfig = Craft::$app->getProjectConfig();
        
        foreach ($configData as $path => $value) {
            $projectConfig->set($path, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190117_000002_initial_yml_and_snapshot cannot be reverted.\n";

        return false;
    }

    private function _getProjectConfigData(): array
    {
        return [
            'superTableBlockTypes' => $this->_getSuperTableBlockTypeData(),
        ];
    }

    private function _getSuperTableBlockTypeData(): array
    {
        $data = [];
        $superTableBlockTypes = (new Query())
            ->select([
                'bt.fieldId',
                'bt.fieldLayoutId',
                'bt.uid',
                'f.uid AS field',
            ])
            ->from(['{{%supertableblocktypes}} bt'])
            ->innerJoin('{{%fields}} f', '[[bt.fieldId]] = [[f.id]]')
            ->all();

        $layoutIds = [];
        $blockTypeData = [];
        
        foreach ($superTableBlockTypes as $superTableBlockType) {
            $fieldId = $superTableBlockType['fieldId'];
            unset($superTableBlockType['fieldId']);
            $layoutIds[] = $superTableBlockType['fieldLayoutId'];
            $blockTypeData[$fieldId][$superTableBlockType['uid']] = $superTableBlockType;
        }
        
        $superTableFieldLayouts = $this->_generateFieldLayoutArray($layoutIds);
        
        foreach ($blockTypeData as &$blockTypes) {
            foreach ($blockTypes as &$blockType) {
                $blockTypeUid = $blockType['uid'];
                $layout = $superTableFieldLayouts[$blockType['fieldLayoutId']];
                unset($blockType['uid'], $blockType['fieldLayoutId']);
                $blockType['fieldLayouts'] = [$layout['uid'] => ['tabs' => $layout['tabs']]];
                $data[$blockTypeUid] = $blockType;
            }
        }
        
        return $data;
    }

    private function _generateFieldLayoutArray(array $layoutIds): array
    {
        // Get all the UIDs
        $fieldLayoutUids = (new Query())
            ->select(['id', 'uid'])
            ->from([Table::FIELDLAYOUTS])
            ->where(['id' => $layoutIds])
            ->pairs();

        $fieldLayouts = [];

        foreach ($fieldLayoutUids as $id => $uid) {
            $fieldLayouts[$id] = [
                'uid' => $uid,
                'tabs' => [],
            ];
        }

        // Get the tabs and fields
        $fieldRows = (new Query())
            ->select([
                'fields.handle',
                'fields.uid AS fieldUid',
                'layoutFields.fieldId',
                'layoutFields.required',
                'layoutFields.sortOrder AS fieldOrder',
                'tabs.id AS tabId',
                'tabs.name as tabName',
                'tabs.sortOrder AS tabOrder',
                'tabs.uid AS tabUid',
                'layouts.id AS layoutId',
            ])
            ->from(['{{%fieldlayoutfields}} AS layoutFields'])
            ->innerJoin('{{%fieldlayouttabs}} AS tabs', '[[layoutFields.tabId]] = [[tabs.id]]')
            ->innerJoin('{{%fieldlayouts}} AS layouts', '[[layoutFields.layoutId]] = [[layouts.id]]')
            ->innerJoin('{{%fields}} AS fields', '[[layoutFields.fieldId]] = [[fields.id]]')
            ->where(['layouts.id' => $layoutIds])
            ->orderBy(['tabs.sortOrder' => SORT_ASC, 'layoutFields.sortOrder' => SORT_ASC])
            ->all();

        foreach ($fieldRows as $fieldRow) {
            $layout = &$fieldLayouts[$fieldRow['layoutId']];
            
            if (empty($layout['tabs'][$fieldRow['tabUid']])) {
                $layout['tabs'][$fieldRow['tabUid']] =
                    [
                        'name' => $fieldRow['tabName'],
                        'sortOrder' => $fieldRow['tabOrder'],
                    ];
            }

            $tab = &$layout['tabs'][$fieldRow['tabUid']];
            $field['required'] = $fieldRow['required'];
            $field['sortOrder'] = $fieldRow['fieldOrder'];
            $tab['fields'][$fieldRow['fieldUid']] = $field;
        }

        // Get rid of UIDs
        foreach ($fieldLayouts as &$fieldLayout) {
            $fieldLayout['tabs'] = array_values($fieldLayout['tabs']);
        }

        return $fieldLayouts;
    }

}
