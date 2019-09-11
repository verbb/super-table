<?php
namespace verbb\supertable\gql\types\elements;

use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\gql\interfaces\elements\SuperTableBlock as SuperTableBlockInterface;

use craft\gql\interfaces\Element as ElementInterface;
use craft\gql\base\ObjectType;

use GraphQL\Type\Definition\ResolveInfo;

class SuperTableBlock extends ObjectType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            SuperTableBlockInterface::getType(),
            ElementInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo)
    {
        /** @var SuperTableBlockElement $source */
        $fieldName = $resolveInfo->fieldName;

        return $source->$fieldName;
    }

}
