<?php
namespace verbb\supertable\migrations;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;
use yii\db\Expression;

class m180220_000000_fix_context extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
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
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180220_000000_fix_context cannot be reverted.\n";

        return false;
    }
}
