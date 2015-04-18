<?php
namespace Craft;

class SuperTable_TableService extends BaseApplicationComponent
{
	public function getFieldById($fieldModelId, $fieldId)
	{
		$oldFieldContext = craft()->content->fieldContext;
		//$oldContentTable = craft()->content->contentTable;
		craft()->content->fieldContext = 'superTable:' . $fieldModelId;
		//craft()->content->contentTable = 'supertablecontent_' . $fieldModelId;

		$field = craft()->fields->getFieldById($fieldId);

		craft()->content->fieldContext = $oldFieldContext;
		//craft()->content->contentTable = $oldContentTable;

		return $field;
	}

	public function getFieldByHandle($fieldModelId, $fieldId)
	{
		$oldFieldContext = craft()->content->fieldContext;
		//$oldContentTable = craft()->content->contentTable;
		craft()->content->fieldContext = 'superTable:' . $fieldModelId;
		//craft()->content->contentTable = 'supertablecontent_' . $fieldModelId;

		$field = craft()->fields->getFieldByHandle($fieldId);

		craft()->content->fieldContext = $oldFieldContext;
		//craft()->content->contentTable = $oldContentTable;

		return $field;
	}

	public function populateFieldType($fieldModelId, FieldModel $field, $element = null)
	{
		$oldFieldContext = craft()->content->fieldContext;
		//$oldContentTable = craft()->content->contentTable;
		craft()->content->fieldContext = 'superTable:' . $fieldModelId;
		//craft()->content->contentTable = 'supertablecontent_' . $fieldModelId;

		$field = craft()->fields->populateFieldType($field, $element);
		
		craft()->content->fieldContext = $oldFieldContext;
		//craft()->content->contentTable = $oldContentTable;

		return $field;
	}

	public function createContentTable($fieldModelId) {
		if (!craft()->db->tableExists('supertablecontent_' . $fieldModelId)) {
			$this->_createContentTable('supertablecontent_' . $fieldModelId);
		}
	}


	// The main function when saving fields from the Settings screen
	public function saveFields($fieldModelId, $settings) {
		$oldFieldContext = craft()->content->fieldContext;
		//$oldContentTable = craft()->content->contentTable;
		craft()->content->fieldContext = 'superTable:' . $fieldModelId;
		//craft()->content->contentTable = 'supertablecontent_' . $fieldModelId;

		$field = new FieldModel();
		$existingField = $this->getFieldByHandle($fieldModelId, $settings['handle']);

		if ($existingField) {
			$field = $existingField;
		}

        $field->name 	= $settings['heading'];
        $field->handle 	= $settings['handle'];
        $field->type 	= $settings['type'];

        // if we're creating this field from scratch - load the default field settings
        if (!$field->settings) {
	        $fieldtype = $this->getFieldtypeForField($field);
	        if ($fieldtype) {
		        $field->settings = $fieldtype->getSettings();
	        }
       	}

        // Save the field
		if (craft()->fields->saveField($field)) {
            Craft::log($field->name . ' field saved successfully.');

			craft()->content->fieldContext = $oldFieldContext;
			//craft()->content->contentTable = $oldContentTable;
            
			return array('success' => true, 'fieldId' => $field->id);
        } else {
            Craft::log('Could not save the '.$field->name.' field.', LogLevel::Error);

			craft()->content->fieldContext = $oldFieldContext;
			//craft()->content->contentTable = $oldContentTable;

            return array('error' => $field->getErrors(), 'fieldId' => $field->id);
        }
	}

