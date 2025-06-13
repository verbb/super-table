<?php
namespace verbb\supertable\migrations;

use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\base\PreviewableFieldInterface;
use craft\base\ThumbableFieldInterface;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\Matrix;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\migrations\BaseContentRefactorMigration;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\services\ProjectConfig;

use yii\console\Exception;
use yii\db\Exception as DbException;
use yii\helpers\Inflector;

class m240115_000000_craft5 extends BaseContentRefactorMigration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $fieldsService = Craft::$app->getFields();

        //
        // Content Refactor
        //

        $blockTypeData = (new Query())
            ->select(['id', 'uid', 'fieldLayoutId'])
            ->from('{{%supertableblocktypes}}')
            ->indexBy('uid')
            ->all();

        $indexedSuperTableFieldConfigs = [];

        $superTableFieldConfigs = $projectConfig->find(
            fn(array $config) => ($config['type'] ?? null) === SuperTableField::class,
        );

        foreach ($superTableFieldConfigs as $superTableFieldPath => $superTableFieldConfig) {
            $superTableFieldUid = ArrayHelper::lastValue(explode('.', $superTableFieldPath));

            if (!isset($superTableFieldConfig['settings']['contentTable'])) {
                // Just do a sanity check in case the content table exists, but is missing from settings.
                $baseName = 'stc_' . strtolower($superTableFieldConfig['handle']);

                if (!Craft::$app->getDb()->tableExists($baseName)) {
                    continue;
                }

                throw new Exception("Super Table field {$superTableFieldUid} is missing its contentTable value.");
            }

            $indexedSuperTableFieldConfigs[$superTableFieldUid] = $superTableFieldConfig;
        }

        foreach ($projectConfig->get('superTableBlockTypes') ?? [] as $blockTypeUid => $blockTypeConfig) {
            if (!isset($indexedSuperTableFieldConfigs[$blockTypeConfig['field']])) {
                continue;
            }
            
            if (!isset($blockTypeData[$blockTypeUid])) {
                throw new Exception("Super Table block type $blockTypeUid is out of sync.");
            }
            
            $blockTypeDatum = $blockTypeData[$blockTypeUid];
            $fieldLayout = $fieldsService->getLayoutById($blockTypeDatum['fieldLayoutId']);
            
            $this->updateElements(
                (new Query())->from('{{%supertableblocks}}')->where(['typeId' => $blockTypeDatum['id']]),
                $fieldLayout,
                $indexedSuperTableFieldConfigs[$blockTypeConfig['field']]['settings']['contentTable'],
                'field_',
            );
        }


        //
        // Entrify Blocks
        //

        // Index entry type names and handles
        $entryTypeNames = [];
        $entryTypeHandles = [];
        foreach ($projectConfig->get(ProjectConfig::PATH_ENTRY_TYPES) ?? [] as $entryTypeConfig) {
            $entryTypeNames[$entryTypeConfig['name']] = true;
            $entryTypeHandles[$entryTypeConfig['handle']] = true;
        }

        // Index global field names and handles
        $fieldNames = [];
        $fieldHandles = [];
        foreach ($projectConfig->get(ProjectConfig::PATH_FIELDS) ?? [] as $fieldConfig) {
            $fieldNames[$fieldConfig['name']] = true;
            $fieldHandles[$fieldConfig['handle']] = true;
        }

        // Get all the block type configs, grouped by field
        $blockTypeConfigsByField = [];
        foreach ($projectConfig->get('superTableBlockTypes') ?? [] as $uid => $config) {
            $blockTypeConfigsByField[$config['field']][$uid] = $config;
        }

        // Find all the Super Table field configs
        $fieldConfigs = $projectConfig->find(
            fn(array $config) => ($config['type'] ?? null) === SuperTableField::class,
        );

        $newEntryTypes = [];

        foreach ($fieldConfigs as $fieldPath => $fieldConfig) {
            $fieldUid = ArrayHelper::lastValue(explode('.', $fieldPath));
            $fieldEntryTypes = [];

            foreach ($blockTypeConfigsByField[$fieldUid] ?? [] as $blockTypeUid => $blockTypeConfig) {
                // Generate a new name and handle - Super Table didn't use them
                $blockTypeConfig['name'] = $fieldConfig['name'] . ' Block';
                $blockTypeConfig['handle'] = $fieldConfig['handle'] . 'Block';

                $entryType = $newEntryTypes[] = $fieldEntryTypes[] = new EntryType([
                    'uid' => $blockTypeUid,
                    'name' => $this->uniqueName($blockTypeConfig['name'], $entryTypeNames),
                    'handle' => $this->uniqueHandle($blockTypeConfig['handle'], $entryTypeHandles),
                    'hasTitleField' => false,
                    'titleFormat' => null,
                ]);

                $fieldLayoutUid = ArrayHelper::firstKey($blockTypeConfig['fieldLayouts'] ?? []);
                $fieldLayout = $fieldLayoutUid ? $fieldsService->getLayoutByUid($fieldLayoutUid) : new FieldLayout();
                $fieldLayout->type = Entry::class;
                $entryType->setFieldLayout($fieldLayout);
                /** @var PreviewableFieldInterface|null $thumbField */
                $thumbField = null;
                $foundPreviewableField = false;

                foreach ($fieldLayout?->getCustomFieldElements() ?? [] as $layoutElement) {
                    $subField = $layoutElement->getField();

                    // Set a unique name & label, and preserve the originals if needed
                    $layoutElement->label = $subField->name;
                    $subField->name = $this->uniqueName(sprintf(
                        '%s - %s',
                        $fieldConfig['name'],
                        $subField->name !== '__blank__' ? $subField->name : Inflector::camel2words($subField->handle),
                    ), $fieldNames);

                    $originalHandle = $subField->handle;
                    $subField->handle = $this->uniqueHandle($subField->handle, $fieldHandles);

                    if ($subField->handle !== $originalHandle) {
                        $layoutElement->handle = $originalHandle;
                    }

                    $muteEvents = $projectConfig->muteEvents;
                    $projectConfig->muteEvents = true;
                    $projectConfig->set(
                        sprintf('%s.%s', ProjectConfig::PATH_FIELDS, $subField->uid),
                        $fieldsService->createFieldConfig($subField),
                    );
                    $projectConfig->muteEvents = $muteEvents;

                    $this->update(Table::FIELDS, [
                        'name' => $subField->name,
                        'handle' => $subField->handle,
                        'context' => 'global',
                    ], [
                        'uid' => $subField->uid,
                    ], updateTimestamp: false);

                    if (!$thumbField && $subField instanceof ThumbableFieldInterface) {
                        $layoutElement->providesThumbs = true;
                        $thumbField = $subField;
                    } elseif (!$foundPreviewableField && $subField instanceof PreviewableFieldInterface) {
                        $layoutElement->includeInCards = true;
                        $foundPreviewableField = true;
                    }
                }

                if (!$foundPreviewableField && $thumbField instanceof PreviewableFieldInterface) {
                    $thumbField->layoutElement->includeInCards = true;
                }
            }

            // update the field config
            $fieldConfig['settings']['entryTypes'] = array_map(fn(EntryType $entryType) => $entryType->uid, $fieldEntryTypes);

            // If this field is a static field, override and set min and max rows to 1 to preserve this behaviour in Craft 5
            if (isset($fieldConfig['settings']['staticField']) && $fieldConfig['settings']['staticField']) {
                $fieldConfig['settings']['minRows'] = 1;
                $fieldConfig['settings']['maxRows'] = 1;
            } else {
                $fieldConfig['settings']['maxEntries'] = ArrayHelper::remove($fieldConfig['settings'], 'maxBlocks');
                $fieldConfig['settings']['minEntries'] = ArrayHelper::remove($fieldConfig['settings'], 'minBlocks');
            }

            $fieldConfig['settings']['viewMode'] = 'blocks';

            unset($fieldConfig['settings']['contentTable']);

            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;
            $projectConfig->set($fieldPath, $fieldConfig);
            $projectConfig->muteEvents = $muteEvents;

            $this->update(Table::FIELDS, [
                'settings' => Json::encode($fieldConfig['settings']),
            ], [
                'uid' => $fieldUid,
            ], updateTimestamp: false);
        }

        // save the new entry types
        $entriesServices = Craft::$app->getEntries();
        $typeIdMap = [];

        $oldIds = (new Query())
            ->select(['uid', 'id'])
            ->from('{{%supertableblocktypes}}')
            ->pairs();

        foreach ($newEntryTypes as $entryType) {
            $entriesServices->saveEntryType($entryType, false);
            if (isset($oldIds[$entryType->uid])) {
                $typeIdMap[$oldIds[$entryType->uid]] = $entryType->id;
            }
        }

        // Store the blockType vs entryType ID map for other plugins to make use of in migrations.
        Craft::$app->getCache()->set('superTableBlockTypeMap', $typeIdMap);

        if (!empty($typeIdMap)) {
            // disable FK checks for all of this
            try {
                $this->db->transaction(function () {
                    $this->db->createCommand()->checkIntegrity(false)->execute();
                });
                $disabledFkChecks = true;
            } catch (DbException) {
                // the DB user probably didn't have permission
                // see https://github.com/craftcms/cms/issues/15063#issuecomment-2194059768
                $disabledFkChecks = false;
            };

            // entrify the Super Table blocks
            $typeIdSql = 'CASE';
            foreach ($typeIdMap as $oldId => $newId) {
                $typeIdSql .= " WHEN [[typeId]] = $oldId THEN $newId";
            }
            $typeIdSql .= " END";
            $this->execute(sprintf(
                <<<SQL
INSERT INTO %s ([[id]], [[primaryOwnerId]], [[fieldId]], [[typeId]], [[postDate]], [[dateCreated]], [[dateUpdated]])
SELECT [[id]], [[primaryOwnerId]], [[fieldId]], %s, [[dateCreated]], [[dateCreated]], [[dateUpdated]]
FROM %s supertableblocks
WHERE [[supertableblocks.typeId]] IN (%s)
SQL,
                Table::ENTRIES,
                $typeIdSql,
                '{{%supertableblocks}}',
                implode(',', array_keys($typeIdMap)),
            ));

            $this->execute(sprintf(
                <<<SQL
INSERT INTO %s
SELECT * FROM %s
SQL,
                Table::ELEMENTS_OWNERS,
                '{{%supertableblocks_owners}}',
            ));

            $this->update(
                Table::ELEMENTS,
                ['deletedWithOwner' => true],
                ['id' => (new Query())
                    ->select('id')
                    ->from(['supertableblocks' => '{{%supertableblocks}}'])
                    ->where([
                        'supertableblocks.typeId' => array_keys($typeIdMap),
                        'supertableblocks.deletedWithOwner' => true,
                    ]),
                ],
            );

            $this->update(
                Table::ELEMENTS,
                ['type' => Entry::class],
                ['type' => 'verbb\supertable\elements\SuperTableBlockElement'],
                updateTimestamp: false,
            );

            if ($disabledFkChecks) {
                $this->db->createCommand()->checkIntegrity(true)->execute();
            }
        }

        // drop the old Super Table tables
        $this->dropAllForeignKeysToTable('{{%supertableblocks_owners}}');
        $this->dropAllForeignKeysToTable('{{%supertableblocks}}');
        $this->dropAllForeignKeysToTable('{{%supertableblocktypes}}');
        $this->dropTable('{{%supertableblocks_owners}}');
        $this->dropTable('{{%supertableblocks}}');
        $this->dropTable('{{%supertableblocktypes}}');

        $contentTablePrefix = Craft::$app->getDb()->getSchema()->getRawTableName('{{%stc_}}');

        foreach ($this->db->getSchema()->getTableNames() as $table) {
            if (str_starts_with($table, $contentTablePrefix)) {
                $this->dropTable($table);
            }
        }

        $fieldsService->refreshFields();

        // remove the old Super Table block type configs
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;
        $projectConfig->remove('superTableBlockTypes');
        $projectConfig->muteEvents = $muteEvents;

        return true;
    }

    public function safeDown(): bool
    {
        echo "m240115_000000_craft5 cannot be reverted.\n";

        return false;
    }


    // Private Methods
    // =========================================================================

    private function uniqueName(string $name, array &$names): string
    {
        $i = 1;

        do {
            $test = $name . ($i !== 1 ? " $i" : '');

            if (!isset($names[$test])) {
                $names[$test] = true;
                return $test;
            }

            $i++;
        } while (true);
    }

    private function uniqueHandle(string $handle, array &$handles): string
    {
        $i = 1;

        do {
            $test = $handle . ($i !== 1 ? $i : '');

            if (!isset($handles[$test])) {
                $handles[$test] = true;
                return $test;
            }

            $i++;
        } while (true);
    }
}
