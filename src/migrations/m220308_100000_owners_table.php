<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * m220213_015220_matrixblocks_elements_table migration.
 */
class m220308_100000_owners_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropTableIfExists('{{%supertableblocks_owners}}');

        $this->createTable('{{%supertableblocks_owners}}', [
            'blockId' => $this->integer()->notNull(),
            'ownerId' => $this->integer()->notNull(),
            'sortOrder' => $this->smallInteger()->unsigned()->notNull(),
            'PRIMARY KEY([[blockId]], [[ownerId]])',
        ]);

        $this->addForeignKey(null, '{{%supertableblocks_owners}}', ['blockId'], '{{%supertableblocks}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%supertableblocks_owners}}', ['ownerId'], Table::ELEMENTS, ['id'], 'CASCADE', null);

        $blocksTable = '{{%supertableblocks}}';
        $ownersTable = '{{%supertableblocks_owners}}';

        // Fix any null sortOrders. It can happen!
        $this->update($blocksTable, ['sortOrder' => '1'], ['sortOrder' => null]);

        $this->execute(<<<SQL
INSERT INTO $ownersTable ([[blockId]], [[ownerId]], [[sortOrder]]) 
SELECT [[id]], [[ownerId]], [[sortOrder]] 
FROM $blocksTable
SQL
        );

        // drop sortOrder
        $this->dropIndexIfExists('{{%supertableblocks}}', ['sortOrder'], false);
        $this->dropColumn('{{%supertableblocks}}', 'sortOrder');

        // ownerId => primaryOwnerId
        $this->renameColumn('{{%supertableblocks}}', 'ownerId', 'primaryOwnerId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220308_100000_owners_table cannot be reverted.\n";
        return false;
    }
}