	// Handles deleting old fields (for this table) that we've removed on the client-side table.
	// We don't want them hanging around in the DB!
	public function checkForFieldsToDelete($fieldModelId, $columns) {
		$oldFieldContext = craft()->content->fieldContext;
		//$oldContentTable = craft()->content->contentTable;
		craft()->content->fieldContext = 'superTable:' . $fieldModelId;
		//craft()->content->contentTable = 'supertablecontent_' . $fieldModelId;

		$fields = craft()->fields->getAllFields('id');

		// If our list of fields to be saved and existing fields are different - something has changed!
		if (count($fields) != count($columns)) {

			// Build our array of just fieldId's that are on the client-side
			$clientFields = array();

			// If there are no columns, we've removed all of them from the front-end
			if (count($columns) == 0) {
				$clientFields[] = '';
			} else {
				foreach ($columns as $column) {
					if (array_key_exists('handle', $column)) {
						$clientFields[] = $column['handle'];
					}
				}
			}

			foreach ($fields as $field) {
				if (!in_array($field->handle, $clientFields)) {

					// This field is in the database - but no longer hooked to a this table! Time to delete...
					if (craft()->fields->deleteField($field)) {
			            Craft::log($field->name . ' field deleted successfully.');
			        } else {
			            Craft::log('Could not delete the '.$field->name.' field.', LogLevel::Error);
			        }
				}
			}
		}

		craft()->content->fieldContext = $oldFieldContext;
		//craft()->content->contentTable = $oldContentTable;
	}

	// Simply delete all fields attached to this SuperTable - only used when deleting the SuperTable field
	public function deleteTableFields($fieldModelId) {
		$oldFieldContext = craft()->content->fieldContext;
		//$oldContentTable = craft()->content->contentTable;
		craft()->content->fieldContext = 'superTable:' . $fieldModelId;
		//craft()->content->contentTable = 'supertablecontent_' . $fieldModelId;

		$fields = craft()->fields->getAllFields('id');

		foreach ($fields as $field) {
			if (craft()->fields->deleteField($field)) {
		
			}
		}

		craft()->content->fieldContext = $oldFieldContext;
		//craft()->content->contentTable = $oldContentTable;
	}




	// Easily fetch each field types' settings HTML when needing to set their settings
	public function getFieldtypeForField($field)
	{
		//var_dump($field->type);
		if ($field->type == 'Assets') {
			$fieldtype = new AssetsFieldType();
		} else if ($field->type == 'Categories') {
			$fieldtype = new CategoriesFieldType();
		} else if ($field->type == 'Checkboxes') {
			$fieldtype = new CheckboxesFieldType();
		} else if ($field->type == 'Color') {
			$fieldtype = new ColorFieldType();
		} else if ($field->type == 'Date') {
			$fieldtype = new DateFieldType();
		} else if ($field->type == 'Dropdown') {
			$fieldtype = new DropdownFieldType();
		} else if ($field->type == 'Entries') {
			$fieldtype = new EntriesFieldType();
		} else if ($field->type == 'Lightswitch') {
			$fieldtype = new LightswitchFieldType();
		} else if ($field->type == 'Matrix') {
			$fieldtype = new MatrixFieldType();
		} else if ($field->type == 'MultiSelect') {
			$fieldtype = new MultiSelectFieldType();
		} else if ($field->type == 'Number') {
			$fieldtype = new NumberFieldType();
		} else if ($field->type == 'PlainText') {
			$fieldtype = new PlainTextFieldType();
		} else if ($field->type == 'PositionSelect') {
			$fieldtype = new PositionSelectFieldType();
		} else if ($field->type == 'RadioButtons') {
			$fieldtype = new RadioButtonsFieldType();
		} else if ($field->type == 'RichText') {
			$fieldtype = new RichTextFieldType();
		} else if ($field->type == 'TableField') {
			$fieldtype = new TableFieldType();
		} else if ($field->type == 'Tags') {
			$fieldtype = new TagsFieldType();
		} else if ($field->type == 'Users') {
			$fieldtype = new UsersFieldType();
		} else {
			$fieldtype = null;
		}
		
		//$fieldtype = craft()->fields->getFieldType($field);

		return $fieldtype;
	}

