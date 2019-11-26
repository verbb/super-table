<?php
namespace verbb\supertable\migrations;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\MigrationHelper;
use craft\services\Fields;

class m191127_000000_fix_width extends Migration
{
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.super-table.schemaVersion', true);

        if (version_compare($schemaVersion, '2.2.1', '>=')) {
            return;
        }

        $projectConfig->muteEvents = true;

        // Find all Super Table fields
        $superTableFields = (new Query())
            ->select(['id', 'uid', 'settings', 'context'])
            ->from([Table::FIELDS])
            ->where(['type' => SuperTableField::class])
            ->all();

        // Get all field info for performance
        $allFields = (new Query())
            ->select(['id', 'uid'])
            ->from([Table::FIELDS])
            ->indexBy('id')
            ->all();

        foreach ($superTableFields as $superTableField) {
            $path = Fields::CONFIG_FIELDS_KEY . '.' . $superTableField['uid'] . '.settings.columns';
            $columns = $projectConfig->get($path);

            // We need to update from using the field's ID to the field's UID for settings like width
            if ($columns) {
                $fixedColumns = [];

                foreach ($columns as $fieldId => $column) {
                    $field = $allFields[$fieldId] ?? null;

                    if ($field) {
                        $fixedColumns[$field['uid']] = $column;
                    }
                }

                if ($fixedColumns) {
                    $projectConfig->set($path, $fixedColumns);
                }
            }
        }

        $projectConfig->muteEvents = false;
        
        return true;
    }

    public function safeDown()
    {
        echo "m191127_000000_fix_width cannot be reverted.\n";
        return false;
    }
}