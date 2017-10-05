<?php
namespace Craft;

use Cake\Utility\Hash as Hash;

class SuperTableFeedMeFieldType extends BaseFeedMeFieldType
{
    // Templates
    // =========================================================================

    public function getMappingTemplate()
    {
        return 'supertable/_integrations/feedme/fields/supertable';
    }
    


    // Public Methods
    // =========================================================================

    public function prepFieldData($element, $field, $fieldData, $handle, $options)
    {
        $preppedData = array();

        $data = Hash::get($fieldData, 'data');

        if (empty($data)) {
            return array();
        }

        // Store the fields for this Matrix - can't use the fields service due to context
        $blockTypes = craft()->superTable->getBlockTypesByFieldId($field->id, 'id');

        // Ensure when importing only one block thats its treated correctly.
        $keys = array_keys($data);

        if (!is_numeric($keys[0])) {
            $data = array($data);
        }

        // If we've got Matrix data like 0.0.blockHandle.fieldHandle - then we've got a problem.
        // Commonly, this will be due to a field being referenced outside of inner-repeatable Matrix data.
        $hasOrphanedData = false;

        foreach (Hash::flatten($data) as $key => $value) {
            if (preg_match('/^\d+\.\d+/', $key)) {
                $hasOrphanedData = true;
                break;
            }
        }

        if ($hasOrphanedData) {
            $seperateBlockData = array();

            foreach ($data as $sortKey => $sortData) {
                foreach ($sortData as $blockHandle => $blockFieldData) {
                    if (!is_numeric($blockHandle)) {
                        $seperateBlockData[$blockHandle] = $blockFieldData;

                        unset($data[$sortKey][$blockHandle]);
                    }
                }
            }

            // Now, append this content to each block that we're importing, so it gets sorted out properly
            if ($seperateBlockData) {
                foreach ($data as $sortKey => $sortData) {
                    foreach ($sortData as $blockHandle => $blockFieldData) {
                        $data[$sortKey][$blockHandle] = array_merge_recursive($blockFieldData, $seperateBlockData);
                    }
                }

                $data = $data[0];
            }
        }

        if (!isset($data[0])) {
            $data = array($data);
        }

        foreach ($data as $sortKey => $sortData) {
            $blockData = array();

            foreach ($sortData as $blockHandle => $blockFieldData) {
                $preppedFieldData = array();

                foreach ($blockFieldData as $blockFieldHandle => $blockFieldContent) {

                    if (!isset($blockTypes[$blockHandle])) {
                        continue;
                    }

                    // Get the Matrix-contexted field for our regular field-prepping function
                    $blockType = $blockTypes[$blockHandle];

                    foreach ($blockType->getFields() as $f) {
                        if ($f->handle == $blockFieldHandle) {
                            $subField = $f;
                        }
                    }

                    // Check to see if this is information for a block
                    if ($blockFieldHandle == 'block') {
                        foreach ($blockFieldContent as $blockFieldOption => $blockFieldOptionValue) {
                            $blockData[$blockHandle][$blockFieldOption] = Hash::get($blockFieldOptionValue, 'data');
                        }
                    }

                    if (!isset($subField)) {
                        continue;
                    }

                    $fieldOptions = array(
                        'field' => $subField,
                    );

                    // Special-case for table!
                    if ($subField->type == 'Table') {
                        $blockFieldContent = array('data' => $blockFieldContent);
                    }

                    // Parse this inner-field's data, just like a regular field
                    $parsedData = craft()->feedMe_fields->prepForFieldType(null, $blockFieldContent, $blockFieldHandle, $fieldOptions);

                    // Fire any post-processing for the field type
                    $posted = craft()->feedMe_fields->postForFieldType(null, $parsedData, $blockFieldHandle, $subField);

                    if ($posted) {
                        $parsedData = $parsedData[$blockFieldHandle];
                    }

                    if ($parsedData) {
                        $preppedFieldData[$blockFieldHandle] = $parsedData;
                    }
                }

                if ($preppedFieldData) {
                    $order = $sortKey + 1;
                    $enabled = true;

                    $preppedData['new' . $order] = array(
                        'type' => $blockHandle,
                        'order' => $order,
                        'enabled' => $enabled,
                        'fields' => $preppedFieldData,
                    );
                }
            }
        }

        return $preppedData;
    }
    
}

