<?php
namespace verbb\supertable\migrations;

use verbb\supertable\SuperTable;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;

class m180210_000000_migrate_content_tables extends Migration
{
    public function safeUp()
    {
        // Migrate our Craft 2 content tables from `supertablecontent_matrixId_superTableHandle` to `stc_matrixId_superTableHandle`
        // this will help to alleviate issues with long field handles, and in particular for nested fields. Otherwise, errors
        // are normally thrown due to the overly long index names.
        $fields = (new Query())
            ->select(['id'])
            ->from(['{{%fields}}'])
            ->where(['type' => ['verbb\supertable\fields\SuperTableField', 'SuperTable']])
            ->column();

        if (!empty($fields)) {
            foreach ($fields as $key => $fieldId) {
                $fieldQuery =  (new Query())
                    ->select(['*'])
                    ->from(['{{%fields}} fields'])
                    ->where(['id' => $fieldId])
                    ->one();

                $newContentTable = $this->getContentTableName($fieldQuery);
                $oldContentTable = str_replace('stc_', 'supertablecontent_', $newContentTable);

                echo $oldContentTable . " -> " . $newContentTable . "\n\n";

                if (Craft::$app->db->tableExists($oldContentTable)) {
                    $this->renameTable($oldContentTable, $newContentTable);
                }
            }
        }
    }

    public function safeDown()
    {
        echo "m180210_000000_migrate_content_tables cannot be reverted.\n";
        return false;
    }

    public function getContentTableName($supertableField)
    {
        $name = '';
        $parentFieldId = '';

        $handle = $supertableField['handle'];

        // Check if this field is inside a Matrix - we need to prefix this content table if so.
        if ($supertableField['context'] != 'global') {
            $parentFieldContext = explode(':', $supertableField['context']);

            if ($parentFieldContext[0] == 'matrixBlockType') {
                $parentFieldUid = $parentFieldContext[1];
                $parentFieldId = Db::idByUid('{{%matrixblocktypes}}', $parentFieldUid);
            }
        }

        $name = '_' . StringHelper::toLowerCase($handle) . $name;

        if ($parentFieldId) {
            $name = '_' . $parentFieldId . $name;
        }

        return '{{%stc' . $name . '}}';
    }
}
