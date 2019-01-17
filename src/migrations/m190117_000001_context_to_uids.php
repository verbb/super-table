<?php
namespace verbb\supertable\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

use yii\db\Expression;

class m190117_000001_context_to_uids extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Map Super Table block type IDs to UUIDs
        $blockTypeUids = (new Query())
            ->select(['id', 'uid'])
            ->from(['{{%supertableblocktypes}}'])
            ->pairs();
        
        // Get all the Super Table sub-fields
        $fields = (new Query())
            ->select(['id', 'context'])
            ->from(['{{%fields}}'])
            ->where(['like', 'context', 'superTableBlockType'])
            ->all();

        // Switch out IDs for UUIDs
        foreach ($fields as $field) {
            list(, $blockTypeId) = explode(':', $field['context'], 2);

            // Make sure the block type still exists
            if (!isset($blockTypeUids[$blockTypeId])) {
                continue;
            }

            $this->update('{{%fields}}', [
                'context' => 'superTableBlockType:' . $blockTypeUids[$blockTypeId]
            ], ['id' => $field['id']], [], false);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190117_000001_context_to_uids cannot be reverted.\n";

        return false;
    }
}
