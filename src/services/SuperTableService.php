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
use craft\elements\db\ElementQueryInterface;
use craft\fields\BaseRelationField;
use craft\helpers\Html;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

use yii\base\Component;
use yii\base\Exception;

class SuperTableService extends Component
{
    // Properties
    // =========================================================================

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
            ->where(['fieldId' => $fieldId])
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
     * Returns a block type by its ID.
     *
     * @param int $blockTypeId The block type ID.
     *
     * @return SuperTableBlockType|null The block type, or `null` if it didn’t exist.
     */
    public function getBlockTypeById(int $blockTypeId)
    {
        if ($this->_blockTypesById !== null && array_key_exists($blockTypeId, $this->_blockTypesById)) {
            return $this->_blockTypesById[$blockTypeId];
        }

        $result = $this->_createBlockTypeQuery()
            ->where(['id' => $blockTypeId])
            ->one();

        return $this->_blockTypesById[$blockTypeId] = $result ? new SuperTableBlockTypeModel($result) : null;
    }

    /**
     * Validates a block type.
     *
     * If the block type doesn’t validate, any validation errors will be stored on the block type.
     *
     * @param SuperTableBlockType $blockType        The block type.
     * @param bool            $validateUniques      Whether the Name and Handle attributes should be validated to
     *                                              ensure they’re unique. Defaults to `true`.
     *
     * @return bool Whether the block type validated.
     */
    public function validateBlockType(SuperTableBlockTypeModel $blockType, bool $validateUniques = true): bool
    {
        $validates = true;

        $reservedHandles = array('type');

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

                $field->addErrors(array('handle' => Craft::t('"{handle}" is a reserved word.', array('handle' => $field->handle))));
            }

