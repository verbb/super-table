<?php
namespace verbb\supertable\migrations;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\MatrixField;
use craft\fields\MissingField;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;

class m190131_000000_fix_supertable_missing_fields extends Migration
{
    public function safeUp()
    {
        // Fix any fields that have turned to a missing field in the previous migration
        $fields = (new Query())
            ->select(['*'])
            ->from([Table::FIELDS])
            ->where(['like', 'context', 'superTableBlockType:'])
            ->andWhere(['type' => MissingField::class])
            ->all();

        foreach ($fields as $field) {
            $settings = Json::decode($field['settings']);

            if (is_array($settings) && array_key_exists('expectedType', $settings)) {
                $expectedType = $settings['expectedType'];
                $newSettings = $settings['settings'] ?? [];

                $this->update(Table::FIELDS, [
                    'type' => $expectedType,
                    'settings' => $newSettings,
                ], [
                    'id' => $field['id']
                ]);
            }
        }
    }

    public function safeDown()
    {
        echo "m190131_000000_fix_supertable_missing_fields cannot be reverted.\n";
        return false;
    }
}