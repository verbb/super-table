<?php
namespace Craft;

class SuperTableController extends BaseController
{
    public function actionGetTableRow()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $tableId = craft()->request->getRequiredPost('tableId');
        $rowId = craft()->request->getRequiredPost('rowId');
        $cols = craft()->request->getRequiredPost('cols');
        $name = craft()->request->getRequiredPost('name');

        foreach ($cols as $colId => $col) {
            $field = craft()->superTable_table->getFieldById($tableId, $col['fieldId']);

            $fieldtype = craft()->superTable_table->populateFieldType($tableId, $field);

            $cols[$colId]['fieldType'] = $fieldtype;
        }

        $variables = array(
            'tableId' => $tableId,
            'rowId' => $rowId,
            'cols' => $cols,
            'name' => $name,
        );

        craft()->templates->startJsBuffer();
        $returnData['html'] = $this->renderTemplate('supertable/row', $variables, true);
        $returnData['js'] = craft()->templates->clearJsBuffer();

        $this->returnJson($returnData);
    }

    public function actionGetModalBody()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $fieldId = craft()->request->getPost('fieldId');
        $tableId = craft()->request->getPost('tableId');
        $fieldType = craft()->request->getPost('fieldType');

        // If there isn't a field yet, this is a brand-new row thats been added
        if ($fieldId) {

            // Get the field
            $field = craft()->superTable_table->getFieldById($tableId, $fieldId);

            // Get the fieldtype for this field
            $fieldtype = craft()->superTable_table->getFieldtypeForField($field);

            // Grab the fieldtypes Settings in HTML - complete with populated settings
            $fieldtype->setSettings($field->settings);
        } else {

            // No field yet - get default
            $fieldtype = craft()->fields->getFieldType($fieldType);
        }

        $variables = array(
            'fieldType' => $fieldtype,
        );

        // Don't process the output yet - issues with JS in template...
        $returnData = $this->renderTemplate('supertable/settings/modal', $variables, false, false);

        $this->returnJson($returnData);
    }

    public function actionSetSettingsFromModal()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $fieldId = craft()->request->getRequiredPost('fieldId');
        $tableId = craft()->request->getRequiredPost('tableId');
        $settings = craft()->request->getRequiredPost('settings');

        // Get the field
        $field = craft()->superTable_table->getFieldById($tableId, $fieldId);

        $returnData = craft()->superTable_table->setFieldSettings($tableId, $field, $settings);

        $this->returnJson($returnData);
    }

    /*public function actionGetBlankField()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        //$fieldId = craft()->request->getRequiredPost('fieldId');
        //$tableId = craft()->request->getRequiredPost('tableId');
        //$settings = craft()->request->getRequiredPost('settings');

        // Get the field
        //$field = craft()->superTable_table->getFieldById($tableId, $fieldId);

        $field = craft()->superTable_table->getFieldById('224', '272');

        //$fieldtype = craft()->superTable_table->getFieldtypeForField($field);

        //$returnData = $fieldtype->getInputHtml('', '');



        $variables = array(
            'col' => array(
                'name'      => $field->name,
                'handle'    => $field->handle,
                'type'      => $field->type,
                'settings'  => $field->settings,
                'fieldType' => $field->fieldtype,



            ),
            'value' => '',
            'cellName' => 'fields[superTable'.$field->type.'][row1][col2]',
        );



        $returnData = $this->renderTemplate('supertable/_fields/categories', $variables, false, false);


        $this->returnJson($returnData);
    }

    public function actionCreateSuperTable()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $settings = array(
            'groupId' => craft()->request->getRequiredPost('group'),
            'name' => craft()->request->getRequiredPost('name'),
            'handle' => craft()->request->getRequiredPost('handle'),
            'instructions' => craft()->request->getRequiredPost('instructions'),
            //'translatable' => craft()->request->getPost('translatable'),
            'type' => craft()->request->getRequiredPost('type'),
        );

        $returnData = craft()->superTable_table->createNewTable($settings);

        $this->returnJson($returnData);
    }*/
}

