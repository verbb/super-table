<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;

class m190117_000000_soft_deletes extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%supertableblocks}}', 'deletedWithOwner')) {
            $this->addColumn('{{%supertableblocks}}', 'deletedWithOwner', $this->boolean()->null()->after('sortOrder'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190117_000000_soft_deletes cannot be reverted.\n";

        return false;
    }
}
