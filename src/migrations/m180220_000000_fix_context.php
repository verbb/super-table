<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\StringHelper;

class m180220_000000_fix_context extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $fields = (new Query())
            ->select(['id', 'type', 'context'])
            ->from(['{{%fields}}'])
            ->all($this->db);

        foreach ($fields as $field) {
            if (StringHelper::startsWith($field['context'], 'supertableBlockType')) {
                $context = str_replace('supertableBlockType', 'superTableBlockType', $field['context']);

                $this->update('{{%fields}}', ['context' => $context], ['id' => $field['id']], [], false);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m180220_000000_fix_context cannot be reverted.\n";

        return false;
    }
}
