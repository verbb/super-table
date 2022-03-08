<?php
namespace verbb\supertable\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;

use GraphQL\Type\Definition\Type;

class SuperTableBlock extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'fieldId' => [
                'name' => 'fieldId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the field the Super Table blocks belong to, per the fields’ IDs.',
            ],
            'primaryOwnerId' => [
                'name' => 'primaryOwnerId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => ' Narrows the query results based on the primary owner element of the Super Table blocks, per the owners’ IDs.',
            ],
            'typeId' => Type::listOf(QueryArgument::getType()),
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getDraftArguments(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getRevisionArguments(): array
    {
        return [];
    }
}
