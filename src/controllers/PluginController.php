<?php
namespace verbb\supertable\controllers;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\migrations\m190120_000000_fix_supertablecontent_tables;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\MatrixField;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\web\Controller;

class PluginController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings()
    {
        return $this->renderTemplate('super-table/plugin-settings', [
            'settings' => true,
        ]);
    }

    public function actionFixContentTables()
    {
        // Backup!
        Craft::$app->getDb()->backup();

        $migration = new m190120_000000_fix_supertablecontent_tables();

        ob_start();

        // Run the main migration
        $migration->up();
        $output = ob_get_contents();

        ob_end_clean();

        $output = nl2br($output);

        $output .= '<br>Fixes complete.';

        return $this->renderTemplate('super-table/plugin-settings', [
            'fixed' => true,
            'output' => $output,
        ]);
    }

    public function actionCheckContentTables()
    {
        ob_start();

        $errors = false;

        $db = Craft::$app->getDb();
        $fieldsService = Craft::$app->getFields();
        $superTableService = SuperTable::$plugin->getService();
        $matrixService = Craft::$app->getMatrix();
        $projectConfig = Craft::$app->getProjectConfig();

        // Find all top-level Super Table fields and make sure their content table exists
        $superTableFields = (new Query())
            ->select(['*'])
            ->from([Table::FIELDS])
            ->where(['type' => SuperTableField::class, 'context' => 'global'])
            ->all();

        foreach ($superTableFields as $field) {
            $settings = Json::decode($field['settings']);

            if (is_array($settings) && array_key_exists('contentTable', $settings)) {
                $contentTable = $settings['contentTable'];

                if (!$contentTable) {
                    $errors = true;
                    echo "    > ERROR: Super Table field #{$field['id']} empty content table {$contentTable} ...\n";
                } else if (!$db->tableExists($contentTable)) {
                    $errors = true;
                    echo "    > ERROR: Super Table field #{$field['id']} missing content table {$contentTable} ...\n";
                }
            } else {
                $errors = true;
                echo "    > ERROR: Super Table field #{$field['id']} is missing its content table in settings ...\n";
            }
        }

        // Find all nested Super Tables - find their parent field and update
        $superTableFields = (new Query())
            ->select(['*'])
            ->from([Table::FIELDS])
            ->where(['type' => SuperTableField::class])
            ->andWhere(['!=', 'context', 'global'])
            ->all();

        foreach ($superTableFields as $field) {
            $settings = Json::decode($field['settings']);

            if (is_array($settings) && array_key_exists('contentTable', $settings)) {
                $contentTable = $settings['contentTable'];

                if (!$contentTable) {
                    $errors = true;
                    echo "    > ERROR: Super Table field #{$field['id']} has content table {$contentTable} ...\n";
                } else if (!$db->tableExists($contentTable)) {
                    $errors = true;
                    echo "    > ERROR: Super Table field #{$field['id']} missing content table {$contentTable} ...\n";
                }
            } else {
                $errors = true;
                echo "    > ERROR: Super Table field #{$field['id']} is missing its content table in settings ...\n";
            }
        }

        // Also, check to see if there are any content tables mistakenly using the uid of matrix fields (happened during)
        // early Craft 3.1 upgraders.
        foreach ($superTableFields as $field) {
            $parentFieldContext = explode(':', $field['context']);

            if ($parentFieldContext[0] == 'matrixBlockType') {
                $parentFieldUid = $parentFieldContext[1];

                $wrongContentTable = '{{%stc_' . $parentFieldUid . '_' . strtolower($field['handle'] . '}}');

                // This shouldn't exist - it was mistakenly created in the Craft 3.1 update
                if ($db->tableExists($wrongContentTable)) {
                    $errors = true;
                    echo "    > ERROR: Incorrect nested content table found {$wrongContentTable} ...\n";
                }
            }
        }

        // Find any `supertablecontents_*` tables, these should be `stc_*`. But we should check if these tables are completely empty
        foreach (Craft::$app->db->schema->getTableNames() as $tableName) {
            if (strstr($tableName, 'supertablecontent_')) {
                // Does a shortned (correct) table name exist? It really should at this point...
                $newTableName = str_replace('supertablecontent_', 'stc_', $tableName);

                if (!$db->tableExists($newTableName)) {
                    $errors = true;
                    echo "    > ERROR: Shortened table not found. {$tableName} should be {$newTableName} ...\n";
                } else {
                    // The shortened table exists, but its empty - not great!
                    $oldCount = (new Query())->select(['*'])->from([$tableName])->count();
                    $newCount = (new Query())->select(['*'])->from([$newTableName])->count();

                    if ($oldCount !== $newCount) {
                        // Its more serious if there's no rows in the new one, but some rows in the other
                        if ($newCount === '0') {
                            $errors = true;
                            echo "    > ERROR: Shortened table {$newTableName} has {$newCount} rows. Old table {$tableName} has {$oldCount} rows ...\n";
                        } else {
                            // Flag this as a warning
                            $errors = true;
                            echo "    > WARNING: Shortened table {$newTableName} has {$newCount} rows. Old table {$tableName} has {$oldCount} rows ...\n";
                        }
                    }
                }
            }
        }

        // Check each table for missing field columns in their content tables
        $superTableBlockTypes = (new Query())
            ->select(['*'])
            ->from(['{{%supertableblocktypes}}'])
            ->all();

        foreach ($superTableBlockTypes as $superTableBlockType) {
            $correctFieldColumns = [];
            $dbFieldColumns = [];

            $fieldLayout = $fieldsService->getLayoutById($superTableBlockType['fieldLayoutId']);

            // Find what the columns should be according to the block type fields
            if ($fieldLayout) {
                foreach ($fieldLayout->getFields() as $field) {
                    if ($field::hasContentColumn()) {
                        $correctFieldColumns[] = 'field_' . $field->handle;
                    }
                }
            }

            $field = $fieldsService->getFieldById($superTableBlockType['fieldId']);

            if ($field) {
                $contentTable = $field->contentTable;

                if ($contentTable) {
                    $columns = Craft::$app->getDb()->getTableSchema($contentTable)->columns;

                    foreach ($columns as $key => $column) {
                        if (strstr($key, 'field_')) {
                            $dbFieldColumns[] = $key;
                        }
                    }

                    if ($correctFieldColumns != $dbFieldColumns) {
                        $errors = true;
                        echo "    > ERROR: {$contentTable} has missing field columns ...\n";
                    }
                }
            }
        }

        // Check for project config inconsistencies
        $superTableFields = (new Query())
            ->select(['id', 'uid', 'settings'])
            ->from([Table::FIELDS])
            ->where(['type' => SuperTableField::class])
            ->all();

        foreach ($superTableFields as $superTableField) {
            $path = Fields::CONFIG_FIELDS_KEY . '.' . $superTableField['uid'] . '.settings';
            $settings = Json::decode($superTableField['settings']);
            $configSettings = $projectConfig->get($path);

            if ($settings != $configSettings) {
                $errors = true;
                echo "    > ERROR: #{$superTableField['id']} has inconsistent field settings in its project config ...\n";
            }
        }

        $output = ob_get_contents();
        ob_end_clean();

        $output = nl2br($output);

        if (!$errors) {
            $output .= 'No errors found.';
        }

        return $this->renderTemplate('super-table/plugin-settings', [
            'checkErrors' => $errors,
            'output' => $output,
        ]);
    }

}