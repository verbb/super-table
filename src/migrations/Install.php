<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists('{{%supertableblocks}}');
        $this->createTable('{{%supertableblocks}}', [
            'id' => $this->integer()->notNull(),
            'primaryOwnerId' => $this->integer()->notNull(),
            'fieldId' => $this->integer()->notNull(),
            'typeId' => $this->integer()->notNull(),
            'deletedWithOwner' => $this->boolean()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->archiveTableIfExists('{{%supertableblocks_owners}}');
        $this->createTable('{{%supertableblocks_owners}}', [
            'blockId' => $this->integer()->notNull(),
            'ownerId' => $this->integer()->notNull(),
            'sortOrder' => $this->smallInteger()->unsigned()->notNull(),
            'PRIMARY KEY([[blockId]], [[ownerId]])',
        ]);

        $this->archiveTableIfExists('{{%supertableblocktypes}}');
        $this->createTable('{{%supertableblocktypes}}', [
            'id' => $this->primaryKey(),
            'fieldId' => $this->integer()->notNull(),
            'fieldLayoutId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, '{{%supertableblocks}}', ['primaryOwnerId'], false);
        $this->createIndex(null, '{{%supertableblocks}}', ['fieldId'], false);
        $this->createIndex(null, '{{%supertableblocks}}', ['typeId'], false);
        $this->createIndex(null, '{{%supertableblocktypes}}', ['fieldId'], false);
        $this->createIndex(null, '{{%supertableblocktypes}}', ['fieldLayoutId'], false);
    }

    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, '{{%supertableblocks}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks}}', ['primaryOwnerId'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks}}', ['typeId'], '{{%supertableblocktypes}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks_owners}}', ['blockId'], '{{%supertableblocks}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks_owners}}', ['ownerId'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocktypes}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocktypes}}', ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'SET NULL', null);
    }

    public function dropTables(): void
    {
        $this->dropTableIfExists('{{%supertableblocks}}');
        $this->dropTableIfExists('{{%supertableblocks_owners}}');
        $this->dropTableIfExists('{{%supertableblocktypes}}');
    }

    public function dropForeignKeys(): void
    {
        if ($this->db->tableExists('{{%supertableblocks}}')) {
            MigrationHelper::dropAllForeignKeysOnTable('{{%supertableblocks}}', $this);
        }

        if ($this->db->tableExists('{{%supertableblocks_owners}}')) {
            MigrationHelper::dropAllForeignKeysOnTable('{{%supertableblocks_owners}}', $this);
        }

        if ($this->db->tableExists('{{%supertableblocktypes}}')) {
            MigrationHelper::dropAllForeignKeysOnTable('{{%supertableblocktypes}}', $this);
        }
    }
}