	// Handles settings a field's settings from a Modal window
	public function setFieldSettings($fieldModelId, $field, $settings) {
		$oldFieldContext = craft()->content->fieldContext;
		//$oldContentTable = craft()->content->contentTable;
		craft()->content->fieldContext = 'superTable:' . $fieldModelId;
		//craft()->content->contentTable = 'supertablecontent_' . $fieldModelId;

        $field->settings = $settings['types'][$field->type.'_SuperTable'];

        // Save the settings
		if (craft()->fields->saveField($field)) {
            Craft::log($field->name . ' field settings saved successfully.');

			craft()->content->fieldContext = $oldFieldContext;
			//craft()->content->contentTable = $oldContentTable;

			return array('success' => true, 'tableId' => $field->id);
        } else {
            Craft::log('Could not save the '.$field->name.' field settings.', LogLevel::Error);

			craft()->content->fieldContext = $oldFieldContext;
			//craft()->content->contentTable = $oldContentTable;

			return array('error' => $field->getErrors());
        }
	}

	// When we click on 'populate' button, we're essentially just saving the field
	// but this way we can redirect the user to the correct edit screen.
	/*public function createNewTable($settings) {
		$field = new FieldModel();

		$field->groupId      = $settings['groupId'];
		$field->name         = $settings['name'];
		$field->handle       = $settings['handle'];
		$field->instructions = $settings['instructions'];
		//$field->translatable = $settings['translatable'];
		$field->type         = $settings['type'];

		if (craft()->fields->saveField($field)) {
			return array('success' => true, 'tableId' => $field->id);
		} else {
			return array('error' => $field->getErrors());
		}
	}*/

	public function saveSettings($fieldModelId, $settings)
	{
		$settings = json_encode($settings->getAttributes());
		craft()->db->createCommand()->update('fields', array('settings' => $settings), array('id' => $fieldModelId));
	}


	// Some fields' content needs to be stored somewhere. Its the best idea to section this off into a specialized table.
	// This'll be something like craft_supertablecontent_SuperTableFieldId
	private function _createContentTable($name)
	{
		craft()->db->createCommand()->createTable($name, array(
			'elementId' => array('column' => ColumnType::Int, 'null' => false),
			'locale'    => array('column' => ColumnType::Locale, 'null' => false),
			'title'     => array('column' => ColumnType::Varchar)
		));

		craft()->db->createCommand()->createIndex($name, 'elementId,locale', true);
		craft()->db->createCommand()->addForeignKey($name, 'elementId', 'elements', 'id', 'CASCADE', null);
		craft()->db->createCommand()->addForeignKey($name, 'locale', 'locales', 'locale', 'CASCADE', 'CASCADE');
	}

	public function deleteTable($fieldModelId)
	{
		// Delete form fields attached to this table
		craft()->superTable_table->deleteTableFields($fieldModelId);

		$originalContentTable = craft()->content->contentTable;
		$contentTable = 'supertablecontent_' . $fieldModelId;
		craft()->content->contentTable = $contentTable;

		// Drop the content table
		if (craft()->db->tableExists($contentTable)) {
			craft()->db->createCommand()->dropTable($contentTable);
		}

		craft()->content->contentTable = $originalContentTable;
	}

	public function getFieldForColumn($fieldModelId, $column) {
        $columns = array();

        if (array_key_exists('fieldId', $column)) {
            $field = craft()->superTable_table->getFieldById($fieldModelId, $column['fieldId']);

            //$fieldtype = craft()->superTable_table->getFieldtypeForField($field);

            if ($field) {
                $columns['heading']		= $field->name;
                $columns['handle']		= $field->handle;
                $columns['type']		= $field->type;
                $columns['settings']	= $field->settings;
                $columns['fieldType']	= $field->fieldtype;
            }
        }

        // Merge the existing settings with the field setting (ie - field width is preserved)
        $columns = array_merge($column, $columns);

        return $columns;
    }

}
