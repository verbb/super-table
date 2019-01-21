<?php
namespace verbb\supertable\migrations;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\MatrixField;
use craft\helpers\Db;
use craft\helpers\Json;

class m190120_000000_fix_supertablecontent_tables extends Migration
{
    public function safeUp()
    {
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

                if (!$this->db->tableExists($contentTable)) {
                    // Re-save field
                    $superTableService->saveSettings($fieldsService->getFieldById($field['id']));
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
            }
        }
    }

    public function safeDown()
    {
        echo "m190120_000000_fix_supertablecontent_tables cannot be reverted.\n";
        return false;
    }

    private function _updateMatrixOrSuperTableSettings($field)
    {
        $superTableService = SuperTable::$plugin->getService();
        $matrixService = Craft::$app->getMatrix();
        
        if (!$field) {
            return;
        }

        if (get_class($field) === SuperTableField::class) {
            $superTableService->saveSettings($field);
        }

        if (get_class($field) === MatrixField::class) {
            $matrixService->saveSettings($field);
        }
    }
}