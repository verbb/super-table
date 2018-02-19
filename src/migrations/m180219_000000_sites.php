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

class m180219_000000_sites extends Migration
{
    // Static
    // =========================================================================

    public function safeUp()
    {
        // Rename the FK columns
        // ---------------------------------------------------------------------

        if ($this->db->columnExists('{{%supertableblocks}}', 'ownerLocale__siteId')) {
            MigrationHelper::renameColumn('{{%supertableblocks}}', 'ownerLocale__siteId', 'ownerSiteId');
        }

        // Drop the old FKs
        // ---------------------------------------------------------------------

        MigrationHelper::dropForeignKeyIfExists('{{%supertableblocks}}', ['ownerLocale'], $this);

        // Drop the old indexes
        // ---------------------------------------------------------------------

        MigrationHelper::dropIndexIfExists('{{%supertableblocks}}', ['ownerLocale'], false, $this);

        // Drop the locale columns
        // ---------------------------------------------------------------------

        if ($this->db->columnExists('{{%supertableblocks}}', 'ownerLocale')) {
            $this->dropColumn('{{%supertableblocks}}', 'ownerLocale');
        }

        // Super Table content tables
        // ---------------------------------------------------------------------

        $superTableTablePrefix = $this->db->getSchema()->getRawTableName('{{%stc_}}');

        foreach ($this->db->getSchema()->getTableNames() as $tableName) {
            if (StringHelper::startsWith($tableName, $superTableTablePrefix)) {
                // Rename column
                if ($this->db->columnExists($tableName, 'locale__siteId')) {
                    MigrationHelper::renameColumn($tableName, 'locale__siteId', 'siteId');
                }

                MigrationHelper::dropAllForeignKeysOnTable($tableName);
                MigrationHelper::dropAllIndexesOnTable($tableName);

                $this->createIndex(null, $tableName, ['elementId', 'siteId'], true);
                $this->addForeignKey(null, $tableName, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');

                // Delete the old FK, indexes, and column
                if ($this->db->columnExists($tableName, 'locale')) {
                    MigrationHelper::dropForeignKeyIfExists($tableName, ['locale'], $this);
                    MigrationHelper::dropIndexIfExists($tableName, ['elementId', 'locale'], true, $this);
                    MigrationHelper::dropIndexIfExists($tableName, ['locale'], false, $this);
                    $this->dropColumn($tableName, 'locale');
                }
            }
        }

        Craft::$app->getDb()->getSchema()->refresh();

        // Update Super Table/relationship field settings
        // ---------------------------------------------------------------------

        $fields = (new Query())
            ->select(['id', 'type', 'translationMethod', 'settings'])
            ->from(['{{%fields}}'])
            ->where([
                'type' => [
                    SuperTableField::class,
                ]
            ])
            ->all($this->db);

        foreach ($fields as $field) {

            $settings = Json::decodeIfJson($field['settings']);

            if (!is_array($settings)) {
                echo 'Field '.$field['id'].' ('.$field['type'].') settings were invalid JSON: '.$field['settings']."\n";
                $settings = [];
            }

            $localized = ($field['translationMethod'] === 'site');

            $settings['localizeBlocks'] = $localized;

            $this->update(
                '{{%fields}}',
                [
                    'translationMethod' => 'none',
                    'settings' => Json::encode($settings),
                ],
                ['id' => $field['id']],
                [],
                false);
        }
    }

    public function safeDown()
    {
        echo "m180219_000000_sites cannot be reverted.\n";

        return false;
    }
}
