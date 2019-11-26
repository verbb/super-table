<?php
namespace verbb\supertable\gql\resolvers\elements;

use verbb\supertable\elements\SuperTableBlockElement;

use craft\gql\base\ElementResolver;

class SuperTableBlock extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery($source, array $arguments, $fieldName = null)
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = SuperTableBlockElement::find();
            // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it's preloaded, it's preloaded.
        if (is_array($query)) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        return $query;
    }
}
