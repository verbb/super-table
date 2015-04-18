<?php
namespace Craft;

class SuperTableFieldType extends BaseFieldType
{
    public function getName()
    {
        return Craft::t('Super Table');
    }

    public function defineContentAttribute()
    {
        return AttributeType::Mixed;
        //return false;
    }

    public function getSettingsHtml()
    {
        $columns = $this->getSettings()->columns;
        $defaults = $this->getSettings()->defaults;

        //if (!$columns) {
            //$columns = array('col1' => array('heading' => '', 'handle' => '', 'type' => 'SuperTable_Label'));

            // Update the actual settings model for getInputHtml()
            //$this->getSettings()->columns = $columns;
        //} else {

        if ($columns) {
            // Each column in the SuperTable's settings contains a fieldId and width value.
            // Before going on, grab a FieldModel of this field, so we can display its details.
            foreach ($columns as &$column) {
                // Attach the individual field information
                if ($this->model) {
                    $column = craft()->superTable_table->getFieldForColumn($this->model->id, $column);
                }
            }
        }

        if ($defaults === null) {
            $defaults = array('row1' => array());
        }

        $fieldTypeOptions = array();

        foreach (craft()->fields->getAllFieldTypes() as $fieldType) {
            $fieldTypeOptions[] = array('label' => $fieldType->getName(), 'value' => $fieldType->getClassHandle());
        }

        $columnSettings = array(
            'heading' => array(
                'heading' => Craft::t('Column Heading'),
                'type' => 'singleline',
                'autopopulate' => 'handle'
            ),
            'handle' => array(
                'heading' => Craft::t('Handle'),
                'class' => 'code',
                'type' => 'singleline'
            ),
            'width' => array(
                'heading' => Craft::t('Width'),
                'class' => 'code',
                'type' => 'singleline',
                'width' => 50
            ),
            'type' => array(
                'heading' => Craft::t('Type'),
                'class' => 'thin',
                'type' => 'select',
                'options' => $fieldTypeOptions,
            ),
        );

        craft()->templates->includeCssResource('supertable/css/supertable.css');
        craft()->templates->includeJsResource('supertable/js/supertable.js');
        craft()->templates->includeJsResource('supertable/js/supertable-settings.js');

        craft()->templates->includeJsResource('supertable/js/SuperTableFieldSettings.js');
        craft()->templates->includeJs('new Craft.SuperTableFieldSettings(' .
            '"'.craft()->templates->namespaceInputName('columns').'", ' .
            '"'.craft()->templates->namespaceInputName('defaults').'", ' .
            JsonHelper::encode($columns).', ' .
            JsonHelper::encode($defaults).', ' .
            JsonHelper::encode($columnSettings) .
        ');');

        $columnsField = craft()->templates->render('supertable/settings', array(
            'label'        => Craft::t('Table Columns'),
            'instructions' => Craft::t('Define the columns your Super Table should have.'),
            'id'           => 'columns',
            'name'         => 'columns',
            'cols'         => $columnSettings,
            'rows'         => $columns,
            'addRowLabel'  => Craft::t('Add a column'),
            'tableId'       => $this->model['id'],
        ));

        /*$defaultsField = craft()->templates->render('supertable/field', array(
            'label'        => Craft::t('Default Values'),
            'instructions' => Craft::t('Define the default values for the field.'),
            'id'           => 'defaults',
            'name'         => 'defaults',
            'cols'         => $columns,
            'rows'         => $defaults,
        ));*/

        return $columnsField;//.$defaultsField;
    }

    public function getInputHtml($name, $value)
    {
        $columns = $this->getSettings()->columns;
        $defaults = $this->getSettings()->defaults;

        $input = '<input type="hidden" name="'.$name.'" value="">';

        if ($columns) {
            foreach ($columns as &$column) {
                // Attach the individual field information
                $column = craft()->superTable_table->getFieldForColumn($this->model->id, $column);

                // Translate the column headings
                if (!empty($column['heading'])) {
                    $column['heading'] = Craft::t($column['heading']);
                }
            }

            if (!$value) {
                if (is_array($defaults)) {
                    $value = array_values($defaults);
                }
            }

            $id = craft()->templates->formatInputId($name);

            $input .= craft()->templates->render('supertable/input-table', array(
                'id'        => $id,
                'tableId'   => $this->model->id,
                'name'      => $name,
                'cols'      => $columns,
                'rows'      => $value,
            ));
        }

        return $input;
    }

    public function prepValueFromPost($value)
    {
        if (is_array($value)) {
            // Drop the string row keys
            return array_values($value);
        }
    }

    public function prepValue($value)
    {
        if (is_array($value) && ($columns = $this->getSettings()->columns)) {
            // Make the values accessible from both the col IDs and the handles
            foreach ($value as &$row) {
                foreach ($columns as $colId => $col) {
                    $col = craft()->superTable_table->getFieldForColumn($this->model->id, $col);

                    if ($col['handle']) {
                        $row[$col['handle']] = (isset($row[$colId]) ? $row[$colId] : null);
                    }
                }
            }

            return $value;
        }
    }

    protected function defineSettings()
    {
        return array(
            'columns' => AttributeType::Mixed,
            'defaults' => AttributeType::Mixed,
        );
    }

    public function onAfterSave() {
        // Create the contents table to store field values in - if doesnt exist
        craft()->superTable_table->createContentTable($this->model->id);

        // Now that the SuperTable field has been saved, we can use it's ID to link things together.
        $settings = $this->getSettings();
        $columns = $settings->columns;
        $fieldSettings = $columns;

        // We only want to store a reference to the fieldId in this SuperTable's settings object
        unset($settings['columns']);

        // First, we have to loop through all fields for this SuperTable as stored in the DB.
        // If they're not contained in the $fields object, they need to be deleted
        craft()->superTable_table->checkForFieldsToDelete($this->model->id, $fieldSettings);

        // Loop through each column in the table, and save/update it's corresponding external field
        if ($fieldSettings) {
            foreach ($fieldSettings as $colKey => $column) {
                $saveResponse = craft()->superTable_table->saveFields($this->model->id, $column);

                // If there was an error saving the field - don't save it in the field's settings
                if (array_key_exists('error', $saveResponse)) {

                    return false;
                } else {
                    // These are the only values we store in the SuperTable settings
                    $columns[$colKey] = array(
                        'fieldId'   => $saveResponse['fieldId'],
                        'width'     => $column['width'],
                    );
                }
            }
        }

        // Then, save the linked fieldId's for each column back to this SuperTable's settings.
        // But this needs to be a direct DB Query - otherwise will create a nasty infinite save loop (this is onAfterSave after all).
        $settings->columns = $columns;
        craft()->superTable_table->saveSettings($this->model->id, $settings);
    }

    public function onBeforeDelete() {
        // Before we delete this SuperTable field, we need to:
        // - Delete all fields attached to this SuperTable
        // - Delete the contentTable for this SuperTable
        // - Delete the actual SuperTable field (done automatically after this)
        craft()->superTable_table->deleteTable($this->model->id);
    }
}
