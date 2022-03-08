<?php
namespace verbb\supertable\migrations;

use verbb\supertable\fields\SuperTableField;
use verbb\supertable\elements\SuperTableBlockElement;

use Craft;
use craft\db\Migration;

class m180211_000000_type_columns extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
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
                'newClass' => SuperTableBlockElement::class,
            ],
            [
                'tables' => [
                    '{{%fields}}',
                ],
                'oldClass' => 'SuperTable',
                'newClass' => SuperTableField::class,
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

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m180211_000000_type_columns cannot be reverted.\n";

        return false;
    }
}
