<?php
namespace verbb\supertable\gql\types\generators;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\gql\interfaces\elements\SuperTableBlock as SuperTableBlockInterface;
use verbb\supertable\gql\types\elements\SuperTableBlock;

use Craft;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;

class SuperTableBlockType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        // If we need superTable block types for a specific SuperTable field, fetch those.
        if ($context) {
            /** @var SuperTableField $context */
            $superTableBlockTypes = $context->getBlockTypes();
        } else {
            $superTableBlockTypes = SuperTable::$plugin->getService()->getAllBlockTypes();
        }

        $gqlTypes = [];

        foreach ($superTableBlockTypes as $superTableBlockType) {
            $type = static::generateType($superTableBlockType);
            $gqlTypes[$type->name] = $type;
        }

        return $gqlTypes;
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): mixed
    {
        /** @var SuperTableBlockType $superTableBlockType */
        $typeName = SuperTableBlockElement::gqlTypeNameByContext($context);

        if (!($entity = GqlEntityRegistry::getEntity($typeName))) {
            $contentFieldGqlTypes = self::getContentFields($context);
            $blockTypeFields = array_merge(SuperTableBlockInterface::getFieldDefinitions(), $contentFieldGqlTypes);

            // Generate a type for each block type
            $entity = GqlEntityRegistry::getEntity($typeName);

            if (!$entity) {
                $entity = new SuperTableBlock([
                    'name' => $typeName,
                    'fields' => function() use ($blockTypeFields, $typeName) {
                        return Craft::$app->getGql()->prepareFieldDefinitions($blockTypeFields, $typeName);
                    },
                ]);

                // It's possible that creating the super table block triggered creating all super table block types, so check again.
                $entity = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, $entity);
            }
        }

        return $entity;
    }
}
