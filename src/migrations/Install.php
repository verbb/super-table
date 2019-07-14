<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp()
    {
        if (!$this->db->tableExists('{{%supertableblocks}}')) {
            $this->createTables();
            $this->createIndexes();
            $this->addForeignKeys();
        }

        return true;
    }

    public function safeDown()
    {
        return true;
    }

    // Protected Methods
    // =========================================================================

    protected function createTables()
    {
        $this->createTable('{{%supertableblocks}}', [
            'id' => $this->integer()->notNull(),
            'ownerId' => $this->integer()->notNull(),
            'fieldId' => $this->integer()->notNull(),
            'typeId' => $this->integer()->notNull(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'deletedWithOwner' => $this->boolean()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->createTable('{{%supertableblocktypes}}', [
            'id' => $this->primaryKey(),
            'fieldId' => $this->integer()->notNull(),
            'fieldLayoutId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    protected function createIndexes()
    {
        $this->createIndex(null, '{{%supertableblocks}}', ['ownerId'], false);
        $this->createIndex(null, '{{%supertableblocks}}', ['fieldId'], false);
        $this->createIndex(null, '{{%supertableblocks}}', ['typeId'], false);
        $this->createIndex(null, '{{%supertableblocks}}', ['sortOrder'], false);
        $this->createIndex(null, '{{%supertableblocktypes}}', ['fieldId'], false);
        $this->createIndex(null, '{{%supertableblocktypes}}', ['fieldLayoutId'], false);
    }

    protected function addForeignKeys()
    {
        $this->addForeignKey(null, '{{%supertableblocks}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks}}', ['ownerId'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks}}', ['typeId'], '{{%supertableblocktypes}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocktypes}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocktypes}}', ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL', null);
    }
}
