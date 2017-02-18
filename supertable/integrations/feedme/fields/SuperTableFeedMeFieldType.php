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
        $sortedData = array();

        $data = Hash::get($fieldData, 'data');

        if (empty($data)) {
            return;
        }

        // Because of how mapping works, we need to do some extra work here, which is a pain!
        // This is to ensure blocks are ordered as they are provided. Data will be provided as:
        // blockhandle = [
        //   fieldHandle = [
        //     orderIndex = [
        //       data
        //     ]
        //   ]
        // ]
        //
        // We change it to:
        //
        // orderIndex = [
        //   blockhandle = [
        //     orderIndex = [
        //       data
        //     ]
        //   ]
        // ]
        //
        $optionsArray = array();
        $flatten = Hash::flatten($data);

        foreach ($flatten as $keyedIndex => $value) {
            $tempArray = explode('.', $keyedIndex);

            // Save field options for later - they're a special case
            if (strstr($keyedIndex, '.options.')) {
                FeedMeArrayHelper::arraySet($optionsArray, $tempArray, $value);
            } else {
                preg_match_all('/data.(\d*)/', $keyedIndex, $blockKeys);
                $blockKey = $blockKeys[1];
    
                // Single Row
                if (!$blockKey) {
                    $tempArray[] = 0;
                    $blockKey = 0;
                }

                // Remove the index from inside [data], to the front
                array_splice($tempArray, 0, 0, $blockKey);

                // Check for nested data (elements, table)
                if (preg_match('/data.(\d*\.\d*)/', $keyedIndex)) {
                    unset($tempArray[count($tempArray) - 2]);
                } else {
                    array_pop($tempArray);
                }

                FeedMeArrayHelper::arraySet($sortedData, $tempArray, $value);
            }
        }

        // Now a special case for field options. Because of the way field-mapping stored them, we need to
        // loop through and apply across all blocks of this type. This also makes field-processing easier
        foreach ($sortedData as $blockOrder => $blockData) {
            foreach ($blockData as $blockHandle => $innerData) {
                $optionData = Hash::get($optionsArray, $blockHandle);

                if ($optionData) {
                    $sortedData[$blockOrder][$blockHandle] = Hash::merge($innerData, $optionData);
                }
            }
        }

        // Sort by the new ordering we've set
        ksort($sortedData);

        // Store the fields for this Matrix - can't use the fields service due to context
        $blockTypes = craft()->superTable->getBlockTypesByFieldId($field->id, 'id');

        $count = 0;
        $allPreppedFieldData = array();

        foreach ($sortedData as $sortKey => $sortData) {
            foreach ($sortData as $blockHandle => $blockFieldData) {
                foreach ($blockFieldData as $blockFieldHandle => $blockFieldContent) {

                    // Get the Matrix-contexted field for our regular field-prepping function
                    $blockType = $blockTypes[$blockHandle];

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

                    // Parse this inner-field's data, just like a regular field
                    $parsedData = craft()->feedMe_fields->prepForFieldType(null, $blockFieldContent, $blockFieldHandle, $fieldOptions);

                    if ($parsedData) {
                        // Special-case for inner table - not a great solution at the moment, needs to be more flexible
                        if ($subField->type == 'Table') {
                            foreach ($parsedData as $i => $tableFieldRow) {
                                $next = reset($tableFieldRow);

                                if (!is_array($next)) {
                                    $tableFieldRow = array($i => $tableFieldRow);
                                }

                                foreach ($tableFieldRow as $j => $tableFieldColumns) {
                                    foreach ($tableFieldColumns as $k => $tableFieldColumn) {
                                        $allPreppedFieldData[$k][$blockHandle][$blockFieldHandle][$j][$sortKey] = $tableFieldColumn;
                                    }
                                }
                            }
                        } else {
                            $allPreppedFieldData[$sortKey][$blockHandle][$blockFieldHandle] = $parsedData;
                        }
                    }
                }
            }
        }

        // Now we've got a bit more sane data - its a simple (regular) import
        if ($allPreppedFieldData) {
            foreach ($allPreppedFieldData as $key => $preppedBlockFieldData) {
                foreach ($preppedBlockFieldData as $blockHandle => $preppedFieldData) {
                    $preppedData['new'.($count+1)] = array(
                        'type' => $blockHandle,
                        'order' => ($count+1),
                        'enabled' => true,
                        'fields' => $preppedFieldData,
                    );

                    $count++;
                }
            }
        }

        return $preppedData;
    }

    // Allows us to smartly-check to look at existing Matrix fields for an element, and whether data has changed or not.
    // No need to update Matrix blocks unless content has changed, which causes needless new elements to be created.
    public function postFieldData($element, $field, &$data, $handle)
    {
        
    }
    
}

