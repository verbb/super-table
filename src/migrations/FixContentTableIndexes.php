<?php
namespace verbb\supertable\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

class FixContentTableIndexes extends Migration
{
    public function safeUp(): bool
    {
        foreach (Craft::$app->db->schema->getTableNames() as $tableName) {
            if (str_contains($tableName, 'stc_')) {
                MigrationHelper::dropAllForeignKeysOnTable($tableName, $this);
                MigrationHelper::dropAllIndexesOnTable($tableName, $this);

                $this->createIndex(null, $tableName, ['elementId', 'siteId'], true);
                $this->addForeignKey(null, $tableName, ['elementId'], '{{%elements}}', ['id'], 'CASCADE', null);
                $this->addForeignKey(null, $tableName, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}