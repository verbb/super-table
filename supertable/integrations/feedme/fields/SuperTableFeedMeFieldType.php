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












/*class SuperTableFeedMeFieldType extends BaseFeedMeFieldType
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

        // Store the fields for this Super Table field - can't use the fields service due to context
        $blockTypeFields = array();
        $blockTypes = craft()->superTable->getBlockTypesByFieldId($field->id);
        $blockType = $blockTypes[0]; // Super Table only ever has one Block Type

        foreach ($blockType->getFields() as $f) {
            $blockTypeFields[$f->handle] = $f;
        }


        // Data will be provided as:
        // blocktypeid = [
        //   fieldHandle = [
        //     data
        //   ]
        // ]
        //
        // Don't forget, Super Table fields only have one blockTypeId, so grab that separately
        // and set $data to our actual data we want to process
        $dataKeys = array_keys($data);
        $blockTypeId = $dataKeys[0];

        $data = $data[$blockTypeId];

        $optionsArray = array();
        $fieldsArray = array();
        $flatten = Hash::flatten($data);

        foreach ($flatten as $keyedIndex => $value) {
            $tempArray = explode('.', $keyedIndex);

            // Save field options and nested fields for later - they're a special case
            if (strstr($keyedIndex, '.options.')) {
                FeedMeArrayHelper::arraySet($optionsArray, $tempArray, $value);
            } else if (strstr($keyedIndex, '.fields.')) {
                FeedMeArrayHelper::arraySet($fieldsArray, $tempArray, $value);
            } else {
                //preg_match_all('/data.(\d*)/', $keyedIndex, $blockKeys);
                //$blockKey = $blockKeys[1];

                // Check if we're importing a single row into Super Table
                //if (!$blockKey) {
                    //$tempArray[] = 0;
                    //$blockKey = 0;
                //}

                // What type of field are we importing? Some fields require specifics dat (Elements = array)
                $field = $blockTypeFields[$tempArray[0]];

                // Element fields need an array - but we actually check for data in the format:
                if ($field->type == 'Assets') {
                    // If we're missing a 'fieldHandle.data.0.0' pattern, fix it
                    if (!preg_match('/data.(\d*\.\d*)/', $keyedIndex)) {
                        array_splice($tempArray, 2, 0, 0);
                    }

                    // And another slightly special case - not sure why, but not going to upset things
                    // until we can fully unit-test all these cases - nightmare!
                    if (preg_match('/data.(\d*\.\d*\.\d*)/', $keyedIndex)) {
                        unset($tempArray[2]);
                        $tempArray = array_values($tempArray); // re-base keys
                    }
                } else {
                    // We're dealing with a regular field - but the trick here is to check if we're
                    // importing a single Super Table Row - we still need to treat it like an array
                    // ie - we get data delivered as 'fieldHandle.data' - missing a '0' at the end
                    if (!preg_match('/data.(\d*)/', $keyedIndex)) {
                        array_splice($tempArray, 2, 0, 0);
                    }
                }

                // Remove the index from inside [data], to the front
                array_splice($tempArray, 0, 0, $tempArray[2]);
                unset($tempArray[3]);
                $tempArray = array_values($tempArray); // re-base keys


                // Check if we're importing a single row into Super Table
                //if (!$blockKey) {
                    // But, important to check what sort of field we're importing into. For instance
                    // Element fields actually require an array of elements, so we don't want to mess that up



                echo "<pre>";
                print_r($tempArray);
                echo "</pre>";

                //}

                FeedMeArrayHelper::arraySet($sortedData, $tempArray, $value);
            }
        }

        // Now a special case for field options and nested fields. Because of the way field-mapping stores them,
        // we need to loop through and apply across all blocks of this type. This also makes field-processing easier
        foreach ($sortedData as $blockOrder => $blockData) {
            foreach ($blockData as $blockHandle => $innerData) {
                $additionalData = array();

                // Get field options or nested fields
                $optionData = Hash::get($optionsArray, $blockHandle);
                $fieldData = Hash::get($fieldsArray, $blockHandle);

                if ($optionData) {
                    $additionalData = Hash::merge($additionalData, $optionData);
                }

                if ($fieldData) {
                    //$additionalData = Hash::merge($additionalData, $fieldData);
                }

                $sortedData[$blockOrder][$blockHandle] = Hash::merge($innerData, $additionalData);
            }
        }

        echo "<pre>";
        print_r($flatten);
        echo "</pre>";

        echo "<pre>";
        print_r($sortedData);
        echo "</pre>";


        // Sort by the new ordering we've set
        ksort($sortedData);

        // Store the fields for this Matrix - can't use the fields service due to context
        $blockTypes = craft()->matrix->getBlockTypesByFieldId($field->id, 'handle');

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

    public function postFieldData($element, $field, &$data, $handle)
    {
        
    }
    
}*/