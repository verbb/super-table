<?php
namespace verbb\supertable\controllers;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\migrations\FixContentTables;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\fields\MissingField;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\services\ProjectConfig;
use craft\web\Controller;

use yii\web\Response;

class PluginController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings(): Response
    {
        $view = $this->getView();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        return $this->renderTemplate('super-table/plugin-settings', [
            'settings' => true,
            'checking' => true,
            'resaving' => true,
        ]);
    }

    public function actionResaveFields(): Response
    {
        // This might take a while
        App::maxPowerCaptain();

        // Backup!
        Craft::$app->getDb()->backup();

        ob_start();

        $db = Craft::$app->getDb();
        $fieldsService = Craft::$app->getFields();
        $superTableService = SuperTable::$plugin->getService();
        $matrixService = Craft::$app->getMatrix();
        $projectConfig = Craft::$app->getProjectConfig();
        $processedParentFields = [];

        $superTableBlockTypes = (new Query())
            ->select(['*'])
            ->from(['{{%supertableblocktypes}}'])
            ->all();

        foreach ($superTableBlockTypes as $superTableBlockType) {
            $missingFields = false;

            if (!isset($superTableBlockType['fieldId'])) {
                echo "    > Super Table field skipped as it's missing its `fieldId`. Block type #{$superTableBlockType['id']} ...\n";

                continue;
            }

            $superTableField = $fieldsService->getFieldById($superTableBlockType['fieldId']);

            if (!isset($superTableBlockType['fieldLayoutId'])) {
                echo "    > Super Table field #{$superTableField->id} skipped as it's missing its `fieldLayoutId`. Block type #{$superTableBlockType['id']} ...\n";

                continue;
            }

            $fieldLayout = $fieldsService->getLayoutById($superTableBlockType['fieldLayoutId']);

            // Find what the columns should be according to the block type fields
            if ($fieldLayout && $superTableField && $superTableField::class == SuperTableField::class) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    if ($field::class == MissingField::class) {
                        $missingFields = true;
                        break;
                    }
                }
            } else {
                echo "    > Super Table field #{$superTableField->id} skipped as it isn't a Super Table field, it's a " . $superTableField::class . ". Block type #{$superTableBlockType['id']} ...\n";

                continue;
            }

            if ($missingFields) {
                echo "    > Super Table field #{$superTableField->id} skipped as it contains missing fields ...\n";

                continue;
            }

            // Check to see what level this is. Non-top levels fields should have their owner saved
            if ($superTableField->context !== 'global') {
                $parentFieldContext = explode(':', $superTableField->context);

                if ($parentFieldContext[0] == 'matrixBlockType') {
                    $parentBlockTypeUid = $parentFieldContext[1];
                    $parentBlockTypeId = Db::idByUid('{{%matrixblocktypes}}', $parentBlockTypeUid);

                    if (!$parentBlockTypeId) {
                        echo "    > ERROR: Unable to find owner Matrix block ID for #{$parentBlockTypeUid} ...\n";

                        continue;
                    }

                    $blockType = Craft::$app->getMatrix()->getBlockTypeById($parentBlockTypeId);
                    $parentField = $fieldsService->getFieldById($blockType->fieldId);

                    if (in_array($parentField->id, $processedParentFields)) {
                        // echo "    > Owner matrix field #{$parentField->id} skipped as it's already been re-saved ...\n";

                        continue;
                    }

                    // Re-save it
                    $fieldsService->saveField($parentField);

                    $processedParentFields[] = $parentField->id;

                    echo "    > Owner matrix field #{$parentField->id} re-saved ...\n";
                } else {
                    echo "    > Unknown parent context {$superTableField->context} ...\n";
                }

                continue;
            }

            // Re-save it
            $fieldsService->saveField($superTableField);

            echo "    > Super Table field #{$superTableField->id} re-saved ...\n";
        }

        $output = ob_get_clean();

        $output = nl2br($output);

        $output .= '<br>Field re-saving complete.';

        $view = $this->getView();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        return $this->renderTemplate('super-table/plugin-settings', [
            'resaving' => true,
            'resaveOutput' => $output,
        ]);
    }

    public function actionFixContentTables(): Response
    {
        // This might take a while
        App::maxPowerCaptain();

        // Backup!
        Craft::$app->getDb()->backup();

        $migration = new FixContentTables();

        ob_start();

        // Run the main migration
        $migration->manual = true;
        $migration->up();
        $output = ob_get_clean();

        $output = nl2br($output);

        $output .= '<br>Fixes complete.';

        $view = $this->getView();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        return $this->renderTemplate('super-table/plugin-settings', [
            'checking' => true,
            'fixed' => true,
            'output' => $output,
        ]);
    }

    public function actionCheckContentTables(): Response
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

        // Identify tables that are missing the correct index from a missed/failed migration
        foreach (Craft::$app->db->schema->getTableNames() as $tableName) {
            if (str_contains($tableName, 'stc_')) {
                $correctIndexExists = MigrationHelper::doesIndexExist($tableName, ['elementId', 'siteId'], true, Craft::$app->db);
                if (!$correctIndexExists) {
                    echo "    > {$tableName} is missing the correct unique index on elementId and siteId...\n";
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
            if (str_contains($tableName, 'supertablecontent_')) {
                // Does a shortened (correct) table name exist? It really should at this point...
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
            $missingFields = false;

            if (!isset($superTableBlockType['fieldId'])) {
                echo "    > Super Table field skipped as it's missing its `fieldId`. Block type #{$superTableBlockType['id']} ...\n";

                continue;
            }

            $superTableField = $fieldsService->getFieldById($superTableBlockType['fieldId']);

            if (!isset($superTableBlockType['fieldLayoutId'])) {
                echo "    > Super Table field #{$superTableField->id} skipped as it's missing its `fieldLayoutId`. Block type #{$superTableBlockType['id']} ...\n";

                continue;
            }

            $fieldLayout = $fieldsService->getLayoutById($superTableBlockType['fieldLayoutId']);

            if (!$superTableField) {
                $errors = true;
                echo "    > ERROR: Blocktype field #{$superTableBlockType['fieldId']} not found ...\n";
                continue;
            }

            if ($superTableField::class !== SuperTableField::class) {
                $errors = true;
                echo "    > ERROR: Blocktype field #{$superTableBlockType['fieldId']} is not a Super Table field ...\n";
                continue;
            }

            // Find what the columns should be according to the block type fields
            if ($fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    if ($field::hasContentColumn()) {
                        $correctFieldColumns[] = 'field_' . $field->handle;
                    }

                    if ($field::class == MissingField::class) {
                        $missingFields = true;
                        echo "    > ERROR: Unable to update {$superTableField->contentTable} as it contains missing fields. Manually fix field #{$superTableField->id} and its missing fields ...\n";
                        break;
                    }
                }
            }

            // If there are any missing fields, we have to quit right now, otherwise we'll mess up
            // the content table, as we just don't know enough about the content table structure
            // to reliably update it properly.
            if ($missingFields) {
                continue;
            }

            if ($superTableField) {
                $contentTable = $superTableField->contentTable;

                if ($contentTable) {
                    $contentTableSchema = Craft::$app->getDb()->getTableSchema($contentTable);

                    if ($contentTableSchema) {
                        $columns = $contentTableSchema->columns;

                        foreach ($columns as $key => $column) {
                            if (str_contains($key, 'field_')) {
                                $dbFieldColumns[] = $key;
                            }
                        }

                        // Sort items the same - just in case they're in a slightly different order, but all there
                        sort($correctFieldColumns);
                        sort($dbFieldColumns);

                        if ($correctFieldColumns != $dbFieldColumns) {
                            $errors = true;
                            echo "    > ERROR: {$contentTable} has missing field columns ...\n";
                        }
                    } else {
                        $errors = true;
                        echo "    > ERROR: {$contentTable} database table does not exist ...\n";
                    }
                }
            }
        }

        // Check for project config inconsistencies
        $superTableFields = (new Query())
            ->select(['id', 'uid', 'settings', 'context'])
            ->from([Table::FIELDS])
            ->where(['type' => SuperTableField::class])
            ->all();

        foreach ($superTableFields as $superTableField) {
            $parentFieldContext = explode(':', $superTableField['context']);

            if ($superTableField['context'] === 'global') {
                $path = ProjectConfig::PATH_FIELDS . '.' . $superTableField['uid'] . '.settings';
            } else if ($parentFieldContext[0] == 'matrixBlockType') {
                $path = ProjectConfig::PATH_MATRIX_BLOCK_TYPES . '.' . $parentFieldContext[1] . '.' . ProjectConfig::PATH_FIELDS . '.' . $superTableField['uid'] . '.settings';
            }

            $settings = Json::decode($superTableField['settings']);
            $configSettings = $projectConfig->get($path);
            $configSettings = ProjectConfigHelper::unpackAssociativeArrays($configSettings);

            if ($settings != $configSettings) {
                $errors = true;
                echo "    > ERROR: #{$superTableField['id']} has inconsistent field settings in its project config ...\n";
            }
        }

        $output = ob_get_clean();

        $output = nl2br($output);

        if (!$errors) {
            $output .= 'No errors found.';
        }

        $view = $this->getView();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        return $this->renderTemplate('super-table/plugin-settings', [
            'checking' => true,
            'checkErrors' => $errors,
            'output' => $output,
        ]);
    }

}
