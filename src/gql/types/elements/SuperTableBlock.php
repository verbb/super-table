<?php
namespace verbb\supertable\gql\types\elements;

use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\gql\interfaces\elements\SuperTableBlock as SuperTableBlockInterface;

use craft\gql\types\elements\Element;

use GraphQL\Type\Definition\ResolveInfo;

class SuperTableBlock extends Element
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            SuperTableBlockInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var SuperTableBlockElement $source */
        $fieldName = $resolveInfo->fieldName;

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }

}
