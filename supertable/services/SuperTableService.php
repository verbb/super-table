<?php
namespace Craft;

class SuperTableService extends BaseApplicationComponent
{
    // Properties
    // =========================================================================

    private $_blockTypesById;
    private $_blockTypesByFieldId;
    private $_fetchedAllBlockTypesForFieldId;
    private $_blockTypeRecordsById;
    private $_blockRecordsById;
    private $_uniqueBlockTypeAndFieldHandles;
    private $_parentSuperTableFields;

    // Public Methods
    // =========================================================================

    public function getBlockTypesByFieldId($fieldId, $indexBy = null)
    {
        if (empty($this->_fetchedAllBlockTypesForFieldId[$fieldId]))
        {
            $this->_blockTypesByFieldId[$fieldId] = array();

            $results = $this->_createBlockTypeQuery()
                ->where('fieldId = :fieldId', array(':fieldId' => $fieldId))
                ->queryAll();

            foreach ($results as $result) {
                $blockType = new SuperTable_BlockTypeModel($result);
                $this->_blockTypesById[$blockType->id] = $blockType;
                $this->_blockTypesByFieldId[$fieldId][] = $blockType;
            }

            $this->_fetchedAllBlockTypesForFieldId[$fieldId] = true;
        }

        if (!$indexBy) {
            return $this->_blockTypesByFieldId[$fieldId];
        } else {
            $blockTypes = array();

            foreach ($this->_blockTypesByFieldId[$fieldId] as $blockType)
            {
                $blockTypes[$blockType->$indexBy] = $blockType;
            }

            return $blockTypes;
        }
    }

    public function getBlockTypeById($blockTypeId)
    {
        if (!isset($this->_blockTypesById) || !array_key_exists($blockTypeId, $this->_blockTypesById)) {
            $result = $this->_createBlockTypeQuery()
                ->where('id = :id', array(':id' => $blockTypeId))
                ->queryRow();

            if ($result) {
                $blockType = new SuperTable_BlockTypeModel($result);
            } else {
                $blockType = null;
            }

            $this->_blockTypesById[$blockTypeId] = $blockType;
        }

        return $this->_blockTypesById[$blockTypeId];
    }

