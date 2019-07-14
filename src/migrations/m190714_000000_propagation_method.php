<?php
namespace verbb\supertable\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\helpers\MigrationHelper;

class m190714_000000_propagation_method extends Migration
{
    public function safeUp()
    {
        MigrationHelper::dropForeignKeyIfExists('{{%supertableblocks}}', ['ownerSiteId'], $this);
        MigrationHelper::dropIndexIfExists('{{%supertableblocks}}', ['ownerSiteId'], false, $this);
        $this->dropColumn('{{%supertableblocks}}', 'ownerSiteId');
    }

    public function safeDown()
    {
        echo "m190714_000000_propagation_method cannot be reverted.\n";
        return false;
    }
}