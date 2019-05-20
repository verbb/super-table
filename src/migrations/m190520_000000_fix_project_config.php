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
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\services\Fields;
use craft\services\Matrix;

class m190520_000000_fix_project_config extends Migration
{
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.super-table.schemaVersion', true);

        if (version_compare($schemaVersion, '2.0.12', '>=')) {
            return;
        }

        $projectConfig->muteEvents = true;

        // Fix any nested Super Table fields that have their settings in the main fields table
        $superTableFields = (new Query())
            ->select(['id', 'uid', 'settings', 'context'])
            ->from([Table::FIELDS])
            ->where(['type' => SuperTableField::class])
            ->andWhere(['!=', 'context', 'global'])
            ->all();

        foreach ($superTableFields as $superTableField) {
            // This should always be null - these non-global ST fields belong to Matrix blocks, not global fields
            $path = Fields::CONFIG_FIELDS_KEY . '.' . $superTableField['uid'];
            $settings = $projectConfig->get($path);

            if ($settings) {
                $projectConfig->remove($path);
            }
        }

        $projectConfig->muteEvents = false;

        return true;
    }

    public function safeDown()
    {
        echo "m190520_000000_fix_project_config cannot be reverted.\n";
        return false;
    }
}