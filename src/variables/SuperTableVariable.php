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

    public function getSuperTableBlocks($fieldId)
    {
        return SuperTable::$plugin->getService()->getBlockTypesByFieldId($fieldId);
    }
}
