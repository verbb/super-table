<?php
namespace Craft;

class SuperTableFieldType extends BaseFieldType implements IEagerLoadingFieldType
{
    // Public Methods
    // =========================================================================

    public function getName()
    {
        return Craft::t('Super Table');
    }

    public function defineContentAttribute()
    {
        return false;
    }

    public function getSettingsHtml()
    {
        // Get the available field types data
        $fieldTypeInfo = $this->_getFieldTypeInfoForConfigurator();

        $fieldTypeOptions = array();

        foreach (craft()->fields->getAllFieldTypes() as $fieldType) {
            // No SuperTable-Inception, sorry buddy.
            if ($fieldType->getClassHandle() != 'SuperTable') {
                $fieldTypeOptions[] = array('label' => $fieldType->getName(), 'value' => $fieldType->getClassHandle());
            }
        }

        $settings = $this->getSettings();

        // Grab any additional settings for each field - latch it on

        $blockTypes = $settings->getBlockTypes();
        $tableId = ($blockTypes) ? $blockTypes[0]->id : 'new';

        craft()->templates->includeJsResource('supertable/js/SuperTableConfigurator.js');
        craft()->templates->includeJs('new Craft.SuperTableConfigurator(' . 
            '"'.$tableId.'", ' .
            '"'.craft()->templates->namespaceInputId($tableId).'", ' .
            JsonHelper::encode($fieldTypeInfo).', ' . 
            '"'.craft()->templates->getNamespace().'"' .
        ');');

        return craft()->templates->render('supertable/settings', array(
            'id'            => $tableId,
            'settings'      => $settings,
            'fieldTypes'    => $fieldTypeOptions,
        ));
    }

    public function prepSettings($settings)
    {
        if ($settings instanceof SuperTable_SettingsModel) {
            return $settings;
        }

        $superTableSettings = new SuperTable_SettingsModel($this->model);
        $blockTypes = array();
        $columns = array();

        if (!empty($settings['blockTypes'])) {
            foreach ($settings['blockTypes'] as $blockTypeId => $blockTypeSettings) {
                $blockType = new SuperTable_BlockTypeModel();
                $blockType->id      = $blockTypeId;
                $blockType->fieldId = $this->model->id;

                $fields = array();

                if (!empty($blockTypeSettings['fields'])) {
                    foreach ($blockTypeSettings['fields'] as $fieldId => $fieldSettings) {
                        $field = new FieldModel();
                        $field->id           = $fieldId;
                        $field->name         = $fieldSettings['name'];
                        $field->handle       = $fieldSettings['handle'];
                        $field->instructions = $fieldSettings['instructions'];
                        $field->required     = !empty($fieldSettings['required']);
                        $field->translatable = !empty($fieldSettings['translatable']);
                        $field->type         = $fieldSettings['type'];

                        if (isset($fieldSettings['width'])) {
                            $columns[$field->id] = array(
                                'width' => $fieldSettings['width'],
                            );
                        }

                        if (isset($fieldSettings['typesettings'])) {
                            $field->settings = $fieldSettings['typesettings'];
                        }

                        $fields[] = $field;
                    }
                }

                $blockType->setFields($fields);
                $blockTypes[] = $blockType;
            }
        }

        $superTableSettings->setBlockTypes($blockTypes);

        // Save additional field column data - but in the SuperTable field
        $superTableSettings->columns = $columns;

        if (!empty($settings['fieldLayout'])) {
            $superTableSettings->fieldLayout = $settings['fieldLayout'];
        }

        if (!empty($settings['staticField'])) {
            $superTableSettings->staticField = $settings['staticField'];
        }

        if (!empty($settings['selectionLabel'])) {
            $superTableSettings->selectionLabel = $settings['selectionLabel'];
        }

        if (!empty($settings['minRows'])) {
            $superTableSettings->minRows = $settings['minRows'];
        }

        if (!empty($settings['maxRows'])) {
            $superTableSettings->maxRows = $settings['maxRows'];
        }

        return $superTableSettings;
    }

    public function onAfterSave()
    {
        craft()->superTable->saveSettings($this->getSettings(), false);
    }

    public function onBeforeDelete()
    {
        craft()->superTable->deleteSuperTableField($this->model);
    }

