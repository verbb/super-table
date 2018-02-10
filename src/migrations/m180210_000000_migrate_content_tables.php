<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;

class m180210_000000_migrate_content_tables extends Migration
{
    public function safeUp()
    {
        // Migrate our Craft 2 content tables from `supertablecontent_matrixId_superTableHandle` to `stc_matrixId_superTableHandle`
        // this will help to alleviate issues with long field handles, and in particular for nested fields. Otherwise, errors
        // are normally thrown due to the overly long index names.
        $fields = (new Query())
            ->select(['id'])
            ->from(['{{%fields}}'])
            ->where(['type' => ['verbb\supertable\fields\SuperTableField', 'SuperTable']])
            ->column();

        if (!empty($fields)) {
            foreach ($fields as $key => $fieldId) {
                $field = Craft::$app->fields->getFieldById($fieldId);

                $newContentTable = SuperTable::$plugin->service->getContentTableName($field);
                $oldContentTable = str_replace('stc_', 'supertablecontent_', $newContentTable);

                if (Craft::$app->db->tableExists($oldContentTable)) {
                    MigrationHelper::renameTable($oldContentTable, $newContentTable);
                }
            }
        }
    }

    public function safeDown()
    {
        echo "m180210_000000_migrate_content_tables cannot be reverted.\n";
        return false;
    }
}
