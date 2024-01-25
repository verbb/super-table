<?php
namespace verbb\supertable\variables;

use Craft;
use craft\elements\Entry;
use craft\elements\db\ElementQueryInterface;

class SuperTableVariable
{
    public function blocks(array $criteria = []): ElementQueryInterface
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.superTable.blocks()` method has been deprecated. Use `craft.entries()` instead.');

        $query = Entry::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    public function getRelatedElements(array $criteria = []): ?ElementQueryInterface
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.superTable.getRelatedElements()` method has been deprecated. Use `craft.entries().relatedTo()` instead.');

        return null;
    }

    public function getSuperTableBlocks(int $fieldId): array
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.superTable.getSuperTableBlocks()` method has been deprecated. Use `field.getEntryTypes()` instead.');

        $field = Craft::$app->getFields()->getFieldById($fieldId);

        return $field->getEntryTypes();
    }
}
