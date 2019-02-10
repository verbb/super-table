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
use craft\web\Controller;

class PluginController extends Controller
{
    // Public Methods
    // =========================================================================

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

        echo nl2br($output);

        echo '<br>Fixes complete.';

        exit;
    }

    public function actionCheckContentTables()
    {
        ob_start();

        $errors = false;

        $db = Craft::$app->getDb();
        $fieldsService = Craft::$app->getFields();
        $superTableService = SuperTable::$plugin->getService();
        $matrixService = Craft::$app->getMatrix();

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

        $output = ob_get_contents();
        ob_end_clean();

        echo nl2br($output);

        if ($errors) {
            echo '<br>Fix the above errors by running the <a href="' . UrlHelper::actionUrl('super-table/plugin/fix-content-tables') . '">Super Table content table fixer.';
        } else {
            echo 'No errors found.';
        }

        exit;
    }

}