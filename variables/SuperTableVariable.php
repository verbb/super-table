<?php
namespace Craft;

class SuperTableVariable
{

	//
	// Having a Matrix-SuperTable-Matrix layout will cause issues becase it will try to apply the namespace for the top-level
	// Matrix field, which means inner-Matrix fields will not work properly. Very hacky, but we need to replicate the Matrix
	// getInputHtml() function with alternative namespaces.
	//

	public function getMatrixInputHtml($fieldType, $name, $value)
	{
		$id = craft()->templates->formatInputId($name);
		$settings = $fieldType->getSettings();

		if ($value instanceof ElementCriteriaModel)
		{
			$value->limit = null;
			$value->status = null;
			$value->localeEnabled = null;
		}

		$html = craft()->templates->render('_components/fieldtypes/Matrix/input', array(
			'id' => $id,
			'name' => $name,
			'blockTypes' => $settings->getBlockTypes(),
			'blocks' => $value,
			'static' => false
		));

		// Get the block types data
		$blockTypeInfo = $this->_getBlockTypeInfoForInput($fieldType, $name);

		craft()->templates->includeJsResource('supertable/js/MatrixInputAlt.js');

		craft()->templates->includeJs('new Craft.MatrixInputAlt(' .
			'"'.craft()->templates->namespaceInputId($id).'", ' .
			JsonHelper::encode($blockTypeInfo).', ' .
			'"'.craft()->templates->namespaceInputName($name).'", ' .
			($settings->maxBlocks ? $settings->maxBlocks : 'null') .
		');');

		craft()->templates->includeTranslations('Disabled', 'Actions', 'Collapse', 'Expand', 'Disable', 'Enable', 'Add {type} above', 'Add a block');

		return $html;
	}

	private function _getBlockTypeInfoForInput($fieldType, $name)
	{
		$blockTypes = array();

		// Set a temporary namespace for these
		$originalNamespace = craft()->templates->getNamespace();
		$namespace = craft()->templates->namespaceInputName($name.'[__BLOCK2__][fields]', $originalNamespace);
		craft()->templates->setNamespace($namespace);

		foreach ($fieldType->getSettings()->getBlockTypes() as $blockType)
		{
			// Create a fake MatrixBlockModel so the field types have a way to get at the owner element, if there is one
			$block = new MatrixBlockModel();
			$block->fieldId = $fieldType->model->id;
			$block->typeId = $blockType->id;

			if ($fieldType->element)
			{
				$block->setOwner($fieldType->element);
			}

			$fieldLayoutFields = $blockType->getFieldLayout()->getFields();

			foreach ($fieldLayoutFields as $fieldLayoutField)
			{
				$fieldType = $fieldLayoutField->getField()->getFieldType();

				if ($fieldType)
				{
					$fieldType->element = $block;
					$fieldType->setIsFresh(true);
				}
			}

			craft()->templates->startJsBuffer();

			$bodyHtml = craft()->templates->namespaceInputs(craft()->templates->render('_includes/fields', array(
				'namespace' => null,
				'fields'    => $fieldLayoutFields
			)));

			// Reset $_isFresh's
			foreach ($fieldLayoutFields as $fieldLayoutField)
			{
				$fieldType = $fieldLayoutField->getField()->getFieldType();

				if ($fieldType)
				{
					$fieldType->setIsFresh(null);
				}
			}

			$footHtml = craft()->templates->clearJsBuffer();

			$blockTypes[] = array(
				'handle'   => $blockType->handle,
				'name'     => Craft::t($blockType->name),
				'bodyHtml' => $bodyHtml,
				'footHtml' => $footHtml,
			);
		}

		craft()->templates->setNamespace($originalNamespace);

		return $blockTypes;
	}

}