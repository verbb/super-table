<?php
namespace verbb\supertable\migrations;

use Craft;
use craft\db\Migration;

class m190511_100000_fix_project_config extends Migration
{
    public function safeUp(): bool
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.super-table.schemaVersion', true);

        if (version_compare($schemaVersion, '2.0.11', '>=')) {
            return true;
        }

        $projectConfig->muteEvents = true;

        $projectConfig->remove('superTable.superTableBlockTypes');

        $projectConfig->muteEvents = false;

        return true;
    }

    public function safeDown(): bool
    {
        echo "m190511_100000_fix_project_config cannot be reverted.\n";
        return false;
    }
}