    public function prepValue($value)
    {
        $criteria = craft()->elements->getCriteria('SuperTable_Block');

        // Existing element?
        if (!empty($this->element->id)) {
            $criteria->ownerId = $this->element->id;
        } else {
            $criteria->id = false;
        }

        $criteria->fieldId = $this->model->id;
        $criteria->locale = $this->element->locale;

        // Set the initially matched elements if $value is already set, which is the case if there was a validation
        // error or we're loading an entry revision.
        if (is_array($value) || $value === '') {
            $criteria->status = null;
            $criteria->localeEnabled = null;
            $criteria->limit = null;

            if (is_array($value)) {
                $prevElement = null;

                foreach ($value as $element) {
                    if ($prevElement) {
                        $prevElement->setNext($element);
                        $element->setPrev($prevElement);
                    }

                    $prevElement = $element;
                }

                $criteria->setMatchedElements($value);

            } else if ($value === '') {
                // Means there were no blocks
                $criteria->setMatchedElements(array());
            }
        }

        if ($this->settings->staticField) {
            return $criteria[0];
        } else {
            return $criteria;
        }
    }

    public function modifyElementsQuery(DbCommand $query, $value)
    {
        if ($value == 'not :empty:') {
            $value = ':notempty:';
        }

        if ($value == ':notempty:' || $value == ':empty:') {
            $alias = 'supertableblocks_'.$this->model->handle;
            $operator = ($value == ':notempty:' ? '!=' : '=');

            $query->andWhere(
                "(select count({$alias}.id) from {{supertableblocks}} {$alias} where {$alias}.ownerId = elements.id and {$alias}.fieldId = :fieldId) {$operator} 0",
                array(':fieldId' => $this->model->id)
            );
        } else if ($value !== null) {
            return false;
        }
    }

    public function getInputHtml($name, $value)
    {
        $id = craft()->templates->formatInputId($name);
        $settings = $this->getSettings();
        
        if ($value instanceof ElementCriteriaModel) {
            $value->limit = null;
            $value->status = null;
            $value->localeEnabled = null;
        }

        $blockTypes = $settings->getBlockTypes();
        $table = ($blockTypes) ? $blockTypes[0] : null;
        
        $html = craft()->templates->render('supertable/'.$settings->fieldLayout.'Input', array(
            'id' => $id,
            'name' => $name,
            'table' => $table,
            'blocks' => $value,
            'settings'  => $settings,
        ));

        // Get the block types data
        $blockTypeInfo = $this->_getBlockTypeInfoForInput($name);

        craft()->templates->includeJsResource('supertable/js/SuperTableInput.js');

        craft()->templates->includeJs('new Craft.SuperTableInput'.ucfirst($settings->fieldLayout).'(' .
            '"'.craft()->templates->namespaceInputId($id).'", ' .
            JsonHelper::encode($blockTypeInfo).', ' .
            '"'.craft()->templates->namespaceInputName($name).'", ' .
            JsonHelper::encode($settings).
        ');');

        return $html;
    }