    public function validateBlockType(SuperTable_BlockTypeModel $blockType, $validateUniques = true)
    {
        $validates = true;

        $reservedHandles = array('type');

        $blockTypeRecord = $this->_getBlockTypeRecord($blockType);
        $blockTypeRecord->fieldId = $blockType->fieldId;

        if (!$blockTypeRecord->validate()) {
            $validates = false;
            $blockType->addErrors($blockTypeRecord->getErrors());
        }

        // Can't validate multiple new rows at once so we'll need to give these temporary context to avoid false unique
        // handle validation errors, and just validate those manually. Also apply the future fieldColumnPrefix so that
        // field handle validation takes its length into account.
        $contentService = craft()->content;
        $originalFieldContext      = $contentService->fieldContext;
        $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;

        $contentService->fieldContext      = StringHelper::randomString(10);
        $contentService->fieldColumnPrefix = 'field_';

        foreach ($blockType->getFields() as $field) {
            craft()->fields->validateField($field);

            // Make sure the block type handle + field handle combo is unique for the whole field. This prevents us from
            // worrying about content column conflicts like "a" + "b_c" == "a_b" + "c".
            if ($field->hasErrors()) {
                $blockType->hasFieldErrors = true;
                $validates = false;

                $blockType->addErrors($field->getErrors());
            }

            if ($field->hasSettingErrors()) {
                $blockType->hasFieldErrors = true;
                $validates = false;

                $blockType->addErrors($field->getSettingErrors());
            }

            // `type` is a restricted handle
            if (in_array($field->handle, $reservedHandles)) {
                $blockType->hasFieldErrors = true;
                $validates = false;

                $field->addErrors(array('handle' => Craft::t('"{handle}" is a reserved word.', array('handle' => $field->handle))));
            }

            // Special-case for validating child Matrix fields
            if ($field->type == 'Matrix') {
                $fieldType = $field->getFieldType();
                $matrixBlockTypes = $fieldType->getSettings()->getBlockTypes();

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

        $contentService->fieldContext      = $originalFieldContext;
        $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;

        return $validates;
    }

    public function saveBlockType(SuperTable_BlockTypeModel $blockType, $validate = true)
    {
        if (!$validate || $this->validateBlockType($blockType)) { 
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            
            try {
                $contentService = craft()->content;
                $fieldsService  = craft()->fields;

                $originalFieldContext         = $contentService->fieldContext;
                $originalFieldColumnPrefix    = $contentService->fieldColumnPrefix;
                $originalOldFieldColumnPrefix = $fieldsService->oldFieldColumnPrefix;

                // Get the block type record
                $blockTypeRecord = $this->_getBlockTypeRecord($blockType);
                $isNewBlockType = $blockType->isNew();

                if (!$isNewBlockType) {
                    // Get the old block type fields
                    $oldBlockTypeRecord = SuperTable_BlockTypeRecord::model()->findById($blockType->id);
                    $oldBlockType = SuperTable_BlockTypeModel::populateModel($oldBlockTypeRecord);

                    $contentService->fieldContext        = 'superTableBlockType:'.$blockType->id;
                    $contentService->fieldColumnPrefix   = 'field_';
                    $fieldsService->oldFieldColumnPrefix = 'field_';

                    $oldFieldsById = array();

                    foreach ($oldBlockType->getFields() as $field) {
                        $oldFieldsById[$field->id] = $field;
                    }

                    // Figure out which ones are still around
                    foreach ($blockType->getFields() as $field) {
                        if (!$field->isNew()) {
                            unset($oldFieldsById[$field->id]);
                        }
                    }

                    // Drop the old fields that aren't around anymore
                    foreach ($oldFieldsById as $field) {
                        $fieldsService->deleteField($field);
                    }

                    // Refresh the schema cache
                    craft()->db->getSchema()->refresh();
                }

                // Set the basic info on the new block type record
                $blockTypeRecord->fieldId   = $blockType->fieldId;

                // Save it, minus the field layout for now
                $blockTypeRecord->save(false);

                if ($isNewBlockType) {
                    // Set the new ID on the model
                    $blockType->id = $blockTypeRecord->id;
                }

                // Save the fields and field layout
                // -------------------------------------------------------------

                $fieldLayoutFields = array();
                $sortOrder = 0;

                // Resetting the fieldContext here might be redundant if this isn't a new blocktype but whatever
                $contentService->fieldContext      = 'superTableBlockType:'.$blockType->id;
                $contentService->fieldColumnPrefix = 'field_';

                foreach ($blockType->getFields() as $field) {
                    if (!$fieldsService->saveField($field, false)) {
                        throw new Exception(Craft::t('An error occurred while saving this SuperTable block type.'));
                    }

                    $fieldLayoutField = new FieldLayoutFieldModel();
                    $fieldLayoutField->fieldId = $field->id;
                    $fieldLayoutField->required = $field->required;
                    $fieldLayoutField->sortOrder = ++$sortOrder;

                    $fieldLayoutFields[] = $fieldLayoutField;
                }

                $contentService->fieldContext        = $originalFieldContext;
                $contentService->fieldColumnPrefix   = $originalFieldColumnPrefix;
                $fieldsService->oldFieldColumnPrefix = $originalOldFieldColumnPrefix;

                $fieldLayoutTab = new FieldLayoutTabModel();
                $fieldLayoutTab->name = 'Content';
                $fieldLayoutTab->sortOrder = 1;
                $fieldLayoutTab->setFields($fieldLayoutFields);

                $fieldLayout = new FieldLayoutModel();
                $fieldLayout->type = 'SuperTable_Block';
                $fieldLayout->setTabs(array($fieldLayoutTab));
                $fieldLayout->setFields($fieldLayoutFields);

                $fieldsService->saveLayout($fieldLayout);

                // Update the block type model & record with our new field layout ID
                $blockType->setFieldLayout($fieldLayout);
                $blockType->fieldLayoutId = $fieldLayout->id;
                $blockTypeRecord->fieldLayoutId = $fieldLayout->id;

                // Update the block type with the field layout ID
                $blockTypeRecord->save(false);

                if (!$isNewBlockType) {
                    // Delete the old field layout
                    $fieldsService->deleteLayoutById($oldBlockType->fieldLayoutId);
                }

                if ($transaction !== null) {
                    $transaction->commit();
                }
            } catch (\Exception $e) {
                if ($transaction !== null) {
                    $transaction->rollback();
                }

                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    public function deleteBlockType(SuperTable_BlockTypeModel $blockType)
    {
        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        
        try {
            // First delete the blocks of this type
            $blockIds = craft()->db->createCommand()
                ->select('id')
                ->from('supertableblocks')
                ->where(array('typeId' => $blockType->id))
                ->queryColumn();

            $this->deleteBlockById($blockIds);

            // Set the new contentTable
            $originalContentTable = craft()->content->contentTable;
            $superTableField = craft()->fields->getFieldById($blockType->fieldId);
            $newContentTable = $this->getContentTableName($superTableField);
            craft()->content->contentTable = $newContentTable;

            // Now delete the block type fields
            $originalFieldColumnPrefix = craft()->content->fieldColumnPrefix;
            craft()->content->fieldColumnPrefix = 'field_';

            foreach ($blockType->getFields() as $field) {
                craft()->fields->deleteField($field);
            }

            // Restore the contentTable and the fieldColumnPrefix to original values.
            craft()->content->fieldColumnPrefix = $originalFieldColumnPrefix;
            craft()->content->contentTable = $newContentTable;

            // Delete the field layout
            craft()->fields->deleteLayoutById($blockType->fieldLayoutId);

            // Finally delete the actual block type
            $affectedRows = craft()->db->createCommand()->delete('supertableblocktypes', array('id' => $blockType->id));

            if ($transaction !== null) {
                $transaction->commit();
            }

            return (bool) $affectedRows;
        } catch (\Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }
    }

    public function validateFieldSettings(SuperTable_SettingsModel $settings)
    {
        $validates = true;

        foreach ($settings->getBlockTypes() as $btIndex => $blockType) {
            if (!$this->validateBlockType($blockType, false)) {
                // Don't break out of the loop because we still want to get validation errors for the remaining block
                // types.
                $validates = false;
            }

            if ($blockType->hasFieldErrors) {
                $settings->addErrors($blockType->getErrors());
            }
        }

        return $validates;
    }

    public function saveSettings(SuperTable_SettingsModel $settings, $validate = true)
    {
        if (!$validate || $this->validateFieldSettings($settings)) {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            
            try {
                $superTableField = $settings->getField();

                // Create the content table first since the block type fields will need it
                $oldContentTable = $this->getContentTableName($superTableField, true);
                $newContentTable = $this->getContentTableName($superTableField);

                // Do we need to create/rename the content table?
                if (!craft()->db->tableExists($newContentTable)) {
                    if ($oldContentTable && craft()->db->tableExists($oldContentTable)) {
                        MigrationHelper::renameTable($oldContentTable, $newContentTable);
                    } else {
                        $this->_createContentTable($newContentTable);
                    }
                }

                // Delete the old block types first, in case there's a handle conflict with one of the new ones
                $oldBlockTypes = $this->getBlockTypesByFieldId($superTableField->id);
                $oldBlockTypesById = array();

                foreach ($oldBlockTypes as $blockType) {
                    $oldBlockTypesById[$blockType->id] = $blockType;
                }

                foreach ($settings->getBlockTypes() as $blockType) {
                    if (!$blockType->isNew()) {
                        unset($oldBlockTypesById[$blockType->id]);
                    }
                }

                foreach ($oldBlockTypesById as $blockType) {
                    $this->deleteBlockType($blockType);
                }

                // Save the new ones
                $sortOrder = 0;

                $originalContentTable = craft()->content->contentTable;
                craft()->content->contentTable = $newContentTable;

                foreach ($settings->getBlockTypes() as $blockType) {
                    $sortOrder++;
                    $blockType->fieldId = $superTableField->id;
                    $this->saveBlockType($blockType, false);
                }

                craft()->content->contentTable = $originalContentTable;

                if ($transaction !== null) {
                    $transaction->commit();
                }

                // Update our cache of this field's block types
                $this->_blockTypesByFieldId[$settings->getField()->id] = $settings->getBlockTypes();

                return true;
            } catch (\Exception $e) {
                if ($transaction !== null) {
                    $transaction->rollback();
                }

                throw $e;
            }
        } else {
            return false;
        }
    }

    public function deleteSuperTableField(FieldModel $superTableField)
    {
        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        
        try {
            $originalContentTable = craft()->content->contentTable;
            $contentTable = $this->getContentTableName($superTableField);
            craft()->content->contentTable = $contentTable;

            // Delete the block types
            $blockTypes = $this->getBlockTypesByFieldId($superTableField->id);

            foreach ($blockTypes as $blockType) {
                $this->deleteBlockType($blockType);
            }

            // Drop the content table
            craft()->db->createCommand()->dropTable($contentTable);

            craft()->content->contentTable = $originalContentTable;

            if ($transaction !== null) {
                $transaction->commit();
            }

            return true;
        } catch (\Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }
    }

    public function getContentTableName(FieldModel $superTableField, $useOldHandle = false)
    {
        $name = '';
        $parentFieldId = '';

        do {
            if ($useOldHandle) {
                if (!$superTableField->oldHandle) {
                    return false;
                }

                $handle = $superTableField->oldHandle;
            } else {
                $handle = $superTableField->handle;
            }

            // Check if this field is inside a Matrix - we need to prefix this content table if so.
            if ($superTableField->context != 'global') {
                $parentFieldContext = explode(':', $superTableField->context);

                if ($parentFieldContext[0] == 'matrixBlockType') {
                    $parentFieldId = $parentFieldContext[1];
                }
            }

            $name = '_'.StringHelper::toLowerCase($handle).$name;
        }
        while ($superTableField = $this->getParentSuperTableField($superTableField));

        if ($parentFieldId) {
            $name = '_'.$parentFieldId.$name;
        }

        return 'supertablecontent'.$name;
    }

    public function getBlockById($blockId, $localeId = null)
    {
        return craft()->elements->getElementById($blockId, 'SuperTable_Block', $localeId);
    }

    public function validateBlock(SuperTable_BlockModel $block)
    {
        $block->clearErrors();

        $blockRecord = $this->_getBlockRecord($block);

        $blockRecord->fieldId   = $block->fieldId;
        $blockRecord->ownerId   = $block->ownerId;
        $blockRecord->typeId    = $block->typeId;
        $blockRecord->sortOrder = $block->sortOrder;

        $blockRecord->validate();
        $block->addErrors($blockRecord->getErrors());

        $originalFieldContext = craft()->content->fieldContext;
        craft()->content->fieldContext = 'superTableBlockType:'.$block->typeId;

        if (!craft()->content->validateContent($block)) {
            $block->addErrors($block->getContent()->getErrors());
        }

        craft()->content->fieldContext = $originalFieldContext;

        return !$block->hasErrors();
    }

    public function saveBlock(SuperTable_BlockModel $block, $validate = true)
    {
        if (!$validate || $this->validateBlock($block)) {
            $blockRecord = $this->_getBlockRecord($block);
            $isNewBlock = $blockRecord->isNewRecord();

            $blockRecord->fieldId     = $block->fieldId;
            $blockRecord->ownerId     = $block->ownerId;
            $blockRecord->ownerLocale = $block->ownerLocale;
            $blockRecord->typeId      = $block->typeId;
            $blockRecord->sortOrder   = $block->sortOrder;

            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            
            try {
                if (craft()->elements->saveElement($block, false)) {
                    if ($isNewBlock) {
                        $blockRecord->id = $block->id;
                    }

                    $blockRecord->save(false);

                    if ($transaction !== null) {
                        $transaction->commit();
                    }

                    return true;
                }
            } catch (\Exception $e) {
                if ($transaction !== null) {
                    $transaction->rollback();
                }

                throw $e;
            }
        }

        return false;
    }

    public function deleteBlockById($blockIds)
    {
        if (!$blockIds) {
            return false;
        }

        if (!is_array($blockIds)) {
            $blockIds = array($blockIds);
        }

        // Pass this along to ElementsService for the heavy lifting
        return craft()->elements->deleteElementById($blockIds);
    }

    public function saveField(SuperTableFieldType $fieldType)
    {
        $owner = $fieldType->element;
        $field = $fieldType->model;
        $blocks = $owner->getContent()->getAttribute($field->handle);

        if ($blocks === null) {
            return true;
        }

        if (!is_array($blocks)) {
            $blocks = array();
        }

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        
        try {
            // First thing's first. Let's make sure that the blocks for this field/owner respect the field's translation setting
            $this->_applyFieldTranslationSetting($owner, $field, $blocks);

            $blockIds = array();

            foreach ($blocks as $block) {
                $block->ownerId = $owner->id;
                $block->ownerLocale = ($field->translatable ? $owner->locale : null);

                $this->saveBlock($block, false);

                $blockIds[] = $block->id;
            }

            // Get the IDs of blocks that are row deleted
            $deletedBlockConditions = array('and',
                'ownerId = :ownerId',
                'fieldId = :fieldId',
                array('not in', 'id', $blockIds)
            );

            $deletedBlockParams = array(
                ':ownerId' => $owner->id,
                ':fieldId' => $field->id
            );

            if ($field->translatable) {
                $deletedBlockConditions[] = 'ownerLocale  = :ownerLocale';
                $deletedBlockParams[':ownerLocale'] = $owner->locale;
            }

            $deletedBlockIds = craft()->db->createCommand()
                ->select('id')
                ->from('supertableblocks')
                ->where($deletedBlockConditions, $deletedBlockParams)
                ->queryColumn();

            $this->deleteBlockById($deletedBlockIds);

            if ($transaction !== null) {
                $transaction->commit();
            }
        } catch (\Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        return true;
    }

    public function getParentSuperTableField(FieldModel $superTableField)
    {
        if (!isset($this->_parentSuperTableFields) || !array_key_exists($superTableField->id, $this->_parentSuperTableFields)) {
            // Does this SuperTable field belong to another one?
            $parentSuperTableFieldId = craft()->db->createCommand()
                ->select('fields.id')
                ->from('fields fields')
                ->join('supertableblocktypes blocktypes', 'blocktypes.fieldId = fields.id')
                ->join('fieldlayoutfields fieldlayoutfields', 'fieldlayoutfields.layoutId = blocktypes.fieldLayoutId')
                ->where('fieldlayoutfields.fieldId = :superTableFieldId', array(':superTableFieldId' => $superTableField->id))
                ->queryScalar();

            if ($parentSuperTableFieldId) {
                $this->_parentSuperTableFields[$superTableField->id] = craft()->fields->getFieldById($parentSuperTableFieldId);
            } else {
                $this->_parentSuperTableFields[$superTableField->id] = null;
            }
        }

        return $this->_parentSuperTableFields[$superTableField->id];
    }

    public function onBeforeDeleteElements($event)
    {
        // Check on every Element-deletion if there are any child-elements that are Super Table fields
        // if there are, we need to delete Super Table Blocks as part of the cleanup process. Otherwise, these
        // blocks stick around orphaned. Note that native Matrix fields do this automatically via Craft-core.

        $elementIds = $event->params['elementIds'];

        if (count($elementIds) == 1) {
            $superTableBlockCondition = array('ownerId' => $elementIds[0]);
        } else {
            $superTableBlockCondition = array('in', 'ownerId', $elementIds);
        }

        // First delete any Matrix blocks that belong to this element(s)
        $superTableBlockIds = craft()->db->createCommand()
            ->select('id')
            ->from('supertableblocks')
            ->where($superTableBlockCondition)
            ->queryColumn();

        if ($superTableBlockIds) {
            craft()->superTable->deleteBlockById($superTableBlockIds);
        }
    }





    // Private Methods
    // =========================================================================

    private function _createBlockTypeQuery()
    {
        return craft()->db->createCommand()
            ->select('id, fieldId, fieldLayoutId')
            ->from('supertableblocktypes');
    }

    private function _getBlockTypeRecord(SuperTable_BlockTypeModel $blockType)
    {
        if (!$blockType->isNew()) {
            $blockTypeId = $blockType->id;

            if (!isset($this->_blockTypeRecordsById) || !array_key_exists($blockTypeId, $this->_blockTypeRecordsById)) {
                $this->_blockTypeRecordsById[$blockTypeId] = SuperTable_BlockTypeRecord::model()->findById($blockTypeId);

                if (!$this->_blockTypeRecordsById[$blockTypeId]) {
                    throw new Exception(Craft::t('No block type exists with the ID “{id}”.', array('id' => $blockTypeId)));
                }
            }

            return $this->_blockTypeRecordsById[$blockTypeId];
        } else {
            return new SuperTable_BlockTypeRecord();
        }
    }

    private function _getBlockRecord(SuperTable_BlockModel $block)
    {
        $blockId = $block->id;

        if ($blockId) {
            if (!isset($this->_blockRecordsById) || !array_key_exists($blockId, $this->_blockRecordsById)) {
                $this->_blockRecordsById[$blockId] = SuperTable_BlockRecord::model()->with('element')->findById($blockId);

                if (!$this->_blockRecordsById[$blockId]) {
                    throw new Exception(Craft::t('No block exists with the ID “{id}”.', array('id' => $blockId)));
                }
            }

            return $this->_blockRecordsById[$blockId];
        } else {
            return new SuperTable_BlockRecord();
        }
    }

    private function _createContentTable($name)
    {
        craft()->db->createCommand()->createTable($name, array(
            'elementId' => array('column' => ColumnType::Int, 'null' => false),
            'locale'    => array('column' => ColumnType::Locale, 'null' => false)
        ));

        craft()->db->createCommand()->createIndex($name, 'elementId,locale', true);
        craft()->db->createCommand()->addForeignKey($name, 'elementId', 'elements', 'id', 'CASCADE', null);
        craft()->db->createCommand()->addForeignKey($name, 'locale', 'locales', 'locale', 'CASCADE', 'CASCADE');
    }

    private function _applyFieldTranslationSetting($owner, $field, $blocks)
    {
        // Does it look like any work is needed here?
        $applyNewTranslationSetting = false;

        foreach ($blocks as $block) {
            if ($block->id && (
                ($field->translatable && !$block->ownerLocale) ||
                (!$field->translatable && $block->ownerLocale)
            )) {
                $applyNewTranslationSetting = true;
                break;
            }
        }

        if ($applyNewTranslationSetting) {
            // Get all of the blocks for this field/owner that use the other locales, whose ownerLocale attribute is set
            // incorrectly
            $blocksInOtherLocales = array();

            $criteria = craft()->elements->getCriteria('SuperTable_Block');
            $criteria->fieldId = $field->id;
            $criteria->ownerId = $owner->id;
            $criteria->status = null;
            $criteria->localeEnabled = null;
            $criteria->limit = null;

            if ($field->translatable) {
                $criteria->ownerLocale = ':empty:';
            }

            foreach (craft()->i18n->getSiteLocaleIds() as $localeId) {
                if ($localeId == $owner->locale) {
                    continue;
                }

                $criteria->locale = $localeId;

                if (!$field->translatable) {
                    $criteria->ownerLocale = $localeId;
                }

                $blocksInOtherLocale = $criteria->find();

                if ($blocksInOtherLocale) {
                    $blocksInOtherLocales[$localeId] = $blocksInOtherLocale;
                }
            }

            if ($blocksInOtherLocales) {
                if ($field->translatable) {
                    $newBlockIds = array();

                    // Duplicate the other-locale blocks so each locale has their own unique set of blocks
                    foreach ($blocksInOtherLocales as $localeId => $blocksInOtherLocale) {
                        foreach ($blocksInOtherLocale as $blockInOtherLocale) {
                            $originalBlockId = $blockInOtherLocale->id;

                            $blockInOtherLocale->id = null;
                            $blockInOtherLocale->getContent()->id = null;
                            $blockInOtherLocale->ownerLocale = $localeId;
                            $this->saveBlock($blockInOtherLocale, false);

                            $newBlockIds[$originalBlockId][$localeId] = $blockInOtherLocale->id;
                        }
                    }

                    // Duplicate the relations, too.  First by getting all of the existing relations for the original
                    // blocks
                    $relations = craft()->db->createCommand()
                        ->select('fieldId, sourceId, sourceLocale, targetId, sortOrder')
                        ->from('relations')
                        ->where(array('in', 'sourceId', array_keys($newBlockIds)))
                        ->queryAll();

                    if ($relations) {
                        // Now duplicate each one for the other locales' new blocks
                        $rows = array();

                        foreach ($relations as $relation) {
                            $originalBlockId = $relation['sourceId'];

                            // Just to be safe...
                            if (isset($newBlockIds[$originalBlockId])) {
                                foreach ($newBlockIds[$originalBlockId] as $localeId => $newBlockId) {
                                    $rows[] = array($relation['fieldId'], $newBlockId, $relation['sourceLocale'], $relation['targetId'], $relation['sortOrder']);
                                }
                            }
                        }

                        craft()->db->createCommand()->insertAll('relations', array('fieldId', 'sourceId', 'sourceLocale', 'targetId', 'sortOrder'), $rows);
                    }
                } else {
                    // Delete all of these blocks
                    $blockIdsToDelete = array();

                    foreach ($blocksInOtherLocales as $localeId => $blocksInOtherLocale) {
                        foreach ($blocksInOtherLocale as $blockInOtherLocale) {
                            $blockIdsToDelete[] = $blockInOtherLocale->id;
                        }
                    }

                    $this->deleteBlockById($blockIdsToDelete);
                }
            }
        }
    }


    // Hook Methods
    // =========================================================================



    // Feed Me
    // =========================================================================

    public function prepForFeedMeFieldType($field, &$data, $handle)
    {
        if ($field->type == 'SuperTable') {
            $content = array();

            preg_match_all('/\w+/', $handle, $matches);

            if (isset($matches[0])) {
                $fieldData = array();

                $fieldHandle = $matches[0][0];
                $blocktypeHandle = $matches[0][1];
                $subFieldHandle = $matches[0][2];

                // Store the fields for this Matrix - can't use the fields service due to context
                $blockTypes = craft()->superTable->getBlockTypesByFieldId($field->id, 'id');
                $blockType = $blockTypes[$blocktypeHandle];

                foreach ($blockType->getFields() as $f) {
                    if ($f->handle == $subFieldHandle) {
                        $subField = $f;
                    }
                }

                $rows = array();

                if (!empty($data)) {
                    if (!is_array($data)) {
                        $data = array($data);
                    }

                    // We're passed data in with the fieldHandle as the key to our data
                    $blockData = $data[$fieldHandle];

                    // Check for static field
                    if (!is_array($blockData)) {
                        $blockData = array($blockData);
                    }

                    foreach ($blockData as $i => $singleFieldData) {
                        $subFieldData = craft()->feedMe_fields->prepForFieldType($singleFieldData, $subFieldHandle, $subField);

                        $fieldData['new'.$blocktypeHandle.($i+1)] = array(
                            'type' => $blocktypeHandle,
                            'order' => $i,
                            'enabled' => true,
                            'fields' => $subFieldData,
                        );
                    }
                }

                $data[$fieldHandle] = $fieldData;
            }
        }
    }

    public function postForFeedMeFieldType(&$fieldData)
    {
        // This is less intensive than craft()->fields->getFieldByHandle($fieldHandle);
        /*foreach ($fieldData as $fieldHandle => $data) {
            if (is_array($data)) {
                $singleFieldData = array_values($data);
                
                // Check for the order attr, otherwise not what we're after
                if (isset($singleFieldData[0]['order'])) {
                    $orderedSuperTableData = array();
                    $tempSuperTableData = array();

                    foreach ($data as $key => $subField) {
                        $tempSuperTableData[$subField['order']][$key] = $subField;
                    }

                    $fieldData[$fieldHandle] = array();

                    foreach ($tempSuperTableData as $key => $subField) {
                        $fieldData[$fieldHandle] = array_merge($fieldData[$fieldHandle], $subField);
                    }
                }
            }
        }*/
    }


    // Export
    // =========================================================================

    public function registerExportOperation(&$data, $handle)
    {
        $superTableField = craft()->fields->getFieldByHandle($handle);

        if ($superTableField) {
            if ($superTableField->type == 'SuperTable') {

                $values = array();
                foreach ($data as $index => $block) {
                    foreach ($block->getFieldLayout()->getFields() as $fieldLayoutField) {
                        $field = $fieldLayoutField->getField();

                        $value = $block->getFieldValue($field->handle);
                        $value = $this->parseFieldData($field, $value);

                        $values[] = $value;
                    }
                }

                $data = $values;
            }
        }
    }

    // Assists with Export functionality - prepares field content for export. Extracted from ExportService.php
    protected function parseFieldData($field, $data)
    {
        if (!is_null($data)) {
            if (!is_null($field)) {
                switch ($field->type) {
                    case ExportModel::FieldTypeEntries:
                    case ExportModel::FieldTypeCategories:
                    case ExportModel::FieldTypeAssets:
                    case ExportModel::FieldTypeUsers:
                        $data = $data instanceof ElementCriteriaModel ? implode(', ', $data->find()) : $data;

                        break;

                    case ExportModel::FieldTypeLightswitch:
                        switch ($data) {
                            case '0':
                                $data = Craft::t('No');
                                break;

                            case '1':
                                $data = Craft::t('Yes');
                                break;
                        }

                        break;

                    case ExportModel::FieldTypeTable:
                        $table = array();
                        foreach ($data as $row) {

                            $i = 1;

                            foreach ($row as $column => $value) {
                                $column = isset($field->settings['columns'][$column]) ? $field->settings['columns'][$column] : (isset($field->settings['columns']['col'.$i]) ? $field->settings['columns']['col'.$i] : array('type' => 'dummy'));

                                $i++;

                                $table[] = $column['type'] == 'checkbox' ? ($value == 1 ? Craft::t('Yes') : Craft::t('No')) : $value;
                            }
                        }

                        $data = $table;

                        break;

                    case ExportModel::FieldTypeRichText:
                    case ExportModel::FieldTypeDate:
                    case ExportModel::FieldTypeRadioButtons:
                    case ExportModel::FieldTypeDropdown:
                        $data = (string) $data;

                        break;

                    case ExportModel::FieldTypeCheckboxes:
                    case ExportModel::FieldTypeMultiSelect:
                        $multi = array();
                        foreach ($data as $row) {
                            $multi[] = $row->value;
                        }

                        $data = $multi;

                        break;
                }
            }
        } else {
            $data = '';
        }

        if (is_array($data)) {
            $data = StringHelper::arrayToString(ArrayHelper::filterEmptyStringsFromArray(ArrayHelper::flattenArray($data)), ', ');
        }

        if (is_object($data)) {
            $data = StringHelper::arrayToString(ArrayHelper::filterEmptyStringsFromArray(ArrayHelper::flattenArray(get_object_vars($data))), ', ');
        }

        return $data;
    }
}
