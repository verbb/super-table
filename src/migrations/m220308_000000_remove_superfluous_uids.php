<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;

class m220308_000000_remove_superfluous_uids extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%supertableblocks}}', 'uid')) {
            $this->dropColumn('{{%supertableblocks}}', 'uid');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220308_000000_remove_superfluous_uids cannot be reverted.\n";
        return false;
    }
}