    public function prepValueFromPost($data)
    {
        // Get the possible block types for this field
        $blockTypes = craft()->superTable->getBlockTypesByFieldId($this->model->id, 'id');

        if (!is_array($data)) {
            return array();
        }

        $oldBlocksById = array();

        // Get the old blocks that are still around
        if (!empty($this->element->id)) {
            $ownerId = $this->element->id;

            $ids = array();

            foreach (array_keys($data) as $blockId) {
                if (is_numeric($blockId) && $blockId != 0) {
                    $ids[] = $blockId;
                }
            }

            if ($ids) {
                $criteria = craft()->elements->getCriteria('SuperTable_Block');
                $criteria->fieldId = $this->model->id;
                $criteria->ownerId = $ownerId;
                $criteria->id = $ids;
                $criteria->limit = null;
                $criteria->status = null;
                $criteria->localeEnabled = null;
                $criteria->locale = $this->element->locale;
                $oldBlocks = $criteria->find();

                // Index them by ID
                foreach ($oldBlocks as $oldBlock) {
                    $oldBlocksById[$oldBlock->id] = $oldBlock;
                }
            }
        } else {
            $ownerId = null;
        }

        $blocks = array();
        $sortOrder = 0;

        foreach ($data as $blockId => $blockData) {
            if (!isset($blockData['type']) || !isset($blockTypes[$blockData['type']])) {
                continue;
            }

            $blockType = $blockTypes[$blockData['type']];

            // Is this new? (Or has it been deleted?)
            if (strncmp($blockId, 'new', 3) === 0 || !isset($oldBlocksById[$blockId])) {
                $block = new SuperTable_BlockModel();
                $block->fieldId = $this->model->id;
                $block->typeId  = $blockType->id;
                $block->ownerId = $ownerId;
                $block->locale  = $this->element->locale;
            } else {
                $block = $oldBlocksById[$blockId];
            }

            $block->setOwner($this->element);
            $block->enabled = (isset($blockData['enabled']) ? (bool) $blockData['enabled'] : true);

            // Set the content post location on the block if we can
            $ownerContentPostLocation = $this->element->getContentPostLocation();

            if ($ownerContentPostLocation) {
                $block->setContentPostLocation("{$ownerContentPostLocation}.{$this->model->handle}.{$blockId}.fields");
            }

            if (isset($blockData['fields'])) {
                $block->setContentFromPost($blockData['fields']);
            }

            $sortOrder++;
            $block->sortOrder = $sortOrder;

            $blocks[] = $block;
        }

        return $blocks;
    }

    public function validate($blocks)
    {
        $errors = array();
        $blocksValidate = true;

        foreach ($blocks as $block) {
            if (!craft()->superTable->validateBlock($block)) {
                $blocksValidate = false;
            }
        }

        if (!$blocksValidate) {
            $errors[] = Craft::t('Correct the errors listed above.');
        }

        $maxRows = $this->getSettings()->maxRows;

        if ($maxRows && count($blocks) > $maxRows) {
            if ($maxRows == 1) {
                $errors[] = Craft::t('There can’t be more than one row.');
            } else {
                $errors[] = Craft::t('There can’t be more than {max} rows.', array('max' => $maxRows));
            }
        }

        $minRows = $this->getSettings()->minRows;

        if ($minRows && count($blocks) < $minRows) {
            if ($minRows == 1) {
                $errors[] = Craft::t('There must be at least one row.');
            } else {
                $errors[] = Craft::t('There must be at least {min} rows.', array('min' => $minRows));
            }
        }

        if ($errors) {
            return $errors;
        } else {
            return true;
        }
    }

    public function getSearchKeywords($value)
    {
        if ($value) {
            $keywords = array();
            $contentService = craft()->content;
    
            if ($this->settings->staticField) {
                $value = array($value);
            }
    
            foreach ($value as $block) {
                $originalContentTable      = $contentService->contentTable;
                $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;
                $originalFieldContext      = $contentService->fieldContext;
    
                $contentService->contentTable      = $block->getContentTable();
                $contentService->fieldColumnPrefix = $block->getFieldColumnPrefix();
                $contentService->fieldContext      = $block->getFieldContext();
    
                foreach (craft()->fields->getAllFields() as $field) {
                    $fieldType = $field->getFieldType();
    
                    if ($fieldType) {
                        $fieldType->element = $block;
                        $handle = $field->handle;
                        $keywords[] = $fieldType->getSearchKeywords($block->getFieldValue($handle));
                    }
                }
    
                $contentService->contentTable      = $originalContentTable;
                $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;
                $contentService->fieldContext      = $originalFieldContext;
            }
            return parent::getSearchKeywords($keywords);
        }
    }

    public function onAfterElementSave()
    {
        craft()->superTable->saveField($this);
    }

    public function getEagerLoadingMap($sourceElements)
    {
        // Get the source element IDs
        $sourceElementIds = array();

        foreach ($sourceElements as $sourceElement) {
            $sourceElementIds[] = $sourceElement->id;
        }

        // Return any relation data on these elements, defined with this field
        $map = craft()->db->createCommand()
            ->select('ownerId as source, id as target')
            ->from('supertableblocks')
            ->where(
                array('and', 'fieldId=:fieldId', array('in', 'ownerId', $sourceElementIds)),
                array(':fieldId' => $this->model->id)
            )
            ->order('sortOrder')
            ->queryAll();

        return array(
            'elementType' => 'SuperTable_Block',
            'map' => $map,
            'criteria' => array('fieldId' => $this->model->id)
        );
    }


