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
        $migration->up();
        $output = ob_get_contents();
        ob_end_clean();

        echo nl2br($output);

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
                }

                if (!$db->tableExists($contentTable)) {
                    $errors = true;
                    echo "    > ERROR: Super Table field #{$field['id']} missing content table {$contentTable} ...\n";
                }
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
                }

                if (!$db->tableExists($contentTable)) {
                    $errors = true;
                    echo "    > ERROR: Super Table field #{$field['id']} missing content table {$contentTable} ...\n";
                }
            }
        }

        // Also, check to see if there are any content tables mistakenly using the uid of matrix fields (happened during)
        // early Craft 3.1 upgraders.
        foreach ($superTableFields as $field) {
            if ($field['context'] != 'global') {
                $parentFieldContext = explode(':', $field['context']);

                if ($parentFieldContext[0] == 'matrixBlockType') {
                    $parentFieldUid = $parentFieldContext[1];

                    $wrongContentTable = '{{%stc_' . $parentFieldUid . '_' . strtolower($field['handle'] . '}}');

                    if ($db->tableExists($wrongContentTable)) {
                        $errors = true;
                        echo "    > ERROR: Incorrect nested content table found {$wrongContentTable} ...\n";
                    }
                }
            }
        }

        $output = ob_get_contents();
        ob_end_clean();

        echo nl2br($output);

        if ($errors) {
            echo '<br>Fix the above errors by running the <a href="' . UrlHelper::actionUrl('super-table/plugin/fix-content-tables') . '">Super Table content table fixer.';
        }

        exit;
    }

}