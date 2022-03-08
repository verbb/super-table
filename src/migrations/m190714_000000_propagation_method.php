<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

class m190714_000000_propagation_method extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%supertableblocks}}', 'ownerSiteId')) {
            MigrationHelper::dropForeignKeyIfExists('{{%supertableblocks}}', ['ownerSiteId'], $this);
            MigrationHelper::dropIndexIfExists('{{%supertableblocks}}', ['ownerSiteId'], false, $this);
            $this->dropColumn('{{%supertableblocks}}', 'ownerSiteId');
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m190714_000000_propagation_method cannot be reverted.\n";
        return false;
    }
}