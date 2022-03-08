<?php
namespace verbb\supertable\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\MissingField;
use craft\helpers\Json;

class m190131_000000_fix_supertable_missing_fields extends Migration
{
    public function safeUp(): bool
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

        return true;
    }

    public function safeDown(): bool
    {
        echo "m190131_000000_fix_supertable_missing_fields cannot be reverted.\n";
        return false;
    }
}