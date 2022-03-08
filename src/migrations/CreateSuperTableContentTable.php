<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;

class CreateSuperTableContentTable extends Migration
{
    // Properties
    // =========================================================================

    /**
     * @var string|null The table name
     */
    public ?string $tableName = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable($this->tableName, [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, $this->tableName, ['elementId', 'siteId'], true);
        $this->addForeignKeys();

        return true;
    }

    /**
     * Adds the foreign keys.
     */
    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, $this->tableName, ['elementId'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, $this->tableName, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        return false;
    }
}
