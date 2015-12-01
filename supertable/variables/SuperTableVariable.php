<?php
namespace Craft;

class SuperTableVariable
{
    /**
    * Expands the defualt relationship behaviour to include Super Table
    * fields so that the user can filter by those too.
    *
    * For example:
    *
    * ```twig
    * {% set reverseRelatedElements = craft.supertable.getRelatedElements({
    *   relatedTo : {
    *     targetElement: entry,
    *     field: 'superTableFieldHandle.columnHandle'
    *   },
    *   elementType : 'SomePlugin_Element',
    *   criteria : {
    *     id : 'not 123',
    *     section : 'someSection'
    *   }
    * }) %}
    * ```
    *
    * @method getRelatedElements
    * @param  array                $params  Should contain 'relatedTo' but can also optionally
    *                                       include 'elementType' and 'criteria'
    * @return ElementCriteriaModel
    */
    public function getRelatedElements($params = null)
    {
        // Parse out the field handles
        $fieldParams = explode('.', $params['relatedTo']['field']);
        
        // For safety fail early if that didn't work
        if (!isset($fieldParams[0]) || !isset($fieldParams[1])) {
            return false;
        }

        $superTableFieldHandle = $fieldParams[0];
        $superTableBlockFieldHandle = $fieldParams[1];

        // Get the Super Table field and associated block type
        $superTableField = craft()->fields->getFieldByHandle($superTableFieldHandle);
        $superTableBlockType = craft()->superTable->getBlockTypesByFieldId($superTableField->id)[0];
       
        // Loop the fields on the block type and save the first one that matches our handle
        $fieldId = false;
        foreach ($superTableBlockType->getFields() as $field) {
            if ($field->handle === $superTableBlockFieldHandle) {
                $fieldId = $field->id;
                break;
            }
        }

        // Check we got something and update the relatedTo criteria for our next elements call
        if ($fieldId) {
            $params['relatedTo']['field'] = $fieldId;
        } else {
            return false;
        }

        // Get the Super Table Blocks that are related to that field
        $superTableBlocks = craft()->elements->getCriteria('SuperTable_Block', array(
            'relatedTo' => $params['relatedTo']
        ));

        // Loop over the returned Super Table Blocks and save their owner ids
        $elementIds = array();
        foreach ($superTableBlocks as $superTableBlock) {
            $elementIds[] = $superTableBlock->ownerId;
        }

        // Default to getting Entry elements but let the user override
        $elementType = ElementType::Entry;
        if (isset($params['elementType'])) {
            $elementType = $params['elementType'];
        }

        // Start our final criteria with the element ids we just got
        $finalCriteria = array(
            'id' => $elementIds
        );
        
        // Check if the user gave us another criteria model and merge that in
        if (isset($params['criteria'])) {
            $finalCriteria = array_merge($finalCriteria, $params['criteria']);
        }

        // Return our final element criteria
        return craft()->elements->getCriteria($elementType, $finalCriteria);
    }



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

    public function getSuperTableBlocks($fieldId)
    {
        return craft()->superTable->getBlockTypesByFieldId($fieldId);
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
