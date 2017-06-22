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
        $blockType = reset($blockTypes);

        $prePreppedData = array();

        foreach (Hash::flatten($data) as $key => $value) {
            if (preg_match('/^\d+\.\d+/', $key)) {
                $prePreppedData[$key] = $value;
            } else {
                $prePreppedData['0.' . $key] = $value;
            }
        }

        $data = Hash::expand($prePreppedData);

        foreach ($data as $sortKey => $sortData) {
            $preppedFieldData = array();

            foreach ($sortData as $blockHandle => $blockFieldData) {
                foreach ($blockFieldData as $blockFieldHandle => $blockFieldContent) {

                    foreach ($blockType->getFields() as $f) {
                        if ($f->handle == $blockFieldHandle) {
                            $subField = $f;
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
            }

            $order = $sortKey + 1;

            $preppedData['new' . $order] = array(
                'type' => $blockHandle,
                'order' => $order,
                'enabled' => true,
                'fields' => $preppedFieldData,
            );
        }

        return $preppedData;
    }

    // Allows us to smartly-check to look at existing Matrix fields for an element, and whether data has changed or not.
    // No need to update Matrix blocks unless content has changed, which causes needless new elements to be created.
    public function postFieldData($element, $field, &$data, $handle)
    {
        
    }
    
}

