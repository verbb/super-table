<?php
namespace verbb\supertable\migrations;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;
use yii\db\Expression;

class m180219_000000_sites extends Migration
{
    // Properties
    // =========================================================================

    protected $caseSql;


    // Public Methods
    // =========================================================================

    public function safeUp()
    {
        $sites = (new Query())
            ->select(['*'])
            ->from([Table::SITES])
            ->all($this->db);

        $this->caseSql = 'case';

        foreach ($sites as $i => $site) {
            $this->caseSql .= ' when % = ' . $this->db->quoteValue($site['handle']) . ' then ' . $this->db->quoteValue($site['id']);
        }

        $this->caseSql .= ' end';


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

        Craft::$app->getDb()->getSchema()->refresh();

        foreach ($this->db->getSchema()->getTableNames() as $tableName) {
            if (StringHelper::startsWith($tableName, $superTableTablePrefix)) {

                // Don't continue if siteId already done
                if ($this->db->columnExists($tableName, 'siteId')) {
                    continue;
                }

                // Check to see if Craft hasn't renamed the locale column to locale__siteId
                if ($this->db->columnExists($tableName, 'locale') && !$this->db->columnExists($tableName, 'locale__siteId')) {
                    $this->addSiteColumn($tableName, 'locale__siteId', true, 'locale');
                    $this->addForeignKey(null, $tableName, ['locale__siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
                }

                // There's actually an issue here in the MigrationHelper::dropAllIndexesOnTable class, not using the current migration
                // as context to find existing indexes. This is important, because the indexes have changed from their current names
                $this->dropAllForeignKeysOnTable($tableName);
                $this->dropAllIndexesOnTable($tableName);

                // Rename column
                if ($this->db->columnExists($tableName, 'locale__siteId')) {
                    $this->renameColumn($tableName, 'locale__siteId', 'siteId');
                }

                // Add them back (like creating a new Matrix would)
                $this->createIndex(null, $tableName, ['elementId', 'siteId'], true);
                $this->addForeignKey(null, $tableName, ['elementId'], '{{%elements}}', ['id'], 'CASCADE', null);
                $this->addForeignKey(null, $tableName, ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');

                // Delete the old column
                if ($this->db->columnExists($tableName, 'locale')) {
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

            $settings['propagationMethod'] = $localized ? 'none' : 'all';

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

    public function dropAllForeignKeysOnTable(string $tableName)
    {
        $rawTableName = $this->db->getSchema()->getRawTableName($tableName);
        $table = $this->db->getSchema()->getTableSchema($rawTableName);

        foreach ($table->foreignKeys as $foreignKeyName => $fk) {
            $this->dropForeignKey($foreignKeyName, $tableName);
        }
    }

    public function dropAllIndexesOnTable(string $tableName)
    {
        $rawTableName = $this->db->getSchema()->getRawTableName($tableName);
        $allIndexes = $this->db->getSchema()->findIndexes($tableName);
 
        foreach ($allIndexes as $indexName => $indexColumns) {
            $this->dropIndex($indexName, $rawTableName);
        }
    }

    // Protected Methods
    // =========================================================================

    protected function addSiteColumn(string $table, string $column, bool $isNotNull, string $localeColumn)
    {
        // Ignore NOT NULL for now
        $type = $this->integer()->after($localeColumn);
        $this->addColumn($table, $column, $type);

        // Set the values
        $this->update($table, [
            $column => new Expression(str_replace('%', "[[{$localeColumn}]]", $this->caseSql))
        ], '', [], false);

        // In case there were any referenced locales that no longer exist.
        if ($table === Table::SEARCHINDEX) {
            $this->delete($table, ['siteId' => null]);
        }

        if ($isNotNull) {
            $this->alterColumn($table, $column, $type->notNull());
        }
    }
}
