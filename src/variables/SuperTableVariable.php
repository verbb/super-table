<?php
namespace verbb\supertable\variables;

use Craft;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\elements\SuperTableBlockElement;

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

    public function getRelatedElements($params = null)
    {
        return SuperTable::$plugin->getService()->getRelatedElementsQuery($params);
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
