<?php
namespace verbb\supertable\migrations;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\MatrixField;
use craft\fields\MissingField;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\services\Fields;
use craft\services\Matrix;

class m190120_000000_fix_supertablecontent_tables extends Migration
{
    public $manual = false;

    public function safeUp()
    {
        $fieldsService = Craft::$app->getFields();
        $superTableService = SuperTable::$plugin->getService();
        $matrixService = Craft::$app->getMatrix();
        $projectConfig = Craft::$app->getProjectConfig();

        // Find any `supertablecontents_*` tables, these should be `stc_*`. But we should check if these tables are completely empty
        foreach (Craft::$app->db->schema->getTableNames() as $tableName) {
            if (strstr($tableName, 'supertablecontent_')) {
                // Does a shortned (correct) table name exist? It really should at this point...
                $newTableName = str_replace('supertablecontent_', 'stc_', $tableName);

                if ($this->db->tableExists($newTableName)) {
                    // The shortened table exists, but its empty - not great!
                    $oldCount = (new Query())->select(['*'])->from([$tableName])->count();
                    $newCount = (new Query())->select(['*'])->from([$newTableName])->count();

                    if ($oldCount !== $newCount && $newCount === '0') {
                        // Remove the new (empty) table and rename the old one.
                        $this->dropTableIfExists($newTableName);
                        MigrationHelper::renameTable($tableName, $newTableName, null);

                        echo "    > Removed empty table {$newTableName}, re-created from {$tableName} ...\n";
                    }
                }
            }
        }

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

                echo "    > Super Table field #{$field['id']} has content table {$contentTable} ...\n";

                if ($contentTable) {
                    if (!$this->db->tableExists($contentTable)) {
                        $this->_createContentTable($settings, $field);
                        // Re-save field
                        // $superTableService->saveSettings($fieldsService->getFieldById($field['id']));
                    }
                } else {
                    $this->_createContentTable($settings, $field);
                }
            } else {
                // We've updated from an older ST version
                $this->_createContentTable($settings, $field);
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

                echo "    > Super Table field #{$field['id']} has content table {$contentTable} ...\n";

                if ($contentTable) {
                    if (!$this->db->tableExists($contentTable)) {
                        // Check to see if our Craft 3 bug is true - the content table isn't stored on the field correctly
                        // missing its Matrix ID prefix - ie, stored as `stc_relatedwork` not `stc_19_relatedwork`.
                        $parentFieldContext = explode(':', $field['context']);

                        if ($parentFieldContext[0] == 'matrixBlockType') {
                            $parentFieldUid = $parentFieldContext[1];
                            $parentFieldId = Db::idByUid(Table::MATRIXBLOCKTYPES, $parentFieldUid);

                            // Stitch the Matrix ID into the table name
                            if ($parentFieldId) {
                                $newContentTable = explode('_', $contentTable);
                                array_splice($newContentTable, 1, 0, $parentFieldId);
                                $newContentTable = implode('_', $newContentTable);

                                // Is there a table that correctly already has the parent Matrix ID? 
                                if ($this->db->tableExists($newContentTable)) {
                                    // We need to update the field to reflect the already-existing table
                                    $settings['contentTable'] = $newContentTable;

                                    $this->update(Table::FIELDS, ['settings' => Json::encode($settings)], ['id' => $field['id']]);
                                } else {
                                    // Otherwise, something's gone wrong somewhere down the line, and this table doesn't
                                    // exist at all. Save the top-level field (Matrix) to trigger the process
                                    $matrixFieldId = (new Query())
                                        ->select(['fieldId'])
                                        ->from([Table::MATRIXBLOCKTYPES])
                                        ->where(['id' => $parentFieldId])
                                        ->scalar();

                                    if ($matrixFieldId) {
                                        // Check for any shenanigans from things like Neo...
                                        $this->_updateMatrixOrSuperTableSettings($fieldsService->getFieldById($matrixFieldId));
                                    }

                                    // And also re-save the Super Table field
                                    $this->_updateMatrixOrSuperTableSettings($fieldsService->getFieldById($field['id']));
                                }
                            }
                        }
                    }
                } else {
                    $this->_createContentTable($settings, $field);
                }
            } else {
                // We've updated from an older ST version
                $this->_createContentTable($settings, $field);
            }
        }

        // Also, check to see if there are any content tables mistakenly using the uid of matrix fields (happened during)
        // early Craft 3.1 upgraders.
        foreach ($superTableFields as $field) {
            $parentFieldContext = explode(':', $field['context']);

            if ($parentFieldContext[0] == 'matrixBlockType') {
                $parentFieldUid = $parentFieldContext[1];

                $wrongContentTable = '{{%stc_' . $parentFieldUid . '_' . strtolower($field['handle'] . '}}');

                if ($this->db->tableExists($wrongContentTable)) {
                    echo "    > Incorrect nested content table found {$wrongContentTable} ...\n";

                    $newField = $fieldsService->getFieldById($field['id']);
                    $contentTable = $this->_getContentTableName($newField);

                    // Rename the table (check if it already exists)
                    if ($this->db->tableExists($contentTable)) {
                        echo "    > {$contentTable} already exists, no need to rename ...\n";
                        $this->dropTableIfExists($wrongContentTable);
                        echo "    > Deleted content table to {$wrongContentTable} ...\n";
                    } else {
                        MigrationHelper::renameTable($wrongContentTable, $contentTable, $this);
                        echo "    > Renamed content table to {$contentTable} ...\n";
                    }

                    // And also update the field settings
                    $settings = Json::decode($field['settings']);
                    $settings['contentTable'] = $contentTable;
                    $this->update(Table::FIELDS, ['settings' => Json::encode($settings)], ['id' => $field['id']]);

                    echo "    > Updated field settings with content table {$contentTable} ...\n";
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

            $superTableField = $fieldsService->getFieldById($superTableBlockType['fieldId']);
            $fieldLayout = $fieldsService->getLayoutById($superTableBlockType['fieldLayoutId']);

            if (!$superTableField) {
                echo "    > ERROR: Blocktype field #{$superTableBlockType['fieldId']} not found ...\n";
                continue;
            }

            if (get_class($superTableField) !== SuperTableField::class) {
                echo "    > ERROR: Blocktype field #{$superTableBlockType['fieldId']} is not a Super Table field ...\n";
                continue;
            }

            // Find what the columns should be according to the block type fields
            if ($fieldLayout) {
                foreach ($fieldLayout->getFields() as $field) {
                    if ($field::hasContentColumn()) {
                        $correctFieldColumns[] = 'field_' . $field->handle;
                    }

                    if (get_class($field) == MissingField::class) {
                        $missingFields = true;
                        echo "    > ERROR: Unable to update {$superTableField->contentTable} as it contains missing fields. Manually fix field #{$superTableBlockType['fieldId']} and its missing fields ...\n";
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
                    $columns = $this->db->getTableSchema($contentTable)->columns;

                    foreach ($columns as $key => $column) {
                        if (strstr($key, 'field_')) {
                            $dbFieldColumns[] = $key;
                        }
                    }

                    // Sort items the same - just in case they're in a slightly different order, but all there
                    sort($correctFieldColumns);
                    sort($dbFieldColumns);

                    if ($correctFieldColumns != $dbFieldColumns) {
                        $fieldsService->saveField($superTableField);

                        echo "    > Content table {$contentTable} field columns have been corrected ...\n";
                    }
                }
            }
        }

        // Update Super Table settings in the project config to match the DB. This is because Craft 3.0 > 3.1 migration
        // has already fired, dumping potentially incorrect values into the project config. Because we've fixed above, 
        // we should be good to update it again with correct values.
        
        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.super-table.schemaVersion', true);
        $allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;

        if (version_compare($schemaVersion, '2.0.10', '<') || ($this->manual && $allowAdminChanges)) {
            $projectConfig->muteEvents = true;

            $superTableFields = (new Query())
                ->select(['id', 'uid', 'settings', 'context'])
                ->from([Table::FIELDS])
                ->where(['type' => SuperTableField::class])
                ->all();

            foreach ($superTableFields as $superTableField) {
                $parentFieldContext = explode(':', $superTableField['context']);

                if ($superTableField['context'] === 'global') {
                    $path = Fields::CONFIG_FIELDS_KEY . '.' . $superTableField['uid'] . '.settings';
                } else if ($parentFieldContext[0] == 'matrixBlockType') {
                    $path = Matrix::CONFIG_BLOCKTYPE_KEY . '.' . $parentFieldContext[1] . '.' . Fields::CONFIG_FIELDS_KEY . '.' . $superTableField['uid'] . '.settings';
                }

                $settings = Json::decode($superTableField['settings']);
                $projectConfig->set($path, $settings);
            }

            $projectConfig->muteEvents = false;
        }
    }

    public function safeDown()
    {
        echo "m190120_000000_fix_supertablecontent_tables cannot be reverted.\n";
        return false;
    }

    private function _createContentTable($settings, $field)
    {
        $fieldsService = Craft::$app->getFields();
        $superTableService = SuperTable::$plugin->getService();

        $newField = $fieldsService->getFieldById($field['id']);

        echo "    > Trying to create missing content table for #{$newField->id} ...\n";

        if (!$newField) {
            echo "    > Could not match #{$newField->id} ...\n";

            return;
        }

        if (get_class($newField) !== SuperTableField::class) {
            echo "    > Field mismatch " . get_class($newField) . " is not a Super Table field ...\n";

            return;
        }

        // Fetch the table that it should be
        $contentTable = $this->_getContentTableName($newField);

        echo "    > Generated table name {$contentTable} ...\n";

        // Update the field 
        $settings['contentTable'] = $contentTable;
        $this->update(Table::FIELDS, ['settings' => Json::encode($settings)], ['id' => $field['id']]);

        // Create the actual content table
        if (!$this->db->tableExists($contentTable)) {
            $migration = new CreateSuperTableContentTable([
                'tableName' => $contentTable,
            ]);

            $migration->up();

            echo "    > Created table {$contentTable} ...\n\n";
        } else {
            echo "    > Content table {$contentTable} already exists, skipping ...\n\n";

            return;
        }
    }

    private function _getContentTableName(SuperTableField $field): string
    {
        $baseName = 'stc_' . strtolower($field->handle);
        $db = Craft::$app->getDb();

        $parentFieldId = '';

        // Check if this field is inside a Matrix - we need to prefix this content table if so.
        if ($field->context != 'global') {
            $parentFieldContext = explode(':', $field->context);

            if ($parentFieldContext[0] == 'matrixBlockType') {
                $parentFieldUid = $parentFieldContext[1];
                $parentFieldId = Db::idByUid('{{%matrixblocktypes}}', $parentFieldUid);
            }
        }
    
        if ($parentFieldId) {
            $baseName = 'stc_' . $parentFieldId . '_' . strtolower($field->handle);
        }

        $name = '{{%' . $baseName . '}}';

        return $name;
    }

    private function _updateMatrixOrSuperTableSettings($field)
    {
        $superTableService = SuperTable::$plugin->getService();
        $matrixService = Craft::$app->getMatrix();
        
        if (!$field) {
            return;
        }

        if (get_class($field) === SuperTableField::class) {
            echo "    > Re-saving Super Table field #{$field->id} with content table {$field->contentTable} ...\n";

            $superTableService->saveSettings($field);
        }

        if (get_class($field) === MatrixField::class) {
            echo "    > Re-saving Matrix field #{$field->id} with content table {$field->contentTable} ...\n";

            $matrixService->saveSettings($field);
        }
    }
}