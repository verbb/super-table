<?php
namespace verbb\supertable\services;

use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\errors\SuperTableBlockTypeNotFoundException;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\migrations\CreateSuperTableContentTable;
use verbb\supertable\models\SuperTableBlockTypeModel;
use verbb\supertable\records\SuperTableBlockTypeRecord;
use verbb\supertable\assetbundles\SuperTableAsset;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\db\Query;
use craft\db\Table;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Site;
use craft\services\Fields;
use craft\web\View;

use yii\base\Component;
use yii\base\Exception;

class SuperTableService extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var bool Whether to ignore changes to the project config.
     * @deprecated in 3.1.2. Use [[\craft\services\ProjectConfig::$muteEvents]] instead.
     */
    public $ignoreProjectConfigChanges = false;

    /**
     * @var
     */
    private $_blockTypesById;

    /**
     * @var
     */
    private $_blockTypesByFieldId;

    /**
     * @var
     */
    private $_fetchedAllBlockTypesForFieldId;

    /**
     * @var
     */
    private $_blockTypeRecordsById;

    /**
     * @var string[]
     */
    private $_uniqueFieldHandles = [];

    /**
     * @var
     */
    private $_parentSuperTableFields;

    const CONFIG_BLOCKTYPE_KEY = 'superTableBlockTypes';


    // Public Methods
    // =========================================================================

    /**
     * Returns the block types for a given Super Table field.
     *
     * @param int $fieldId The Super Table field ID.
     *
     * @return SuperTableBlockType[] An array of block types.
     */
    public function getBlockTypesByFieldId(int $fieldId): array
    {
        if (!empty($this->_fetchedAllBlockTypesForFieldId[$fieldId])) {
            return $this->_blockTypesByFieldId[$fieldId];
        }

        $this->_blockTypesByFieldId[$fieldId] = [];

        $results = $this->_createBlockTypeQuery()
            ->where(['bt.fieldId' => $fieldId])
            ->all();

        foreach ($results as $result) {
            $blockType = new SuperTableBlockTypeModel($result);
            $this->_blockTypesById[$blockType->id] = $blockType;
            $this->_blockTypesByFieldId[$fieldId][] = $blockType;
        }

        $this->_fetchedAllBlockTypesForFieldId[$fieldId] = true;

        return $this->_blockTypesByFieldId[$fieldId];
    }

    /**
     * Returns all the block types.
     *
     * @return SuperTableBlockTypeModel[] An array of block types.
     */
    public function getAllBlockTypes(): array
    {
        $results = $this->_createBlockTypeQuery()
            ->innerJoin(Table::FIELDS . ' f', '[[f.id]] = [[bt.fieldId]]')
            ->where(['f.type' => SuperTableField::class])
            ->all();

        foreach ($results as $key => $result) {
            $results[$key] = new SuperTableBlockTypeModel($result);
        }

        return $results;
    }

    /**
     * Returns a block type by its ID.
     *
     * @param int $blockTypeId The block type ID.
     *
     * @return SuperTableBlockTypeModel|null The block type, or `null` if it didn’t exist.
     */
    public function getBlockTypeById(int $blockTypeId)
    {
        if ($this->_blockTypesById !== null && array_key_exists($blockTypeId, $this->_blockTypesById)) {
            return $this->_blockTypesById[$blockTypeId];
        }

        $result = $this->_createBlockTypeQuery()
            ->where(['bt.id' => $blockTypeId])
            ->one();

        return $this->_blockTypesById[$blockTypeId] = $result ? new SuperTableBlockTypeModel($result) : null;
    }

    /**
     * Validates a block type.
     *
     * If the block type doesn’t validate, any validation errors will be stored on the block type.
     *
     * @param SuperTableBlockTypeModel $blockType        The block type.
     * @param bool            $validateUniques      Whether the Name and Handle attributes should be validated to
     *                                              ensure they’re unique. Defaults to `true`.
     *
     * @return bool Whether the block type validated.
     */
    public function validateBlockType(SuperTableBlockTypeModel $blockType, bool $validateUniques = true): bool
    {
        $validates = true;

        $reservedHandles = ['type'];

        $blockTypeRecord = $this->_getBlockTypeRecord($blockType);
        $blockTypeRecord->fieldId = $blockType->fieldId;

        if (!$blockTypeRecord->validate()) {
            $validates = false;
            $blockType->addErrors($blockTypeRecord->getErrors());
        }

        // Reset this each time - normal Super Table fields won't be an issue, but when validation is called multiple times
        // its because its being embedded in another field (Matrix). Thus, we need to reset unique field handles, because they
        // can be different across multiple parent fields.
        $this->_uniqueFieldHandles = [];

        // Can't validate multiple new rows at once so we'll need to give these temporary context to avoid false unique
        // handle validation errors, and just validate those manually. Also apply the future fieldColumnPrefix so that
        // field handle validation takes its length into account.
        $contentService = Craft::$app->getContent();
        $originalFieldContext = $contentService->fieldContext;
        $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;

        $contentService->fieldContext = StringHelper::randomString(10);
        $contentService->fieldColumnPrefix = 'field_';

        foreach ($blockType->getFields() as $field) {
            $field->validate();

            if ($field->handle) {
                if (in_array($field->handle, $this->_uniqueFieldHandles, true)) {
                    // This error *might* not be entirely accurate, but it's such an edge case that it's probably better
                    // for the error to be worded for the common problem (two duplicate handles within the same block
                    // type).
                    $error = Craft::t('app', '{attribute} "{value}" has already been taken.', [
                        'attribute' => Craft::t('app', 'Handle'),
                        'value' => $field->handle
                    ]);

                    $field->addError('handle', $error);
                } else {
                    $this->_uniqueFieldHandles[] = $field->handle;
                }
            }

            if ($field->hasErrors()) {
                $blockType->hasFieldErrors = true;
                $validates = false;

                $blockType->addErrors($field->getErrors());
            }

            // `type` is a restricted handle
            if (in_array($field->handle, $reservedHandles)) {
                $blockType->hasFieldErrors = true;
                $validates = false;

                $field->addErrors(['handle' => Craft::t('app', '"{handle}" is a reserved word.', ['handle' => $field->handle])]);
            }

            // Special-case for validating child Matrix fields
            if (get_class($field) == 'craft\fields\Matrix') {
                $matrixBlockTypes = $field->getBlockTypes();

                foreach ($matrixBlockTypes as $matrixBlockType) {
                    if ($matrixBlockType->hasFieldErrors) {
                        $blockType->hasFieldErrors = true;
                        $validates = false;

                        // Store a generic error for our parent Super Table field to show a nested error exists
                        $field->addErrors(['field' => 'general']);
                    }
                }
            }
        }

        $contentService->fieldContext = $originalFieldContext;
        $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;

        return $validates;
    }

    /**
     * Saves a block type.
     *
     * @param SuperTableBlockTypeModel $blockType    The block type to be saved.
     * @param bool            $validate       Whether the block type should be validated before being saved.
     *                                        Defaults to `true`.
     *
     * @return bool
     * @throws Exception if an error occurs when saving the block type
     * @throws \Throwable if reasons
     */
    public function saveBlockType(SuperTableBlockTypeModel $blockType, bool $runValidation = true): bool
    {
        if ($runValidation && !$blockType->validate()) {
            return false;
        }

        $fieldsService = Craft::$app->getFields();

        /** @var Field $parentField */
        $parentField = $fieldsService->getFieldById($blockType->fieldId);
        $isNewBlockType = $blockType->getIsNew();

        $projectConfig = Craft::$app->getProjectConfig();

        $configData = [
            'field' => $parentField->uid,
        ];

        // Now, take care of the field layout for this block type
        // -------------------------------------------------------------
        $fieldLayoutFields = [];
        $sortOrder = 0;

        $configData['fields'] = [];

        foreach ($blockType->getFields() as $field) {
            $configData['fields'][$field->uid] = $fieldsService->createFieldConfig($field);

            $field->sortOrder = ++$sortOrder;
            $fieldLayoutFields[] = $field;
        }

        $fieldLayoutTab = new FieldLayoutTab();
        $fieldLayoutTab->name = 'Content';
        $fieldLayoutTab->sortOrder = 1;
        $fieldLayoutTab->setFields($fieldLayoutFields);

        $fieldLayout = $blockType->getFieldLayout();

        if ($fieldLayout->uid) {
            $layoutUid = $fieldLayout->uid;
        } else {
            $layoutUid = StringHelper::UUID();
            $fieldLayout->uid = $layoutUid;
        }

        $fieldLayout->setTabs([$fieldLayoutTab]);
        $fieldLayout->setFields($fieldLayoutFields);

        $fieldLayoutConfig = $fieldLayout->getConfig();

        $configData['fieldLayouts'] = [
            $layoutUid => $fieldLayoutConfig
        ];

        $configPath = self::CONFIG_BLOCKTYPE_KEY . '.' . $blockType->uid;
        $projectConfig->set($configPath, $configData);

        if ($isNewBlockType) {
            $blockType->id = Db::idByUid('{{%supertableblocktypes}}', $blockType->uid);
        }

        return true;
    }

    /**
     * Handle block type change
     *
     * @param ConfigEvent $event
     */
    public function handleChangedBlockType(ConfigEvent $event)
    {
        if ($this->ignoreProjectConfigChanges) {
            return;
        }

        $blockTypeUid = $event->tokenMatches[0];
        $data = $event->newValue;
        $previousData = $event->oldValue;

        // Make sure the field has been synced
        $fieldId = Db::idByUid(Table::FIELDS, $data['field']);
        if ($fieldId === null) {
            Craft::$app->getProjectConfig()->defer($event, [$this, __FUNCTION__]);
            return;
        }

        $fieldsService = Craft::$app->getFields();
        $contentService = Craft::$app->getContent();

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            // Store the current contexts.
            $originalContentTable = $contentService->contentTable;
            $originalFieldContext = $contentService->fieldContext;
            $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;
            $originalOldFieldColumnPrefix = $fieldsService->oldFieldColumnPrefix;

            // Get the block type record
            $blockTypeRecord = $this->_getBlockTypeRecord($blockTypeUid);

            // Set the basic info on the new block type record
            $blockTypeRecord->fieldId = $fieldId;
            $blockTypeRecord->uid = $blockTypeUid;

            // Make sure that alterations, if any, occur in the correct context.
            $contentService->fieldContext = 'superTableBlockType:' . $blockTypeUid;
            $contentService->fieldColumnPrefix = 'field_';

            /** @var SuperTableField $superTableField */
            $superTableField = $fieldsService->getFieldById($blockTypeRecord->fieldId);
            $contentService->contentTable = $superTableField->contentTable;
            $fieldsService->oldFieldColumnPrefix = 'field_';

            $oldFields = $previousData['fields'] ?? [];
            $newFields = $data['fields'] ?? [];

            // Remove fields that this block type no longer has
            foreach ($oldFields as $fieldUid => $fieldData) {
                if (!array_key_exists($fieldUid, $newFields)) {
                    $fieldsService->applyFieldDelete($fieldUid);
                }
            }

            // (Re)save all the fields that now exist for this block.
            foreach ($newFields as $fieldUid => $fieldData) {
                $fieldsService->applyFieldSave($fieldUid, $fieldData, 'superTableBlockType:' . $blockTypeUid);
            }

            // Refresh the schema cache
            Craft::$app->getDb()->getSchema()->refresh();

            $contentService->fieldContext = $originalFieldContext;
            $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;
            $contentService->contentTable = $originalContentTable;
            $fieldsService->oldFieldColumnPrefix = $originalOldFieldColumnPrefix;

            if (!empty($data['fieldLayouts'])) {
                // Save the field layout
                $layout = FieldLayout::createFromConfig(reset($data['fieldLayouts']));
                $layout->id = $blockTypeRecord->fieldLayoutId;
                $layout->type = SuperTableBlockElement::class;
                $layout->uid = key($data['fieldLayouts']);

                $fieldsService->saveLayout($layout);
                $blockTypeRecord->fieldLayoutId = $layout->id;
            } else if ($blockTypeRecord->fieldLayoutId) {
                // Delete the field layout
                $fieldsService->deleteLayoutById($blockTypeRecord->fieldLayoutId);
                $blockTypeRecord->fieldLayoutId = null;
            }

            // Save it
            $blockTypeRecord->save(false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        unset(
            $this->_blockTypesById[$blockTypeRecord->id],
            $this->_blockTypesByFieldId[$blockTypeRecord->fieldId]
        );
        $this->_fetchedAllBlockTypesForFieldId[$blockTypeRecord->fieldId] = false;
    }

    /**
     * Deletes a block type.
     *
     * @param SuperTableBlockTypeModel $blockType The block type.
     * @return bool Whether the block type was deleted successfully.
     */
    public function deleteBlockType(SuperTableBlockTypeModel $blockType): bool
    {
        Craft::$app->getProjectConfig()->remove(self::CONFIG_BLOCKTYPE_KEY . '.' . $blockType->uid);

        return true;
    }

    /**
     * Handle block type change
     *
     * @param ConfigEvent $event
     * @throws \Throwable if reasons
     */
    public function handleDeletedBlockType(ConfigEvent $event)
    {
        if ($this->ignoreProjectConfigChanges) {
            return;
        }

        $blockTypeUid = $event->tokenMatches[0];
        $blockTypeRecord = $this->_getBlockTypeRecord($blockTypeUid);

        if (!$blockTypeRecord->id) {
            return;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $blockType = $this->getBlockTypeById($blockTypeRecord->id);

            if (!$blockType) {
                return;
            }

            // First delete the blocks of this type
            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                $blocks = SuperTableBlockElement::find()
                    ->siteId($siteId)
                    ->typeId($blockType->id)
                    ->all();

                foreach ($blocks as $block) {
                    Craft::$app->getElements()->deleteElement($block);
                }
            }

            // Set the new contentTable
            $contentService = Craft::$app->getContent();
            $fieldsService = Craft::$app->getFields();
            $originalContentTable = $contentService->contentTable;

            /** @var SuperTableField $superTableField */
            $superTableField = $fieldsService->getFieldById($blockType->fieldId);
            $contentService->contentTable = $superTableField->contentTable;

            // Set the new fieldColumnPrefix
            $originalFieldColumnPrefix = Craft::$app->getContent()->fieldColumnPrefix;
            Craft::$app->getContent()->fieldColumnPrefix = 'field_';

            // Now delete the block type fields
            foreach ($blockType->getFields() as $field) {
                Craft::$app->getFields()->deleteField($field);
            }

            // Restore the contentTable and the fieldColumnPrefix to original values.
            Craft::$app->getContent()->fieldColumnPrefix = $originalFieldColumnPrefix;
            $contentService->contentTable = $originalContentTable;

            // Delete the field layout
            $fieldLayoutId = (new Query())
                ->select(['fieldLayoutId'])
                ->from(['{{%supertableblocktypes}}'])
                ->where(['id' => $blockTypeRecord->id])
                ->scalar();

            // Delete the field layout
            Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);

            // Finally delete the actual block type
            $db->createCommand()
                ->delete('{{%supertableblocktypes}}', ['id' => $blockTypeRecord->id])
                ->execute();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        unset(
            $this->_blockTypesById[$blockTypeRecord->id],
            $this->_blockTypesByFieldId[$blockTypeRecord->fieldId],
            $this->_blockTypeRecordsById[$blockTypeRecord->id]
        );
        $this->_fetchedAllBlockTypesForFieldId[$blockTypeRecord->fieldId] = false;
    }

    /**
     * Validates a Super Table field's settings.
     *
     * If the settings don’t validate, any validation errors will be stored on the settings model.
     *
     * @param SuperTableField $supertableField The Super Table field
     *
     * @return bool Whether the settings validated.
     */
    public function validateFieldSettings(SuperTableField $supertableField): bool
    {
        $validates = true;

        foreach ($supertableField->getBlockTypes() as $blockType) {
            if (!$this->validateBlockType($blockType, false)) {
                $validates = false;

                $blockTypeErrors = $blockType->getErrors();

                // Make sure to look at validation for each field
                if (!$blockTypeErrors) {
                    foreach ($blockType->getFields() as $blockTypeField) {
                        $blockTypeFieldErrors = $blockTypeField->getErrors();

                        if ($blockTypeFieldErrors) {
                            $blockTypeErrors[] = $blockTypeFieldErrors;
                        }
                    }
                }

                // Make sure to add any errors to the actual Super Table field. Really important when its
                // being nested in a Matrix field, because Matrix checks for the presence of errors - not the result
                // of this function (which correctly returns false).
                $supertableField->addErrors([ $blockType->id => $blockTypeErrors ]);
            }
        }

        return $validates;
    }

    /**
     * Saves a Super Table field's settings.
     *
     * @param SuperTableField $supertableField The Super Table field
     * @param bool        $validate    Whether the settings should be validated before being saved.
     *
     * @return bool Whether the settings saved successfully.
     * @throws \Throwable if reasons
     */
    public function saveSettings(SuperTableField $supertableField, bool $validate = true): bool
    {
        if (!$supertableField->contentTable) {
            // Silently fail if this is a migration or console request
            $request = Craft::$app->getRequest();

            if ($request->getIsConsoleRequest() || $request->getUrl() == '/actions/update/updateDatabase') {
                return true;
            }

            throw new Exception('Unable to save a Super Table field’s settings without knowing its content table: ' . $supertableField->contentTable);
        }

        if ($validate && !$this->validateFieldSettings($supertableField)) {
            return false;
        }


        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Do we need to create/rename the content table?
            if (!$db->tableExists($supertableField->contentTable)) {
                $oldContentTable = $supertableField->oldSettings['contentTable'] ?? null;

                if ($oldContentTable && $db->tableExists($oldContentTable)) {
                    MigrationHelper::renameTable($oldContentTable, $supertableField->contentTable);
                } else {
                    $this->_createContentTable($supertableField->contentTable);
                }
            }

            if (!Craft::$app->getProjectConfig()->areChangesPending(self::CONFIG_BLOCKTYPE_KEY)) {
                // Delete the old block types first, in case there's a handle conflict with one of the new ones
                $oldBlockTypes = $this->getBlockTypesByFieldId($supertableField->id);
                $oldBlockTypesById = [];
                
                foreach ($oldBlockTypes as $blockType) {
                    $oldBlockTypesById[$blockType->id] = $blockType;
                }
                
                foreach ($supertableField->getBlockTypes() as $blockType) {
                    if (!$blockType->getIsNew()) {
                        unset($oldBlockTypesById[$blockType->id]);
                    }
                }
                
                foreach ($oldBlockTypesById as $blockType) {
                    $this->deleteBlockType($blockType);
                }
                
                $originalContentTable = Craft::$app->getContent()->contentTable;
                Craft::$app->getContent()->contentTable = $supertableField->contentTable;
                
                foreach ($supertableField->getBlockTypes() as $blockType) {
                    $blockType->fieldId = $supertableField->id;
                    $this->saveBlockType($blockType, false);
                }

                Craft::$app->getContent()->contentTable = $originalContentTable;
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        unset(
            $this->_blockTypesByFieldId[$supertableField->id],
            $this->_fetchedAllBlockTypesForFieldId[$supertableField->id]
        );

        return true;
    }

    /**
     * Deletes a Super Table field.
     *
     * @param SuperTableField $supertableField The Super Table field.
     *
     * @return bool Whether the field was deleted successfully.
     * @throws \Throwable
     */
    public function deleteSuperTableField(SuperTableField $supertableField): bool
    {
        // Clear the schema cache
        $db = Craft::$app->getDb();
        $db->getSchema()->refresh();

        $transaction = $db->beginTransaction();
        try {
            $originalContentTable = Craft::$app->getContent()->contentTable;
            Craft::$app->getContent()->contentTable = $supertableField->contentTable;

            // Delete the block types
            $blockTypes = $this->getBlockTypesByFieldId($supertableField->id);

            foreach ($blockTypes as $blockType) {
                $this->deleteBlockType($blockType);
            }

            // Drop the content table
            $db->createCommand()
                ->dropTable($supertableField->contentTable)
                ->execute();

            Craft::$app->getContent()->contentTable = $originalContentTable;

            $transaction->commit();

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Returns the content table name for a given Super Table field.
     *
     * @param SuperTableField $supertableField  The Super Table field.
     * @return string|false The table name, or `false` if $useOldHandle was set to `true` and there was no old handle.
     */
    public function getContentTableName(SuperTableField $supertableField)
    {
        return $supertableField->contentTable;
    }

    /**
     * Defines a new Super Table content table name.
     *
     * @param SuperTableField $field
     * @return string
     */
    public function defineContentTableName(SuperTableField $field): string
    {
        $baseName = 'stc_' . strtolower($field->handle);
        $db = Craft::$app->getDb();
        $i = -1;

        do {
            $i++;

            $parentFieldId = '';

            // Check if this field is inside a Matrix - we need to prefix this content table if so.
            if ($field->context != 'global') {
                $parentFieldContext = explode(':', $field->context);

                if ($parentFieldContext[0] == 'matrixBlockType') {
                    $parentFieldUid = $parentFieldContext[1];
                    $parentFieldId = Db::idByUid('{{%matrixblocktypes}}', $parentFieldUid);
                }
            }
        
            if ($parentFieldId) {
                $baseName = 'stc_' . $parentFieldId . '_' . strtolower($field->handle);
            }

            $name = '{{%' . $baseName . ($i !== 0 ? '_' . $i : '') . '}}';

        } while ($name !== $field->contentTable && $db->tableExists($name));

        return $name;
    }

    /**
     * Returns a block by its ID.
     *
     * @param int      $blockId The Super Table block’s ID.
     * @param int|null $siteId  The site ID to return. Defaults to the current site.
     *
     * @return SuperTableBlockElement|null The Super Table block, or `null` if it didn’t exist.
     */
    public function getBlockById(int $blockId, int $siteId = null)
    {
        /** @var SuperTableBlockElement|null $block */
        return Craft::$app->getElements()->getElementById($blockId, SuperTableBlockElement::class, $siteId);
    }

    /**
     * Saves a Super Table field.
     *
     * @param SuperTableField      $field The Super Table field
     * @param ElementInterface $owner The element the field is associated with
     * @param bool $checkOtherSites Whether to check other sites if the owner was just duplicated
     *
     * @throws \Throwable if reasons
     */
    public function saveField(SuperTableField $field, ElementInterface $owner, $checkOtherSites = false)
    {
        /** @var Element $owner */
        $elementsService = Craft::$app->getElements();
        /** @var MatrixBlockQuery $query */
        $query = $owner->getFieldValue($field->handle);
        /** @var SuperTableBlockElement[] $blocks */
        $blocks = $query->getCachedResult() ?? (clone $query)->anyStatus()->all();
        $blockIds = [];
        $collapsedBlockIds = [];

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            foreach ($blocks as $block) {
                $block->ownerId = $owner->id;
                $elementsService->saveElement($block, false);
                $blockIds[] = $block->id;
            }

            // Delete any blocks that shouldn't be there anymore
            $this->_deleteOtherBlocks($field, $owner, $blockIds);

            // Should we duplicate the blocks to other sites?
            if (
                $field->propagationMethod !== SuperTableField::PROPAGATION_METHOD_ALL &&
                ($owner->propagateAll || !empty($owner->newSiteIds))
            ) {
                // Find the owner's site IDs that *aren't* supported by this site's SuperTable blocks
                $ownerSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($owner), 'siteId');
                $fieldSiteIds = $this->getSupportedSiteIdsForField($field, $owner);
                $otherSiteIds = array_diff($ownerSiteIds, $fieldSiteIds);

                // If propagateAll isn't set, only deal with sites that the element was just propagated to for the first time
                if (!$owner->propagateAll) {
                    $otherSiteIds = array_intersect($otherSiteIds, $owner->newSiteIds);
                }

                if (!empty($otherSiteIds)) {
                    // Get the original element and duplicated element for each of those sites
                    /** @var Element[] $otherTargets */
                    $otherTargets = $owner::find()
                        ->drafts($owner->getIsDraft())
                        ->revisions($owner->getIsRevision())
                        ->id($owner->id)
                        ->siteId($otherSiteIds)
                        ->anyStatus()
                        ->all();

                    // Duplicate SuperTable blocks, ensuring we don't process the same blocks more than once
                    $handledSiteIds = [];

                    $cachedQuery = (clone $query)->anyStatus();
                    $cachedQuery->setCachedResult($blocks);
                    $owner->setFieldValue($field->handle, $cachedQuery);
                    
                    foreach ($otherTargets as $otherTarget) {
                        // Make sure we haven't already duplicated blocks for this site, via propagation from another site
                        if (isset($handledSiteIds[$otherTarget->siteId])) {
                            continue;
                        }

                        $this->duplicateBlocks($field, $owner, $otherTarget);

                        // Make sure we don't duplicate blocks for any of the sites that were just propagated to
                        $sourceSupportedSiteIds = $this->getSupportedSiteIdsForField($field, $otherTarget);
                        $handledSiteIds = array_merge($handledSiteIds, array_flip($sourceSupportedSiteIds));
                    }

                    $owner->setFieldValue($field->handle, $query);
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Duplicates SuperTable blocks from one owner element to another.
     *
     * @param SuperTableField $field The SuperTable field to duplicate blocks for
     * @param ElementInterface $source The source element blocks should be duplicated from
     * @param ElementInterface $target The target element blocks should be duplicated to
     * @param bool $checkOtherSites Whether to duplicate blocks for the source element's other supported sites
     * @throws \Throwable if reasons
     */
    public function duplicateBlocks(SuperTableField $field, ElementInterface $source, ElementInterface $target, bool $checkOtherSites = false)
    {
        /** @var Element $source */
        /** @var Element $target */
        $elementsService = Craft::$app->getElements();
        /** @var SuperTableBlockQuery $query */
        $query = $source->getFieldValue($field->handle);
        /** @var SuperTableBlockElement[] $blocks */
        $blocks = $query->getCachedResult() ?? (clone $query)->anyStatus()->all();
        $newBlockIds = [];
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            foreach ($blocks as $block) {
                /** @var SuperTableBlockElement $newBlock */
                $newBlock = $elementsService->duplicateElement($block, [
                    'ownerId' => $target->id,
                    'owner' => $target,
                    'siteId' => $target->siteId,
                    'propagating' => false,
                ]);

                $newBlockIds[] = $newBlock->id;
            }

            // Delete any blocks that shouldn't be there anymore
            $this->_deleteOtherBlocks($field, $target, $newBlockIds);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Duplicate blocks for other sites as well?
        if ($checkOtherSites && $field->propagationMethod !== SuperTableField::PROPAGATION_METHOD_ALL) {
            // Find the target's site IDs that *aren't* supported by this site's SuperTable blocks
            $targetSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($target), 'siteId');
            $fieldSiteIds = $this->getSupportedSiteIdsForField($field, $target);
            $otherSiteIds = array_diff($targetSiteIds, $fieldSiteIds);

            if (!empty($otherSiteIds)) {
                // Get the original element and duplicated element for each of those sites
                /** @var Element[] $otherSources */
                $otherSources = $target::find()
                    ->drafts($source->getIsDraft())
                    ->revisions($source->getIsRevision())
                    ->id($source->id)
                    ->siteId($otherSiteIds)
                    ->anyStatus()
                    ->all();

                /** @var Element[] $otherTargets */
                $otherTargets = $target::find()
                    ->drafts($target->getIsDraft())
                    ->revisions($target->getIsRevision())
                    ->id($target->id)
                    ->siteId($otherSiteIds)
                    ->anyStatus()
                    ->indexBy('siteId')
                    ->all();

                // Duplicate SuperTable blocks, ensuring we don't process the same blocks more than once
                $handledSiteIds = [];

                foreach ($otherSources as $otherSource) {
                    // Make sure the target actually exists for this site
                    if (!isset($otherTargets[$otherSource->siteId])) {
                        continue;
                    }

                    // Make sure we haven't already duplicated blocks for this site, via propagation from another site
                    if (isset($handledSiteIds[$otherSource->siteId])) {
                        continue;
                    }

                    $this->duplicateBlocks($field, $otherSource, $otherTargets[$otherSource->siteId]);

                    // Make sure we don't duplicate blocks for any of the sites that were just propagated to
                    $sourceSupportedSiteIds = $this->getSupportedSiteIdsForField($field, $otherSource);
                    $handledSiteIds = array_merge($handledSiteIds, array_flip($sourceSupportedSiteIds));
                }
            }
        }
    }

    /**
     * Returns the site IDs that are supported by SuperTable blocks for the given SuperTable field and owner element.
     *
     * @param SuperTableField $field
     * @param ElementInterface $owner
     * @return int[]
     */
    public function getSupportedSiteIdsForField(SuperTableField $field, ElementInterface $owner): array
    {
        /** @var Element $owner */
        /** @var Site[] $allSites */
        $allSites = ArrayHelper::index(Craft::$app->getSites()->getAllSites(), 'id');
        $ownerSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($owner), 'siteId');
        $siteIds = [];

        foreach ($ownerSiteIds as $siteId) {
            switch ($field->propagationMethod) {
                case SuperTableField::PROPAGATION_METHOD_NONE:
                    $include = $siteId == $owner->siteId;
                    break;
                case SuperTableField::PROPAGATION_METHOD_SITE_GROUP:
                    $include = $allSites[$siteId]->groupId == $allSites[$owner->siteId]->groupId;
                    break;
                case SuperTableField::PROPAGATION_METHOD_LANGUAGE:
                    $include = $allSites[$siteId]->language == $allSites[$owner->siteId]->language;
                    break;
                default:
                    $include = true;
                    break;
            }

            if ($include) {
                $siteIds[] = $siteId;
            }
        }

        return $siteIds;
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving block types.
     *
     * @return Query
     */
    private function _createBlockTypeQuery(): Query
    {
        return (new Query())
            ->select([
                'bt.id',
                'bt.fieldId',
                'bt.fieldLayoutId',
                'bt.uid'
            ])
            ->from(['bt' => '{{%supertableblocktypes}}']);
    }

    /**
     * Returns a block type record by its ID or creates a new one.
     *
     * @param SuperTableBlockTypeModel $blockType
     *
     * @return SuperTableBlockTypeRecord
     * @throws SuperTableBlockTypeNotFoundException if $blockType->id is invalid
     */
    private function _getBlockTypeRecord($blockType): SuperTableBlockTypeRecord
    {
        if (is_string($blockType)) {
            $blockTypeRecord = SuperTableBlockTypeRecord::findOne(['uid' => $blockType]) ?? new SuperTableBlockTypeRecord();

            if (!$blockTypeRecord->getIsNewRecord()) {
                $this->_blockTypeRecordsById[$blockTypeRecord->id] = $blockTypeRecord;
            }

            return $blockTypeRecord;
        }

        if ($blockType->getIsNew()) {
            return new SuperTableBlockTypeRecord();
        }

        if (isset($this->_blockTypeRecordsById[$blockType->id])) {
            return $this->_blockTypeRecordsById[$blockType->id];
        }

        $blockTypeRecord = SuperTableBlockTypeRecord::findOne($blockType->id);

        if ($blockTypeRecord === null) {
            throw new SuperTableBlockTypeNotFoundException('Invalid block type ID: ' . $blockType->id);
        }

        return $this->_blockTypeRecordsById[$blockType->id] = $blockTypeRecord;
    }

    /**
     * Creates the content table for a Super Table field.
     *
     * @param string $tableName
     *
     * @return void
     */
    private function _createContentTable(string $tableName)
    {
        $migration = new CreateSuperTableContentTable([
            'tableName' => $tableName
        ]);

        ob_start();
        $migration->up();
        ob_end_clean();
    }

    /**
     * Deletes blocks from an owner element
     *
     * @param SuperTableField $field The SuperTable field
     * @param ElementInterface The owner element
     * @param int[] $except Block IDs that should be left alone
     */
    private function _deleteOtherBlocks(SuperTableField $field, ElementInterface $owner, array $except)
    {
        /** @var Element $owner */
        $deleteBlocks = SuperTableBlockElement::find()
            ->anyStatus()
            ->ownerId($owner->id)
            ->fieldId($field->id)
            ->siteId($owner->siteId)
            ->andWhere(['not', ['elements.id' => $except]])
            ->all();

        $elementsService = Craft::$app->getElements();

        foreach ($deleteBlocks as $deleteBlock) {
            $elementsService->deleteElement($deleteBlock);
        }
    }
}
