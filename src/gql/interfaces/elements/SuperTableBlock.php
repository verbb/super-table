<?php
namespace verbb\supertable\gql\interfaces\elements;

use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\gql\types\generators\SuperTableBlockType;

use craft\gql\interfaces\Element;
use craft\gql\TypeLoader;
use craft\gql\GqlEntityRegistry;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class SuperTableBlock extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return SuperTableBlockType::class;
    }

    /**
     * @inheritdoc
     */
    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all Super Table blocks.',
            'resolveType' => function (SuperTableBlockElement $value) {
                return $value->getGqlTypeName();
            }
        ]));

        SuperTableBlockType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'SuperTableBlockInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array {
        return array_merge(parent::getFieldDefinitions(), [
            'fieldId' => [
                'name' => 'fieldId',
                'type' => Type::int(),
                'description' => 'The ID of the field that owns the Super Table block.'
            ],
            'ownerId' => [
                'name' => 'ownerId',
                'type' => Type::int(),
                'description' => 'The ID of the element that owns the Super Table block.'
            ],
            'typeId' => [
                'name' => 'typeId',
                'type' => Type::int(),
                'description' => 'The ID of the Super Table block\'s type.'
            ],
            'sortOrder' => [
                'name' => 'sortOrder',
                'type' => Type::int(),
                'description' => 'The sort order of the Super Table block within the owner element field.'
            ],
        ]);
    }
}