    // Protected Methods
    // =========================================================================

    protected function getSettingsModel()
    {
        $settings = new SuperTable_SettingsModel($this->model);

        if (!$settings->selectionLabel) {
            $settings->selectionLabel = Craft::t('Add a row');
        }

        if (!$settings->fieldLayout) {
            $settings->fieldLayout = 'table';
        }

        return $settings;
    }


    // Private Methods
    // =========================================================================

    private function _getFieldTypeInfoForConfigurator()
    {
        $fieldTypes = array();

        // Set a temporary namespace for these
        $originalNamespace = craft()->templates->getNamespace();
        $namespace = craft()->templates->namespaceInputName('blockTypes[__BLOCK_TYPE_ST__][fields][__FIELD_ST__][typesettings]', $originalNamespace);
        craft()->templates->setNamespace($namespace);

        foreach (craft()->fields->getAllFieldTypes() as $fieldType) {
            $fieldTypeClass = $fieldType->getClassHandle();

            // No SuperTable-Inception, sorry buddy.
            if ($fieldTypeClass == 'SuperTable') {
                continue;
            }

            craft()->templates->startJsBuffer();

            // A Matrix field will fetch all available fields, grabbing their Settings HTML. Then Super Table will do the same,
            // causing an infinite loop - extract some methods from MatrixFieldType
            if ($fieldTypeClass == 'Matrix') {
                $settingsBodyHtml = craft()->templates->namespaceInputs(craft()->superTable_matrix->getMatrixSettingsHtml($fieldType));
            } else {
                $settingsBodyHtml = craft()->templates->namespaceInputs($fieldType->getSettingsHtml());
            }

            $settingsFootHtml = craft()->templates->clearJsBuffer();

            $fieldTypes[] = array(
                'type'             => $fieldTypeClass,
                'name'             => $fieldType->getName(),
                'settingsBodyHtml' => $settingsBodyHtml,
                'settingsFootHtml' => $settingsFootHtml,
            );
        }

        craft()->templates->setNamespace($originalNamespace);

        return $fieldTypes;
    }

    private function _getBlockTypeInfoForInput($name)
    {
        $settings = $this->getSettings();

        $blockType = array();

        // Set a temporary namespace for these
        $originalNamespace = craft()->templates->getNamespace();
        $namespace = craft()->templates->namespaceInputName($name.'[__BLOCK_ST__][fields]', $originalNamespace);
        craft()->templates->setNamespace($namespace);

        foreach ($settings->getBlockTypes() as $blockType) {
            // Create a fake SuperTable_BlockModel so the field types have a way to get at the owner element, if there is one
            $block = new SuperTable_BlockModel();
            $block->fieldId = $this->model->id;
            $block->typeId = $blockType->id;

            if ($this->element) {
                $block->setOwner($this->element);
                $block->locale = $this->element->locale;
            }

            $fieldLayoutFields = $blockType->getFieldLayout()->getFields();

            foreach ($fieldLayoutFields as $fieldLayoutField) {
                $fieldType = $fieldLayoutField->getField()->getFieldType();

                if ($fieldType) {
                    $fieldType->element = $block;
                    $fieldType->setIsFresh(true);
                }
            }

            craft()->templates->startJsBuffer();

            $bodyHtml = craft()->templates->namespaceInputs(craft()->templates->render('supertable/fields', array(
                'namespace'     => null,
                'fields'        => $fieldLayoutFields,
                'settings'      => $settings,
            )));

            // Reset $_isFresh's
            foreach ($fieldLayoutFields as $fieldLayoutField) {
                $fieldType = $fieldLayoutField->getField()->getFieldType();

                if ($fieldType) {
                    $fieldType->setIsFresh(null);
                }
            }

            $footHtml = craft()->templates->clearJsBuffer();

            $blockType = array(
                'type' => $blockType->id,
                'bodyHtml' => $bodyHtml,
                'footHtml' => $footHtml,
            );
        }

        craft()->templates->setNamespace($originalNamespace);

        return $blockType;
    }


}
