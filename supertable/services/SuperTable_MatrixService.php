<?php
namespace Craft;

class SuperTable_MatrixService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================


    //
    // Extracted from MatrixFieldType - must be modified otherwise will create infinite loop
    //

    public function getMatrixSettingsHtml($matrixFieldType)
    {
        // Get the available field types data
        $fieldTypeInfo = $this->_getMatrixFieldTypeInfoForConfigurator();

        craft()->templates->includeJsResource('supertable/js/MatrixConfiguratorAlt.js');
        craft()->templates->includeJs('new Craft.MatrixConfiguratorAlt(' . 
            JsonHelper::encode($fieldTypeInfo).', ' . 
            '"'.craft()->templates->getNamespace().'"' .
        ');');

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
            if ($fieldType->getClassHandle() != 'Matrix' && $fieldType->getClassHandle() != 'SuperTable') {
                $fieldTypeOptions[] = array('label' => $fieldType->getName(), 'value' => $fieldType->getClassHandle());
            }
        }

        return craft()->templates->render('_components/fieldtypes/Matrix/settings', array(
            'settings'      => $matrixFieldType->getSettings(),
            'fieldTypes'    => $fieldTypeOptions
        ));
    }

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




    // Private Methods
    // =========================================================================

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
