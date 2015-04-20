<?php
namespace Craft;

class SuperTableFieldType extends BaseFieldType
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

        $columnSettings = array(
            'name' => array(
                'heading' => Craft::t('Column Heading'),
                'type' => 'singleline',
                'autopopulate' => 'handle'
            ),
            'handle' => array(
                'heading' => Craft::t('Handle'),
                'class' => 'code',
                'type' => 'singleline'
            ),
            'type' => array(
                'heading' => Craft::t('Type'),
                'class' => 'thin',
                'type' => 'select',
                'options' => $fieldTypeOptions,
            ),
        );

        craft()->templates->includeJsResource('supertable/js/SuperTableSettingsModal.js');
        craft()->templates->includeJs('new Craft.SuperTableSettingsModals(' .
            JsonHelper::encode($fieldTypeInfo). ', ' .
            JsonHelper::encode($this->getSettings()->getBlockTypes()) . 
        ');');

        craft()->templates->includeJsResource('supertable/js/SuperTableConfigurator.js');
        craft()->templates->includeJs('new Craft.SuperTableConfigurator(' .
            '"'.craft()->templates->getNamespace().'", ' .
            JsonHelper::encode('').', ' .
            JsonHelper::encode($this->getSettings()->getBlockTypes()).', ' .
            JsonHelper::encode($columnSettings).', ' .
            JsonHelper::encode($fieldTypeInfo) .
        ');');

		return craft()->templates->render('supertable/settings', array(
			'settings'   => $this->getSettings(),
			'fieldTypes' => $fieldTypeOptions
		));
	}

	public function prepSettings($settings)
	{
		if ($settings instanceof SuperTable_SettingsModel) {
			return $settings;
		}

		$superTableSettings = new SuperTable_SettingsModel($this->model);
		$blockTypes = array();

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
						$field->type         = $fieldSettings['type'];

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

        //var_dump($value);

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

		return $criteria;
	}

	public function getInputHtml($name, $value)
	{
		$id = craft()->templates->formatInputId($name);
		$settings = $this->getSettings();

		// Get the block types data
		$blockTypeInfo = $this->_getBlockTypeInfoForInput($name);

		craft()->templates->includeJsResource('supertable/js/SuperTableInput.js');

		craft()->templates->includeJs('new Craft.SuperTableInput(' .
			'"'.craft()->templates->namespaceInputId($id).'", ' .
			JsonHelper::encode($blockTypeInfo).', ' .
			'"'.craft()->templates->namespaceInputName($name).'"' .
		');');

		if ($value instanceof ElementCriteriaModel) {
			$value->limit = null;
			$value->status = null;
			$value->localeEnabled = null;
		}

		return craft()->templates->render('supertable/input', array(
			'id' => $id,
			'name' => $name,
            'table' => $settings->getBlockTypes()[0],
			'blocks' => $value,
			'static' => false
		));
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

		if ($errors) {
			return $errors;
		} else {
			return true;
		}
	}

	public function getSearchKeywords($value)
	{
		$keywords = array();
		$contentService = craft()->content;

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

	public function onAfterElementSave()
	{
		craft()->superTable->saveField($this);
	}

	// Protected Methods
	// =========================================================================

	protected function getSettingsModel()
	{
		return new SuperTable_SettingsModel($this->model);
	}

	// Private Methods
	// =========================================================================

	private function _getFieldTypeInfoForConfigurator()
	{
		$fieldTypes = array();

		// Set a temporary namespace for these
		$originalNamespace = craft()->templates->getNamespace();
		$namespace = craft()->templates->namespaceInputName('blockTypes[__BLOCK_TYPE__][fields][__FIELD__][typesettings]', $originalNamespace);
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
				$settingsBodyHtml = craft()->templates->namespaceInputs($this->getMatrixSettingsHtml());
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
		$blockType = array();

		// Set a temporary namespace for these
		$originalNamespace = craft()->templates->getNamespace();
		$namespace = craft()->templates->namespaceInputName($name.'[__BLOCK__][fields]', $originalNamespace);
		craft()->templates->setNamespace($namespace);

		foreach ($this->getSettings()->getBlockTypes() as $blockType) {
			// Create a fake SuperTable_BlockModel so the field types have a way to get at the owner element, if there is one
			$block = new SuperTable_BlockModel();
			$block->fieldId = $this->model->id;
			$block->typeId = $blockType->id;

			if ($this->element) {
				$block->setOwner($this->element);
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
				'namespace' => null,
				'fields'    => $fieldLayoutFields
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




	//
	// Extracted from MatrixFieldType - must be modified otherwise will create infinite loop
	//

	public function getMatrixSettingsHtml()
	{
		$matrixFieldType = craft()->fields->getFieldType('Matrix');

		// Get the available field types data
		$fieldTypeInfo = $this->_getMatrixFieldTypeInfoForConfigurator();

		craft()->templates->includeJsResource('js/MatrixConfigurator.js');
		craft()->templates->includeJs('new Craft.MatrixConfigurator('.JsonHelper::encode($fieldTypeInfo).', "'.craft()->templates->getNamespace().'");');

		craft()->templates->includeTranslations(
			'What this block type will be called in the CP.',
			'How you’ll refer to this block type in the templates.',
			'Are you sure you want to delete this block type?',
			'This field is required',
			'This field is translatable',
			'Field Type',
			'Are you sure you want to delete this field?'
		);

		$fieldTypeOptions = array();

		foreach (craft()->fields->getAllFieldTypes() as $fieldType)
		{
			// No Matrix-Inception, sorry buddy.
			if ($fieldType->getClassHandle() != 'Matrix' && $fieldType->getClassHandle() != 'SuperTable')
			{
				$fieldTypeOptions[] = array('label' => $fieldType->getName(), 'value' => $fieldType->getClassHandle());
			}
		}

		return craft()->templates->render('_components/fieldtypes/Matrix/settings', array(
			'settings'   => $matrixFieldType->getSettings(),
			'fieldTypes' => $fieldTypeOptions
		));
	}

	private function _getMatrixFieldTypeInfoForConfigurator()
	{
		$fieldTypes = array();

		// Set a temporary namespace for these
		$originalNamespace = craft()->templates->getNamespace();
		$namespace = craft()->templates->namespaceInputName('blockTypes[__BLOCK_TYPE__][fields][__FIELD__][typesettings]', $originalNamespace);
		craft()->templates->setNamespace($namespace);

		foreach (craft()->fields->getAllFieldTypes() as $fieldType)
		{
			$fieldTypeClass = $fieldType->getClassHandle();

			// No Matrix-Inception, sorry buddy.
			if ($fieldTypeClass == 'Matrix' || $fieldTypeClass == 'SuperTable')
			{
				continue;
			}

			craft()->templates->startJsBuffer();
			$settingsBodyHtml = craft()->templates->namespaceInputs($fieldType->getSettingsHtml());
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


}
