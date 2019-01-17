<?php
namespace verbb\supertable\variables;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\elements\SuperTableBlockElement;

use Craft;
use craft\elements\Entry;

class SuperTableVariable
{
    public function blocks($criteria = null): SuperTableBlockQuery
    {
        $query = SuperTableBlockElement::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
    * Expands the defualt relationship behaviour to include Super Table
    * fields so that the user can filter by those too.
    *
    * For example:
    *
    * ```twig
    * {% set reverseRelatedElements = craft.superTable.getRelatedElements({
    *   relatedTo: {
    *       targetElement: entry,
    *       field: 'superTableFieldHandle.columnHandle',
    *   },
    *   ownerSite: 'siteHandle',
    *   elementType: 'craft\\elements\\Entry',
    *   criteria: {
    *       id: 'not 123',
    *       section: 'someSection',
    *   }
    * })->all() %}
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
        $superTableField = Craft::$app->fields->getFieldByHandle($superTableFieldHandle);
        $superTableBlockTypes = SuperTable::$plugin->getService()->getBlockTypesByFieldId($superTableField->id);
        $superTableBlockType = $superTableBlockTypes[0];
       
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

        // Create an element query to find Super Table Blocks
        $blockQuery = SuperTableBlockElement::find();

        $blockCriteria = [
            'relatedTo' => $params['relatedTo']
        ];

        // Check for ownerSite param add to blockCriteria
        if (isset($params['ownerSite'])) {
            $blockCriteria['ownerSite'] = $params['ownerSite'];
        }

        Craft::configure($blockQuery, $blockCriteria);

        // Get the Super Table Blocks that are related to that field and criteria
        $elementIds = $blockQuery->select('ownerId')->column();

        // Default to getting Entry elements but let the user override
        $elementType = $params['elementType'] ?? Entry::class;

        // Start our final criteria with the element ids we just got
        $finalCriteria = [
            'id' => $elementIds,
        ];
        
        // Check if the user gave us another criteria model and merge that in
        if (isset($params['criteria'])) {
            $finalCriteria = array_merge($finalCriteria, $params['criteria']);
        }

        // Create an element query based on our final criteria, and return
        $elementQuery = $elementType::find();
        Craft::configure($elementQuery, $finalCriteria);

        return $elementQuery;
    }


    //
    // Having a Matrix-SuperTable-Matrix layout will cause issues becase it will try to apply the namespace for the top-level
    // Matrix field, which means inner-Matrix fields will not work properly. Very hacky, but we need to replicate the Matrix
    // getInputHtml() function with alternative namespaces.
    //

    public function getMatrixSettingsHtml($matrixFieldType)
    {
        return SuperTable::$plugin->matrixService->getMatrixSettingsHtml($matrixFieldType);
    }

    public function getMatrixInputHtml($matrixField, $value, $element)
    {
        return SuperTable::$plugin->matrixService->getMatrixInputHtml($matrixField, $value, $element);
    }

    public function getSuperTableBlocks($fieldId)
    {
        return SuperTable::$plugin->getService()->getBlockTypesByFieldId($fieldId);
    }

    

}
