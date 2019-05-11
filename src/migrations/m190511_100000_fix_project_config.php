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

class m190511_100000_fix_project_config extends Migration
{
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.super-table.schemaVersion', true);

        if (version_compare($schemaVersion, '2.0.11', '>=')) {
            return;
        }

        $projectConfig->muteEvents = true;

        $projectConfig->remove('superTable.superTableBlockTypes');

        $projectConfig->muteEvents = false;
    }

    public function safeDown()
    {
        echo "m190511_100000_fix_project_config cannot be reverted.\n";
        return false;
    }
}