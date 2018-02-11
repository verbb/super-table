<?php
namespace verbb\supertable\migrations;

use Craft;
use craft\db\Migration;
use yii\db\Expression;

class m180211_000000_type_columns extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $componentTypes = [
            [
                'tables' => [
                    '{{%elements}}',
                    '{{%elementindexsettings}}',
                    '{{%fieldlayouts}}',
                    '{{%templatecachecriteria}}',
                ],
                'oldClass' => 'SuperTable_Block',
                'newClass' => 'verbb\supertable\elements\SuperTableBlockElement',
            ],
            [
                'tables' => [
                    '{{%fields}}',
                ],
                'oldClass' => 'SuperTable',
                'newClass' => 'verbb\supertable\fields\SuperTableField',
            ],
        ];

        foreach ($componentTypes as $componentType) {
            $columns = ['type' => $componentType['newClass']];
            $condition = ['type' => $componentType['oldClass']];

            foreach ($componentType['tables'] as $table) {
                if (Craft::$app->db->tableExists($table)) {
                    $this->alterColumn($table, 'type', $this->string()->notNull());
                    $this->update($table, $columns, $condition, [], false);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180211_000000_type_columns cannot be reverted.\n";

        return false;
    }
}
