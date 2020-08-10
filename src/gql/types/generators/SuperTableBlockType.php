<?php
namespace verbb\supertable\gql\types\generators;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTable as SuperTableField;
use verbb\supertable\gql\interfaces\elements\SuperTableBlock as SuperTableBlockInterface;
use verbb\supertable\gql\types\elements\SuperTableBlock;
use verbb\supertable\models\SuperTableBlockTypeModel;

use Craft;
use craft\base\Field;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeManager;

class SuperTableBlockType implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes($context = null): array
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
    public static function generateType($context): ObjectType
    {
        /** @var SuperTableBlockTypeModel $superTableBlockType */
        $typeName = SuperTableBlockElement::gqlTypeNameByContext($context);

        if (!($entity = GqlEntityRegistry::getEntity($typeName))) {
            $contentFields = $context->getFields();
            $contentFieldGqlTypes = [];

            /** @var Field $contentField */
            foreach ($contentFields as $contentField) {
                $contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
            }

            $blockTypeFields = TypeManager::prepareFieldDefinitions(array_merge(SuperTableBlockInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);

            // Generate a type for each entry type
            $entity = GqlEntityRegistry::getEntity($typeName);

            if (!$entity) {
                $entity = new SuperTableBlock([
                    'name' => $typeName,
                    'fields' => function() use ($blockTypeFields) {
                        return $blockTypeFields;
                    }
                ]);

                // It's possible that creating the matrix block triggered creating all matrix block types, so check again.
                $entity = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, $entity);
            }
        }

        return $entity;
    }
}
