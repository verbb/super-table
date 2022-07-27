<?php
namespace verbb\supertable\services;

use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\errors\SuperTableBlockTypeNotFoundException;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\migrations\CreateSuperTableContentTable;
use verbb\supertable\models\SuperTableBlockType;
use verbb\supertable\records\SuperTableBlockType as SuperTableBlockTypeRecord;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\events\ConfigEvent;
use craft\fields\Matrix;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\Site;

use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidArgumentException;

use Throwable;

use Illuminate\Support\Collection;

class Service extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var SuperTableBlockType[]|null[]
     */
    private array $_blockTypesById = [];

    /**
     * @var SuperTableBlockType[][]
     */
    private array $_blockTypesByFieldId = [];

    /**
     * @var bool[]
     */
    private array $_fetchedAllBlockTypesForFieldId = [];

    /**
     * @var SuperTableBlockTypeRecord[]
     */
    private array $_blockTypeRecordsById = [];

    public const CONFIG_BLOCKTYPE_KEY = 'superTableBlockTypes';


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
            $blockType = new SuperTableBlockType($result);
            $this->_blockTypesById[$blockType->id] = $blockType;
            $this->_blockTypesByFieldId[$fieldId][] = $blockType;
        }

        $this->_fetchedAllBlockTypesForFieldId[$fieldId] = true;

        return $this->_blockTypesByFieldId[$fieldId];
    }

    /**
     * Returns all the block types.
     *
     * @return SuperTableBlockType[] An array of block types.
     */
    public function getAllBlockTypes(): array
    {
        $results = $this->_createBlockTypeQuery()
            ->innerJoin(['f' => Table::FIELDS], '[[f.id]] = [[bt.fieldId]]')
            ->where(['f.type' => SuperTableField::class])
            ->all();

        foreach ($results as $key => $result) {
            $results[$key] = new SuperTableBlockType($result);
        }

        return $results;
    }

    /**
     * Returns a block type by its ID.
     *
     * @param int $blockTypeId The block type ID.
     *
     * @return SuperTableBlockType|null The block type, or `null` if it didn’t exist.
     */
    public function getBlockTypeById(int $blockTypeId): ?SuperTableBlockType
    {
        if (array_key_exists($blockTypeId, $this->_blockTypesById)) {
            return $this->_blockTypesById[$blockTypeId];
        }

        $result = $this->_createBlockTypeQuery()
            ->where(['bt.id' => $blockTypeId])
            ->one();

        return $this->_blockTypesById[$blockTypeId] = $result ? new SuperTableBlockType($result) : null;
    }

    /**
     * Validates a block type.
     *
     * If the block type doesn’t validate, any validation errors will be stored on the block type.
     *
     * @param SuperTableBlockType $blockType The block type.
     * @param bool $validateUniques Whether the Name and Handle attributes should be validated to
     *                                              ensure they’re unique. Defaults to `true`.
     *
     * @return bool Whether the block type validated.
     */
    public function validateBlockType(SuperTableBlockType $blockType, bool $validateUniques = true): bool
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
        // it's because its being embedded in another field (Matrix). Thus, we need to reset unique field handles, because they
        // can be different across multiple parent fields.
        $_uniqueFieldHandles = [];

        // Can't validate multiple new rows at once so we'll need to give these temporary context to avoid false unique
        // handle validation errors, and just validate those manually. Also apply the future fieldColumnPrefix so that
        // field handle validation takes its length into account.
        $contentService = Craft::$app->getContent();
        $originalFieldContext = $contentService->fieldContext;
        $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;

        $contentService->fieldContext = StringHelper::randomString(10);
        $contentService->fieldColumnPrefix = 'field_';

        foreach ($blockType->getCustomFields() as $field) {
            $field->validate();

            if ($field->handle) {
                if (in_array($field->handle, $_uniqueFieldHandles, true)) {
                    // This error *might* not be entirely accurate, but it's such an edge case that it's probably better
                    // for the error to be worded for the common problem (two duplicate handles within the same block
                    // type).
                    $error = Craft::t('yii', '{attribute} "{value}" has already been taken.', [
                        'attribute' => Craft::t('app', 'Handle'),
                        'value' => $field->handle,
                    ]);

                    $field->addError('handle', $error);
                } else {
                    $_uniqueFieldHandles[] = $field->handle;
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

                $field->addErrors(['handle' => Craft::t('app', '“{handle}” is a reserved word.', ['handle' => $field->handle])]);
            }

            // Special-case for validating child Matrix fields
            if ($field::class == Matrix::class) {
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
     * @param SuperTableBlockType $blockType The block type to be saved.
     * @param bool $runValidation Whether the block type should be validated before being saved.
     * Defaults to `true`.
     * @throws Exception if an error occurs when saving the block type
     * @throws Throwable if reasons
     */
    public function saveBlockType(SuperTableBlockType $blockType, bool $runValidation = true): bool
    {
        if ($runValidation && !$blockType->validate()) {
            return false;
        }

        $isNewBlockType = $blockType->getIsNew();
        $configPath = self::CONFIG_BLOCKTYPE_KEY . '.' . $blockType->uid;
        $configData = $blockType->getConfig();
        $field = $blockType->getField();

        Craft::$app->getProjectConfig()->set($configPath, $configData, "Save super table block type for parent field “{$field->handle}”");

        if ($isNewBlockType) {
            $blockType->id = Db::idByUid('{{%supertableblocktypes}}', $blockType->uid);
        }

        return true;
    }

    /**
     * Handle block type change
     */
    public function handleChangedBlockType(ConfigEvent $event): void
    {
        $blockTypeUid = $event->tokenMatches[0];
        $data = $event->newValue;
        $previousData = $event->oldValue;

        // Make sure the field has been synced
        $fieldId = Db::idByUid(Table::FIELDS, $data['field']);
        if ($fieldId === null) {
            Craft::$app->getProjectConfig()->defer($event, [$this, __FUNCTION__]);
            return;
        }

        // Ensure any Matrix blocks are processed first, in the case of M-ST-M fields
        Craft::$app->getProjectConfig()->processConfigChanges('matrixBlockTypes', false);

        $fieldsService = Craft::$app->getFields();
        $contentService = Craft::$app->getContent();

        $transaction = Craft::$app->getDb()->beginTransaction();

        // Store the current contexts.
        $originalContentTable = $contentService->contentTable;
        $originalFieldContext = $contentService->fieldContext;
        $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;
        $originalOldFieldColumnPrefix = $fieldsService->oldFieldColumnPrefix;

        try {
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

            // Ignore it, if the parent field is not a SuperTable field.
            if ($superTableField instanceof SuperTableField) {
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

                if (
                    !empty($data['fieldLayouts']) &&
                    ($layoutConfig = reset($data['fieldLayouts']))
                ) {
                    // Save the field layout
                    $layout = FieldLayout::createFromConfig($layoutConfig);
                    $layout->id = $blockTypeRecord->fieldLayoutId;
                    $layout->type = SuperTableBlockElement::class;
                    $layout->uid = key($data['fieldLayouts']);

                    $fieldsService->saveLayout($layout, false);
                    $blockTypeRecord->fieldLayoutId = $layout->id;
                } else if ($blockTypeRecord->fieldLayoutId) {
                    // Delete the field layout
                    $fieldsService->deleteLayoutById($blockTypeRecord->fieldLayoutId);
                    $blockTypeRecord->fieldLayoutId = null;
                }

                // Save it
                $blockTypeRecord->save(false);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Restore the previous contexts.
        $contentService->fieldContext = $originalFieldContext;
        $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;
        $contentService->contentTable = $originalContentTable;
        $fieldsService->oldFieldColumnPrefix = $originalOldFieldColumnPrefix;

        // Clear caches
        unset(
            $this->_blockTypesById[$blockTypeRecord->id],
            $this->_blockTypesByFieldId[$blockTypeRecord->fieldId]
        );
        $this->_fetchedAllBlockTypesForFieldId[$blockTypeRecord->fieldId] = false;

        // Invalidate Super Table block caches
        Craft::$app->getElements()->invalidateCachesForElementType(SuperTableBlockElement::class);
    }

    /**
     * Deletes a block type.
     *
     * @param SuperTableBlockType $blockType The block type.
     * @return bool Whether the block type was deleted successfully.
     */
    public function deleteBlockType(SuperTableBlockType $blockType): bool
    {
        Craft::$app->getProjectConfig()->remove(self::CONFIG_BLOCKTYPE_KEY . '.' . $blockType->uid, "Delete super table block type for parent field “{$blockType->getField()->handle}”");

        return true;
    }

    /**
     * Handle block type change
     *
     * @throws Throwable if reasons
     */
    public function handleDeletedBlockType(ConfigEvent $event): void
    {
        $blockTypeUid = $event->tokenMatches[0];
        $blockTypeRecord = $this->_getBlockTypeRecord($blockTypeUid);

        if (!$blockTypeRecord->id) {
            return;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

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

            // Ignore it, if the parent field is not a Super Table field.
            if ($superTableField instanceof SuperTableField) {
                $contentService->contentTable = $superTableField->contentTable;

                // Set the new fieldColumnPrefix + oldFieldColumnPrefix
                $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;
                $originalOldFieldColumnPrefix = $fieldsService->oldFieldColumnPrefix;

                $contentService->fieldColumnPrefix = "field_";
                $fieldsService->oldFieldColumnPrefix = "field_";

                // Now delete the block type fields
                foreach ($blockType->getCustomFields() as $field) {
                    $fieldsService->deleteField($field);
                }

                // Restore the contentTable and the fieldColumnPrefix to original values.
                $contentService->contentTable = $originalContentTable;
                $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;
                $fieldsService->oldFieldColumnPrefix = $originalOldFieldColumnPrefix;


                // Delete the field layout
                $fieldLayoutId = (new Query())
                    ->select(['fieldLayoutId'])
                    ->from(['{{%supertableblocktypes}}'])
                    ->where(['id' => $blockTypeRecord->id])
                    ->scalar();

                // Delete the field layout
                if ($fieldLayoutId) {
                    $fieldsService->deleteLayoutById($fieldLayoutId);
                }

                // Delete the actual block type
                Db::delete('{{%supertableblocktypes}}', [
                    'id' => $blockTypeRecord->id,
                ]);
            }

            $transaction->commit();
        } catch (Throwable $e) {
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

        // Invalidate Super Table block caches
        Craft::$app->getElements()->invalidateCachesForElementType(SuperTableBlockElement::class);
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
                    foreach ($blockType->getCustomFields() as $blockTypeField) {
                        $blockTypeFieldErrors = $blockTypeField->getErrors();

                        if ($blockTypeFieldErrors) {
                            $blockTypeErrors[] = $blockTypeFieldErrors;
                        }
                    }
                }

                // Make sure to add any errors to the actual Super Table field. Really important when its
                // being nested in a Matrix field, because Matrix checks for the presence of errors - not the result
                // of this function (which correctly returns false).
                $supertableField->addErrors([$blockType->id => $blockTypeErrors]);
            }
        }

        return $validates;
    }

    /**
     * Saves a Super Table field's settings.
     *
     * @param SuperTableField $supertableField The Super Table field
     * @param bool $validate Whether the settings should be validated before being saved.
     *
     * @return bool Whether the settings saved successfully.
     * @throws Throwable if reasons
     */
    public function saveSettings(SuperTableField $supertableField, bool $validate = true): bool
    {
        if (!$supertableField->contentTable) {
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
                    Db::renameTable($oldContentTable, $supertableField->contentTable);
                } else {
                    $this->_createContentTable($supertableField->contentTable);
                }
            }

            // Only make block type changes if we're not in the middle of applying YAML changes
            if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
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
        } catch (Throwable $e) {
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
     * @throws Throwable
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
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Defines a new Super Table content table name.
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
     * @param int $blockId The Super Table block’s ID.
     * @param int|null $siteId The site ID to return. Defaults to the current site.
     *
     * @return SuperTableBlockElement|null The Super Table block, or `null` if it didn’t exist.
     */
    public function getBlockById(int $blockId, int $siteId = null): ?SuperTableBlockElement
    {
        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return Craft::$app->getElements()->getElementById($blockId, SuperTableBlockElement::class, $siteId);
    }

    /**
     * Saves a Super Table field.
     *
     * @param SuperTableField $field The Super Table field
     * @param ElementInterface $owner The element the field is associated with
     *
     * @throws Throwable if reasons
     */
    public function saveField(SuperTableField $field, ElementInterface $owner): void
    {
        $elementsService = Craft::$app->getElements();

        /** @var SuperTableBlockQuery|Collection $value */
        $value = $owner->getFieldValue($field->handle);

        if ($value instanceof Collection) {
            $blocks = $value->all();
            $saveAll = true;
        } else {
            $blocks = $value->getCachedResult();
            if ($blocks !== null) {
                $saveAll = false;
            } else {
                $blocks = (clone $value)->status(null)->all();
                $saveAll = true;
            }
        }

        $blockIds = [];
        $sortOrder = 0;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            foreach ($blocks as $block) {
                $sortOrder++;
                if ($saveAll || !$block->id || $block->dirty) {
                    $block->primaryOwnerId = $block->ownerId = $owner->id;
                    $block->sortOrder = $sortOrder;
                    $elementsService->saveElement($block, false);

                    // If this is a draft, we can shed the draft data now
                    if ($block->getIsDraft()) {
                        $canonicalBlockId = $block->getCanonicalId();
                        Craft::$app->getDrafts()->removeDraftData($block);
                        Db::delete('{{%supertableblocks_owners}}', [
                            'blockId' => $canonicalBlockId,
                            'ownerId' => $owner->id,
                        ]);
                    }
                } else if ((int)$block->sortOrder !== $sortOrder) {
                    // Just update its sortOrder
                    $block->sortOrder = $sortOrder;
                    Db::update('{{%supertableblocks_owners}}', [
                        'sortOrder' => $sortOrder,
                    ], [
                        'blockId' => $block->id,
                        'ownerId' => $owner->id,
                    ], [], false);
                }

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
                $fieldSiteIds = $this->getSupportedSiteIds($field->propagationMethod, $owner, $field->propagationKeyFormat);
                $otherSiteIds = array_diff($ownerSiteIds, $fieldSiteIds);

                // If propagateAll isn't set, only deal with sites that the element was just propagated to for the first time
                if (!$owner->propagateAll) {
                    $preexistingOtherSiteIds = array_diff($otherSiteIds, $owner->newSiteIds);
                    $otherSiteIds = array_intersect($otherSiteIds, $owner->newSiteIds);
                } else {
                    $preexistingOtherSiteIds = [];
                }

                if (!empty($otherSiteIds)) {
                    // Get the owner element across each of those sites
                    $localizedOwners = $owner::find()
                        ->drafts($owner->getIsDraft())
                        ->provisionalDrafts($owner->isProvisionalDraft)
                        ->revisions($owner->getIsRevision())
                        ->id($owner->id)
                        ->siteId($otherSiteIds)
                        ->status(null)
                        ->all();

                    // Duplicate SuperTable blocks, ensuring we don't process the same blocks more than once
                    $handledSiteIds = [];

                    if ($value instanceof SuperTableBlockQuery) {
                        $cachedQuery = (clone $value)->status(null);
                        $cachedQuery->setCachedResult($blocks);
                        $owner->setFieldValue($field->handle, $cachedQuery);
                    }

                    foreach ($localizedOwners as $localizedOwner) {
                        // Make sure we haven't already duplicated blocks for this site, via propagation from another site
                        if (isset($handledSiteIds[$localizedOwner->siteId])) {
                            continue;
                        }

                        // Find all the field’s supported sites shared with this target
                        $sourceSupportedSiteIds = $this->getSupportedSiteIds($field->propagationMethod, $localizedOwner, $field->propagationKeyFormat);

                        // Do blocks in this target happen to share supported sites with a preexisting site?
                        if (
                            !empty($preexistingOtherSiteIds) &&
                            !empty($sharedPreexistingOtherSiteIds = array_intersect($preexistingOtherSiteIds, $sourceSupportedSiteIds)) &&
                            $preexistingLocalizedOwner = $owner::find()
                                ->drafts($owner->getIsDraft())
                                ->provisionalDrafts($owner->isProvisionalDraft)
                                ->revisions($owner->getIsRevision())
                                ->id($owner->id)
                                ->siteId($sharedPreexistingOtherSiteIds)
                                ->status(null)
                                ->one()
                        ) {
                            // Just resave SuperTable blocks for that one site, and let them propagate over to the new site(s) from there
                            $this->saveField($field, $preexistingLocalizedOwner);
                        } else {
                            $this->duplicateBlocks($field, $owner, $localizedOwner);
                        }

                        // Make sure we don't duplicate blocks for any of the sites that were just propagated to
                        $handledSiteIds = array_merge($handledSiteIds, array_flip($sourceSupportedSiteIds));
                    }

                    if ($value instanceof SuperTableBlockQuery) {
                        $owner->setFieldValue($field->handle, $value);
                    }
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
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
     * @throws Throwable if reasons
     */
    public function duplicateBlocks(SuperTableField $field, ElementInterface $source, ElementInterface $target, bool $checkOtherSites = false, bool $deleteOtherBlocks = true): void
    {
        $elementsService = Craft::$app->getElements();

        /** @var SuperTableBlockQuery|Collection $value */
        $value = $source->getFieldValue($field->handle);

        if ($value instanceof Collection) {
            $blocks = $value->all();
        } else {
            $blocks = $value->getCachedResult() ?? (clone $value)->status(null)->all();
        }

        $newBlockIds = [];

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            foreach ($blocks as $block) {
                $newAttributes = [
                    // Only set the canonicalId if the target owner element is a derivative
                    'canonicalId' => $target->getIsDerivative() ? $block->id : null,
                    'primaryOwnerId' => $target->id,
                    'owner' => $target,
                    'siteId' => $target->siteId,
                    'propagating' => false,
                ];

                if ($target->updatingFromDerivative && $block->getIsDerivative()) {
                    if (
                        ElementHelper::isRevision($source) ||
                        !empty($target->newSiteIds) ||
                        (!$source::trackChanges() || $source->isFieldModified($field->handle, true))
                    ) {
                        $newBlockId = $elementsService->updateCanonicalElement($block, $newAttributes)->id;
                    } else {
                        $newBlockId = $block->getCanonicalId();
                    }
                } elseif ($block->primaryOwnerId === $target->id) {
                    // Only the block ownership was duplicated, so just update its sort order for the target element
                    Db::update('{{%supertableblocks_owners}}', [
                        'sortOrder' => $block->sortOrder,
                    ], ['blockId' => $block->id, 'ownerId' => $target->id], updateTimestamp: false);
                    $newBlockId = $block->id;
                } else {
                    $newBlockId = $elementsService->duplicateElement($block, $newAttributes)->id;
                }

                $newBlockIds[] = $newBlockId;
            }

            if ($deleteOtherBlocks) {
                // Delete any blocks that shouldn't be there anymore
                $this->_deleteOtherBlocks($field, $target, $newBlockIds);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Duplicate blocks for other sites as well?
        if ($checkOtherSites && $field->propagationMethod !== SuperTableField::PROPAGATION_METHOD_ALL) {
            // Find the target's site IDs that *aren't* supported by this site's SuperTable blocks
            $targetSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($target), 'siteId');
            $fieldSiteIds = $this->getSupportedSiteIds($field->propagationMethod, $target, $field->propagationKeyFormat);
            $otherSiteIds = array_diff($targetSiteIds, $fieldSiteIds);

            if (!empty($otherSiteIds)) {
                // Get the original element and duplicated element for each of those sites
                $otherSources = $target::find()
                    ->drafts($source->getIsDraft())
                    ->provisionalDrafts($source->isProvisionalDraft)
                    ->revisions($source->getIsRevision())
                    ->id($source->id)
                    ->siteId($otherSiteIds)
                    ->status(null)
                    ->all();
                $otherTargets = $target::find()
                    ->drafts($target->getIsDraft())
                    ->provisionalDrafts($target->isProvisionalDraft)
                    ->revisions($target->getIsRevision())
                    ->id($target->id)
                    ->siteId($otherSiteIds)
                    ->status(null)
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
                    if (in_array($otherSource->siteId, $handledSiteIds, false)) {
                        continue;
                    }

                    $otherTargets[$otherSource->siteId]->updatingFromDerivative = $target->updatingFromDerivative;
                    $this->duplicateBlocks($field, $otherSource, $otherTargets[$otherSource->siteId]);

                    // Make sure we don't duplicate blocks for any of the sites that were just propagated to
                    $sourceSupportedSiteIds = $this->getSupportedSiteIds($field->propagationMethod, $otherSource, $field->propagationKeyFormat);
                    $handledSiteIds = array_merge($handledSiteIds, $sourceSupportedSiteIds);
                }
            }
        }
    }

    /**
     * Duplicates block ownership relations for a new draft element.
     *
     * @param SuperTableField $field The Super Table field
     * @param ElementInterface $canonical The canonical element
     * @param ElementInterface $draft The draft element
     * @since 3.0.0
     */
    public function duplicateOwnership(SuperTableField $field, ElementInterface $canonical, ElementInterface $draft): void
    {
        if (!$canonical->getIsCanonical()) {
            throw new InvalidArgumentException('The source element must be canonical.');
        }

        if (!$draft->getIsDraft()) {
            throw new InvalidArgumentException('The target element must be a draft.');
        }

        $blocksTable = '{{%supertableblocks}}';
        $ownersTable = '{{%supertableblocks_owners}}';

        Craft::$app->getDb()->createCommand(<<<SQL
INSERT INTO $ownersTable ([[blockId]], [[ownerId]], [[sortOrder]]) 
SELECT [[o.blockId]], '$draft->id', [[o.sortOrder]] 
FROM $ownersTable AS [[o]]
INNER JOIN $blocksTable AS [[b]] ON [[b.id]] = [[o.blockId]] AND [[b.primaryOwnerId]] = '$canonical->id' AND [[b.fieldId]] = '$field->id'
WHERE [[o.ownerId]] = '$canonical->id'
SQL
        )->execute();
    }

    /**
     * Creates revisions for all the blocks that belong to the given canonical element, and assigns those
     * revisions to the given owner revision.
     *
     * @param SuperTableField $field The Super Table field
     * @param ElementInterface $canonical The canonical element
     * @param ElementInterface $revision The revision element
     * @since 4.0.0
     */
    public function createRevisionBlocks(SuperTableField $field, ElementInterface $canonical, ElementInterface $revision): void
    {
        $blocks = SuperTableBlockElement::find()
            ->ownerId($canonical->id)
            ->fieldId($field->id)
            ->siteId('*')
            ->unique()
            ->status(null)
            ->all();

        $revisionsService = Craft::$app->getRevisions();
        $ownershipData = [];

        foreach ($blocks as $block) {
            $blockRevisionId = $revisionsService->createRevision($block, null, null, [
                'primaryOwnerId' => $revision->id,
                'saveOwnership' => false,
            ]);
            $ownershipData[] = [$blockRevisionId, $revision->id, $block->sortOrder];
        }

        Db::batchInsert('{{%supertableblocks_owners}}', ['blockId', 'ownerId', 'sortOrder'], $ownershipData);
    }

    /**
     * Merges recent canonical Super Table block changes into the given Super Table field’s blocks.
     *
     * @param SuperTableField $field The Super Table field
     * @param ElementInterface $owner The element the field is associated with
     */
    public function mergeCanonicalChanges(SuperTableField $field, ElementInterface $owner): void
    {
        // Get the owner across all sites
        $localizedOwners = $owner::find()
            ->id($owner->id ?: false)
            ->siteId(['not', $owner->siteId])
            ->drafts($owner->getIsDraft())
            ->provisionalDrafts($owner->isProvisionalDraft)
            ->revisions($owner->getIsRevision())
            ->status(null)
            ->ignorePlaceholders()
            ->indexBy('siteId')
            ->all();
        $localizedOwners[$owner->siteId] = $owner;

        // Get the canonical owner across all sites
        $canonicalOwners = $owner::find()
            ->id($owner->getCanonicalId())
            ->siteId(array_keys($localizedOwners))
            ->status(null)
            ->ignorePlaceholders()
            ->all();

        $elementsService = Craft::$app->getElements();
        $handledSiteIds = [];

        foreach ($canonicalOwners as $canonicalOwner) {
            if (isset($handledSiteIds[$canonicalOwner->siteId])) {
                continue;
            }

            // Get all the canonical owner’s blocks, including soft-deleted ones
            $canonicalBlocks = SuperTableBlockElement::find()
                ->fieldId($field->id)
                ->primaryOwnerId($canonicalOwner->id)
                ->siteId($canonicalOwner->siteId)
                ->status(null)
                ->trashed(null)
                ->ignorePlaceholders()
                ->all();

            // Get all the derivative owner’s blocks, so we can compare
            $derivativeBlocks = SuperTableBlockElement::find()
                ->fieldId($field->id)
                ->primaryOwnerId($owner->id)
                ->siteId($canonicalOwner->siteId)
                ->status(null)
                ->trashed(null)
                ->ignorePlaceholders()
                ->indexBy('canonicalId')
                ->all();

            foreach ($canonicalBlocks as $canonicalBlock) {
                if (isset($derivativeBlocks[$canonicalBlock->id])) {
                    $derivativeBlock = $derivativeBlocks[$canonicalBlock->id];

                    // Has it been soft-deleted?
                    if ($canonicalBlock->trashed) {
                        // Delete the derivative block too, unless any changes were made to it
                        if ($derivativeBlock->dateUpdated == $derivativeBlock->dateCreated) {
                            $elementsService->deleteElement($derivativeBlock);
                        }
                    } else if (!$derivativeBlock->trashed) {
                        // TODO: remove after next breakpoint
                        $version = Craft::$app->getInfo()->version;
                        if (version_compare($version, '3.7.12', '>=')) {
                            if (ElementHelper::isOutdated($derivativeBlock)) {
                                // Merge the upstream changes into the derivative block
                                $elementsService->mergeCanonicalChanges($derivativeBlock);
                            }
                        } else {
                            // Merge the upstream changes into the derivative block
                            $elementsService->mergeCanonicalChanges($derivativeBlock);
                        }
                    }
                } else if (!$canonicalBlock->trashed && $canonicalBlock->dateCreated > $owner->dateCreated) {
                    // This is a new block, so duplicate it into the derivative owner
                    $elementsService->duplicateElement($canonicalBlock, [
                        'canonicalId' => $canonicalBlock->id,
                        'primaryOwnerId' => $owner->id,
                        'owner' => $localizedOwners[$canonicalBlock->siteId],
                        'siteId' => $canonicalBlock->siteId,
                        'propagating' => false,
                    ]);
                }
            }

            // Keep track of the sites we've already covered
            $siteIds = $this->getSupportedSiteIds($field->propagationMethod, $canonicalOwner, $field->propagationKeyFormat);
            foreach ($siteIds as $siteId) {
                $handledSiteIds[$siteId] = true;
            }
        }
    }

    /**
     * Returns the site IDs that are supported by SuperTable blocks for the given propagation method and owner element.
     *
     * @return int[]
     * @since 2.3.2
     */
    public function getSupportedSiteIds(string $propagationMethod, ElementInterface $owner, ?string $propagationKeyFormat = null): array
    {
        /** @var Site[] $allSites */
        $allSites = ArrayHelper::index(Craft::$app->getSites()->getAllSites(), 'id');
        $ownerSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($owner), 'siteId');
        $siteIds = [];

        $view = Craft::$app->getView();
        $elementsService = Craft::$app->getElements();

        if ($propagationMethod === SuperTableField::PROPAGATION_METHOD_CUSTOM && $propagationKeyFormat !== null) {
            $propagationKey = $view->renderObjectTemplate($propagationKeyFormat, $owner);
        }

        foreach ($ownerSiteIds as $siteId) {
            switch ($propagationMethod) {
                case SuperTableField::PROPAGATION_METHOD_NONE:
                    $include = $siteId == $owner->siteId;
                    break;
                case SuperTableField::PROPAGATION_METHOD_SITE_GROUP:
                    $include = $allSites[$siteId]->groupId == $allSites[$owner->siteId]->groupId;
                    break;
                case SuperTableField::PROPAGATION_METHOD_LANGUAGE:
                    $include = $allSites[$siteId]->language == $allSites[$owner->siteId]->language;
                    break;
                case SuperTableField::PROPAGATION_METHOD_CUSTOM:
                    if (!isset($propagationKey)) {
                        $include = true;
                    } else {
                        $siteOwner = $elementsService->getElementById($owner->id, get_class($owner), $siteId);
                        $include = $siteOwner && $propagationKey === $view->renderObjectTemplate($propagationKeyFormat, $siteOwner);
                    }
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

    /**
     * Expands the default relationship behaviour to include Super Table
     * fields so that the user can filter by those too.
     *
     * For example:
     *
     * ```twig
     * {% set reverseRelatedElements = craft.superTable.getRelatedElements({
     *   relatedTo: {
     *       targetElement: entry,
     *       field: 'superTableFieldHandle.columnHandle',
     *   },
     *   site: 'siteHandle',
     *   elementType: 'craft\\elements\\Entry',
     *   criteria: {
     *       id: 'not 123',
     *       section: 'someSection',
     *   }
     * })->all() %}
     * ```
     *
     * @method getRelatedElements
     * @param array|null $params Should contain 'relatedTo' but can also optionally include 'elementType' and 'criteria'
     * @return SuperTableBlockElement
     */
    public function getRelatedElementsQuery(array $params = null): ?SuperTableBlockQuery
    {
        // Parse out the field handles
        $fieldParams = explode('.', $params['relatedTo']['field']);

        // For safety fail early if that didn't work
        if (!isset($fieldParams[0]) || !isset($fieldParams[1])) {
            return null;
        }

        $superTableFieldHandle = $fieldParams[0];
        $superTableBlockFieldHandle = $fieldParams[1];

        // Get the Super Table field and associated block type
        $superTableField = Craft::$app->fields->getFieldByHandle($superTableFieldHandle);
        $superTableBlockTypes = $this->getBlockTypesByFieldId($superTableField->id);
        $superTableBlockType = $superTableBlockTypes[0];

        // Loop the fields on the block type and save the first one that matches our handle
        $fieldId = false;
        foreach ($superTableBlockType->getCustomFields() as $field) {
            if ($field->handle === $superTableBlockFieldHandle) {
                $fieldId = $field->id;
                break;
            }
        }

        // Check we got something and update the relatedTo criteria for our next elements call
        if ($fieldId) {
            $params['relatedTo']['field'] = $fieldId;
        } else {
            return null;
        }

        // Create an element query to find Super Table Blocks
        $blockQuery = SuperTableBlockElement::find();

        $blockCriteria = [
            'relatedTo' => $params['relatedTo'],
        ];

        // Check for site param add to blockCriteria
        if (isset($params['site'])) {
            $blockCriteria['site'] = $params['site'];
        }

        Craft::configure($blockQuery, $blockCriteria);

        // Get the Super Table Blocks that are related to that field and criteria
        $elementIds = $blockQuery->select('ownerId')->column();

        // Default to getting Entry elements but let the user override
        $elementType = $params['elementType'] ?? Entry::class;

        // Start our final criteria with the element ids we just got
        $finalCriteria = [
            'id' => $elementIds,
        ];

        // Check if the user gave us another criteria model and merge that in
        if (isset($params['criteria'])) {
            $finalCriteria = array_merge($finalCriteria, $params['criteria']);
        }

        // Create an element query based on our final criteria, and return
        $elementQuery = $elementType::find();
        Craft::configure($elementQuery, $finalCriteria);

        return $elementQuery;
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
                'bt.uid',
            ])
            ->from(['bt' => '{{%supertableblocktypes}}']);
    }

    /**
     * Returns a block type record by its ID or creates a new one.
     *
     * @param string|SuperTableBlockType $blockType
     * @return SuperTableBlockTypeRecord
     * @throws SuperTableBlockTypeNotFoundException if $blockType->id is invalid
     */
    private function _getBlockTypeRecord(string|SuperTableBlockType $blockType): SuperTableBlockTypeRecord
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
     */
    private function _createContentTable(string $tableName): void
    {
        $migration = new CreateSuperTableContentTable([
            'tableName' => $tableName,
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
    private function _deleteOtherBlocks(SuperTableField $field, ElementInterface $owner, array $except): void
    {
        $blocks = SuperTableBlockElement::find()
            ->status(null)
            ->ownerId($owner->id)
            ->fieldId($field->id)
            ->siteId($owner->siteId)
            ->andWhere(['not', ['elements.id' => $except]])
            ->all();

        $elementsService = Craft::$app->getElements();
        $deleteOwnership = [];

        foreach ($blocks as $block) {
            if ($block->primaryOwnerId === $owner->id) {
                $elementsService->deleteElement($block);
            } else {
                // Just delete the ownership relation
                $deleteOwnership[] = $block->id;
            }
        }

        if ($deleteOwnership) {
            Db::delete('{{%supertableblocks_owners}}', [
                'blockId' => $deleteOwnership,
                'ownerId' => $owner->id,
            ]);
        }
    }
}
