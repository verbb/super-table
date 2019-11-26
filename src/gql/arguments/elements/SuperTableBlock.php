<?php
namespace verbb\supertable\gql\arguments\elements;

use craft\gql\base\ElementArguments;

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
                'type' => Type::listOf(Type::int()),
                'description' => 'Narrows the query results based on the field the Super Table blocks belong to, per the fields’ IDs.'
            ],
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::listOf(Type::string()),
                'description' => ' Narrows the query results based on the owner element of the Super Table blocks, per the owners’ IDs.'
            ],
            'typeId' => Type::listOf(Type::int()),
        ]);
    }
}
