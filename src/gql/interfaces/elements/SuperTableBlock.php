<?php
namespace verbb\supertable\gql\interfaces\elements;

use verbb\supertable\gql\types\generators\SuperTableBlockType;

use Craft;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;

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
            'resolveType' => self::class . '::resolveElementTypeName',
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
    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'fieldId' => [
                'name' => 'fieldId',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The ID of the field that owns the Super Table block.',
            ],
            'primaryOwnerId' => [
                'name' => 'primaryOwnerId',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The ID of the primary owner of the Super Table block.',
            ],
            'typeId' => [
                'name' => 'typeId',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The ID of the Super Table block‘s type.',
            ],
            'sortOrder' => [
                'name' => 'sortOrder',
                'type' => Type::int(),
                'description' => 'The sort order of the Super Table block within the owner element field.',
            ],
        ]), self::getName());
    }
}