            // Special-case for validating child Matrix fields
            if (get_class($field) == 'craft\fields\Matrix') {
                $matrixBlockTypes = $field->getBlockTypes();

                foreach ($matrixBlockTypes as $matrixBlockType) {
                    if ($matrixBlockType->hasFieldErrors) {
                        $blockType->hasFieldErrors = true;
                        $validates = false;

                        // Store a generic error for our parent Super Table field to show a nested error exists
                        $field->addErrors(array('field' => 'general'));
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
     * @param SuperTableBlockType $blockType    The block type to be saved.
     * @param bool            $validate       Whether the block type should be validated before being saved.
     *                                        Defaults to `true`.
     *
     * @return bool
     * @throws Exception if an error occurs when saving the block type
     * @throws \Throwable if reasons
     */
    public function saveBlockType(SuperTableBlockTypeModel $blockType, bool $validate = true): bool
    {
        if (!$validate || $this->validateBlockType($blockType)) {
            $transaction = Craft::$app->getDb()->beginTransaction();
            try {
                $contentService = Craft::$app->getContent();
                $fieldsService = Craft::$app->getFields();

                $originalFieldContext = $contentService->fieldContext;
                $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;
                $originalOldFieldColumnPrefix = $fieldsService->oldFieldColumnPrefix;

                // Get the block type record
                $blockTypeRecord = $this->_getBlockTypeRecord($blockType);
                $isNewBlockType = $blockType->getIsNew();

                if (!$isNewBlockType) {
                    // Get the old block type fields
                    $result = $this->_createBlockTypeQuery()
                        ->where(['id' => $blockType->id])
                        ->one();

                    $oldBlockType = new SuperTableBlockTypeModel($result);

                    $contentService->fieldContext = 'superTableBlockType:'.$blockType->id;
                    $contentService->fieldColumnPrefix = 'field_';
                    $fieldsService->oldFieldColumnPrefix = 'field_';

                    $oldFieldsById = [];

                    foreach ($oldBlockType->getFields() as $field) {
                        /** @var Field $field */
                        $oldFieldsById[$field->id] = $field;
                    }

                    // Figure out which ones are still around
                    foreach ($blockType->getFields() as $field) {
                        /** @var Field $field */
                        if (!$field->getIsNew()) {
                            unset($oldFieldsById[$field->id]);
                        }
                    }

                    // Drop the old fields that aren't around anymore
                    foreach ($oldFieldsById as $field) {
                        $fieldsService->deleteField($field);
                    }

                    // Refresh the schema cache
                    Craft::$app->getDb()->getSchema()->refresh();
                }

                // Set the basic info on the new block type record
                $blockTypeRecord->fieldId = $blockType->fieldId;

                // Save it, minus the field layout for now
                $blockTypeRecord->save(false);

                if ($isNewBlockType) {
                    // Set the new ID on the model
                    $blockType->id = $blockTypeRecord->id;
                }

                // Save the fields and field layout
                // -------------------------------------------------------------

                $fieldLayoutFields = [];
                $sortOrder = 0;

                // Resetting the fieldContext here might be redundant if this isn't a new blocktype but whatever
                $contentService->fieldContext = 'superTableBlockType:'.$blockType->id;
                $contentService->fieldColumnPrefix = 'field_';

                foreach ($blockType->getFields() as $field) {
                    if (!$fieldsService->saveField($field, false)) {
                        throw new Exception('An error occurred while saving this SuperTable block type.');
                    }

                    $field->sortOrder = ++$sortOrder;

                    $fieldLayoutFields[] = $field;
                }

                $contentService->fieldContext = $originalFieldContext;
                $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;
                $fieldsService->oldFieldColumnPrefix = $originalOldFieldColumnPrefix;

                $fieldLayoutTab = new FieldLayoutTab();
                $fieldLayoutTab->name = 'Content';
                $fieldLayoutTab->sortOrder = 1;
                $fieldLayoutTab->setFields($fieldLayoutFields);

                $fieldLayout = new FieldLayout();
                $fieldLayout->type = SuperTableBlockElement::class;

                if (isset($oldBlockType)) {
                    $fieldLayout->id = $oldBlockType->fieldLayoutId;
                }

                $fieldLayout->setTabs([$fieldLayoutTab]);
                $fieldLayout->setFields($fieldLayoutFields);
                $fieldsService->saveLayout($fieldLayout);
                $blockType->setFieldLayout($fieldLayout);
                $blockType->fieldLayoutId = (int)$fieldLayout->id;
                $blockTypeRecord->fieldLayoutId = $fieldLayout->id;

                // Update the block type with the field layout ID
                $blockTypeRecord->save(false);

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();

                throw $e;
            }

            return true;
        }

        return false;
    }

    /**
     * Deletes a block type.
     *
     * @param SuperTableBlockType $blockType The block type.
     *
     * @return bool Whether the block type was deleted successfully.
     * @throws \Throwable if reasons
     */
    public function deleteBlockType(SuperTableBlockTypeModel $blockType): bool
    {
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
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
            /** @var SuperTableField $supertableField */
            $supertableField = $fieldsService->getFieldById($blockType->fieldId);
            $newContentTable = $this->getContentTableName($supertableField);
            $contentService->contentTable = $newContentTable;

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
            Craft::$app->getFields()->deleteLayoutById($blockType->fieldLayoutId);

            // Finally delete the actual block type
            $affectedRows = Craft::$app->getDb()->createCommand()
                ->delete('{{%supertableblocktypes}}', ['id' => $blockType->id])
                ->execute();

            $transaction->commit();

            return (bool)$affectedRows;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
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
        if (!$validate || $this->validateFieldSettings($supertableField)) {
            $transaction = Craft::$app->getDb()->beginTransaction();
            try {
                // Create the content table first since the block type fields will need it
                $oldContentTable = $this->getContentTableName($supertableField, true);
                $newContentTable = $this->getContentTableName($supertableField);

                if ($newContentTable === false) {
                    throw new Exception('There was a problem getting the new content table name.');
                }

                // Do we need to create/rename the content table?
                if (!Craft::$app->getDb()->tableExists($newContentTable)) {
                    if ($oldContentTable !== false && Craft::$app->getDb()->tableExists($oldContentTable)) {
                        MigrationHelper::renameTable($oldContentTable, $newContentTable);
                    } else {
                        $this->_createContentTable($newContentTable);
                    }
                }

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

                // Save the new ones
                $originalContentTable = Craft::$app->getContent()->contentTable;
                Craft::$app->getContent()->contentTable = $newContentTable;

                foreach ($supertableField->getBlockTypes() as $blockType) {
                    $blockType->fieldId = $supertableField->id;
                    $this->saveBlockType($blockType, false);
                }

                Craft::$app->getContent()->contentTable = $originalContentTable;

                $transaction->commit();

                // Update our cache of this field's block types
                $this->_blockTypesByFieldId[$supertableField->id] = $supertableField->getBlockTypes();

                return true;
            } catch (\Throwable $e) {
                $transaction->rollBack();

                throw $e;
            }
        } else {
            return false;
        }
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
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $originalContentTable = Craft::$app->getContent()->contentTable;
            $contentTable = $this->getContentTableName($supertableField);

            if ($contentTable === false) {
                throw new Exception('There was a problem getting the content table.');
            }

            Craft::$app->getContent()->contentTable = $contentTable;

            // Delete the block types
            $blockTypes = $this->getBlockTypesByFieldId($supertableField->id);

            foreach ($blockTypes as $blockType) {
                $this->deleteBlockType($blockType);
            }

            // Drop the content table
            Craft::$app->getDb()->createCommand()
                ->dropTable($contentTable)
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
     * @param bool        $useOldHandle Whether the method should use the field’s old handle when determining the table
     *                                  name (e.g. to get the existing table name, rather than the new one).
     *
     * @return string|false The table name, or `false` if $useOldHandle was set to `true` and there was no old handle.
     */
    public function getContentTableName(SuperTableField $supertableField, bool $useOldHandle = false)
    {
        $name = '';
        $parentFieldId = '';

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        do {
            if ($useOldHandle) {
                if (!$supertableField->oldHandle) {
                    return false;
                }

                $handle = $supertableField->oldHandle;
            } else {
                $handle = $supertableField->handle;
            }

            // Check if this field is inside a Matrix - we need to prefix this content table if so.
            if ($supertableField->context != 'global') {
                $parentFieldContext = explode(':', $supertableField->context);

                if ($parentFieldContext[0] == 'matrixBlockType') {
                    $parentFieldId = $parentFieldContext[1];
                }
            }

            $name = '_'.StringHelper::toLowerCase($handle).$name;
        } while ($supertableField = $this->getParentSuperTableField($supertableField));

        if ($parentFieldId) {
            $name = '_'.$parentFieldId.$name;
        }

        return '{{%stc'.$name.'}}';
    }

    /**
     * Returns a block by its ID.
     *
     * @param int      $blockId The Super Table block’s ID.
     * @param int|null $siteId  The site ID to return. Defaults to the current site.
     *
     * @return SuperTableBlock|null The Super Table block, or `null` if it didn’t exist.
     */
    public function getBlockById(int $blockId, int $siteId = null)
    {
        /** @var SuperTableBlock|null $block */
        $block = Craft::$app->getElements()->getElementById($blockId, SuperTableBlockElement::class, $siteId);

        return $block;
    }

    /**
     * Saves a Super Table field.
     *
     * @param SuperTableField      $field The Super Table field
     * @param ElementInterface $owner The element the field is associated with
     *
     * @throws \Throwable if reasons
     */
    public function saveField(SuperTableField $field, ElementInterface $owner)
    {
        /** @var Element $owner */
        /** @var SuperTableBlockQuery $query */
        /** @var SuperTableBlock[] $blocks */
        $query = $owner->getFieldValue($field->handle);

        // Skip if the query's site ID is different than the element's
        // (Indicates that the value as copied from another site for element propagation)
        if ($query->siteId != $owner->siteId) {
            return;
        }

        if (($blocks = $query->getCachedResult()) === null) {
            $query = clone $query;
            $query->anyStatus();
            $blocks = $query->all();
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // If this is a preexisting element, make sure that the blocks for this field/owner respect the field's translation setting
            if ($query->ownerId) {
                $this->_applyFieldTranslationSetting($query->ownerId, $query->siteId, $field);
            }

            // If the query is set to fetch blocks of a different owner, we're probably duplicating an element
            if ($query->ownerId && $query->ownerId != $owner->id) {
                // Make sure this owner doesn't already have blocks
                $newQuery = clone $query;
                $newQuery->ownerId = $owner->id;
                if (!$newQuery->exists()) {
                    // Duplicate the blocks for the new owner
                    $elementsService = Craft::$app->getElements();
                    foreach ($blocks as $block) {
                        $elementsService->duplicateElement($block, [
                            'ownerId' => $owner->id,
                            'ownerSiteId' => $field->localizeBlocks ? $owner->siteId : null
                        ]);
                    }
                }
            } else {
                $blockIds = [];

                // Only propagate the blocks if the owner isn't being propagated
                $propagate = !$owner->propagating;

                foreach ($blocks as $block) {
                    $block->ownerId = $owner->id;
                    $block->ownerSiteId = ($field->localizeBlocks ? $owner->siteId : null);
                    $block->propagating = $owner->propagating;

                    Craft::$app->getElements()->saveElement($block, false, $propagate);

                    $blockIds[] = $block->id;
                }

                // Delete any blocks that shouldn't be there anymore
                $deleteBlocksQuery = SuperTableBlockElement::find()
                    ->anyStatus()
                    ->ownerId($owner->id)
                    ->fieldId($field->id)
                    ->where(['not', ['elements.id' => $blockIds]]);

                if ($field->localizeBlocks) {
                    $deleteBlocksQuery->ownerSiteId($owner->siteId);
                } else {
                    $deleteBlocksQuery->siteId($owner->siteId);
                }

                foreach ($deleteBlocksQuery->all() as $deleteBlock) {
                    Craft::$app->getElements()->deleteElement($deleteBlock);
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Returns the parent Super Table field, if any.
     *
     * @param SuperTableField $supertableField The Super Table field.
     *
     * @return SuperTableField|null The Super Table field’s parent Super Table field, or `null` if there is none.
     */
    public function getParentSuperTableField(SuperTableField $supertableField)
    {
        if ($this->_parentSuperTableFields !== null && array_key_exists($supertableField->id, $this->_parentSuperTableFields)) {
            return $this->_parentSuperTableFields[$supertableField->id];
        }

        // Does this SuperTable field belong to another one?
        $parentSuperTableFieldId = (new Query())
            ->select(['fields.id'])
            ->from(['{{%fields}} fields'])
            ->innerJoin('{{%supertableblocktypes}} blocktypes', '[[blocktypes.fieldId]] = [[fields.id]]')
            ->innerJoin('{{%fieldlayoutfields}} fieldlayoutfields', '[[fieldlayoutfields.layoutId]] = [[blocktypes.fieldLayoutId]]')
            ->where(['fieldlayoutfields.fieldId' => $supertableField->id])
            ->scalar();

        if (!$parentSuperTableFieldId) {
            return $this->_parentSuperTableFields[$supertableField->id] = null;
        }

        /** @var SuperTableField $field */
        $field = $this->_parentSuperTableFields[$supertableField->id] = Craft::$app->getFields()->getFieldById($parentSuperTableFieldId);

        return $field;
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
                'id',
                'fieldId',
                'fieldLayoutId',
            ])
            ->from(['{{%supertableblocktypes}}']);
    }

    /**
     * Returns a block type record by its ID or creates a new one.
     *
     * @param SuperTableBlockType $blockType
     *
     * @return SuperTableBlockTypeRecord
     * @throws SuperTableBlockTypeNotFoundException if $blockType->id is invalid
     */
    private function _getBlockTypeRecord(SuperTableBlockTypeModel $blockType): SuperTableBlockTypeRecord
    {
        if ($blockType->getIsNew()) {
            return new SuperTableBlockTypeRecord();
        }

        if ($this->_blockTypeRecordsById !== null && array_key_exists($blockType->id, $this->_blockTypeRecordsById)) {
            return $this->_blockTypeRecordsById[$blockType->id];
        }

        if (($this->_blockTypeRecordsById[$blockType->id] = SuperTableBlockTypeRecord::findOne($blockType->id)) === null) {
            throw new SuperTableBlockTypeNotFoundException('Invalid block type ID: '.$blockType->id);
        }

        return $this->_blockTypeRecordsById[$blockType->id];
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
     * Applies the field's translation setting to a set of blocks.
     *
     * @param int         $ownerId
     * @param int         $ownerSiteId
     * @param SuperTableField $field
     */
    private function _applyFieldTranslationSetting(int $ownerId, int $ownerSiteId, SuperTableField $field)
    {
        // If the field is translatable, see if there are any global blocks that should be localized
        if ($field->localizeBlocks) {
            $blockQuery = SuperTableBlockElement::find()
                ->fieldId($field->id)
                ->ownerId($ownerId)
                ->anyStatus()
                ->siteId($ownerSiteId)
                ->ownerSiteId(':empty:');

            $blocks = $blockQuery->all();

            if (!empty($blocks)) {
                // Find any relational fields on these blocks
                $relationFields = [];
                foreach ($blocks as $block) {
                    if (isset($relationFields[$block->typeId])) {
                        continue;
                    }
                    $relationFields[$block->typeId] = [];
                    foreach ($block->getType()->getFields() as $typeField) {
                        if ($typeField instanceof BaseRelationField) {
                            $relationFields[$block->typeId][] = $typeField->handle;
                        }
                    }
                }
                // Prefetch the blocks in all the other sites, in case they have
                // any localized content
                $otherSiteBlocks = [];
                $allSiteIds = Craft::$app->getSites()->getAllSiteIds();
                foreach ($allSiteIds as $siteId) {
                    if ($siteId != $ownerSiteId) {
                        /** @var SuperTableBlock[] $siteBlocks */
                        $siteBlocks = $otherSiteBlocks[$siteId] = $blockQuery->siteId($siteId)->all();
                        
                        // Hard-set the relation IDs
                        foreach ($siteBlocks as $block) {
                            if (isset($relationFields[$block->typeId])) {
                                foreach ($relationFields[$block->typeId] as $handle) {
                                    /** @var ElementQueryInterface $relationQuery */
                                    $relationQuery = $block->getFieldValue($handle);
                                    $block->setFieldValue($handle, $relationQuery->ids());
                                }
                            }
                        }
                    }
                }

                // Explicitly assign the current site's blocks to the current site
                foreach ($blocks as $block) {
                    $block->ownerSiteId = $ownerSiteId;
                    Craft::$app->getElements()->saveElement($block, false);
                }

                // Now save the other sites' blocks as new site-specific blocks
                foreach ($otherSiteBlocks as $siteId => $siteBlocks) {
                    foreach ($siteBlocks as $block) {
                        //$originalBlockId = $block->id;

                        $block->id = null;
                        $block->contentId = null;
                        $block->siteId = (int)$siteId;
                        $block->ownerSiteId = (int)$siteId;
                        Craft::$app->getElements()->saveElement($block, false);
                        //$newBlockIds[$originalBlockId][$siteId] = $block->id;
                    }
                }
            }
        } else {
            // Otherwise, see if the field has any localized blocks that should be deleted
            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                if ($siteId != $ownerSiteId) {
                    $blocks = SuperTableBlockElement::find()
                        ->fieldId($field->id)
                        ->ownerId($ownerId)
                        ->anyStatus()
                        ->siteId($siteId)
                        ->ownerSiteId($siteId)
                        ->all();

                    foreach ($blocks as $block) {
                        Craft::$app->getElements()->deleteElement($block);
                    }
                }
            }
        }
    }
}