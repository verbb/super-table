<?php
namespace verbb\supertable\gql\resolvers\elements;

use verbb\supertable\elements\SuperTableBlockElement;

use craft\elements\db\ElementQuery;
use craft\gql\base\ElementResolver;

class SuperTableBlock extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery(mixed $source, array $arguments, $fieldName = null): mixed
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = SuperTableBlockElement::find();
            // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it's preloaded, it's preloaded.
        if (!$query instanceof ElementQuery) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        return $query;
    }
}
