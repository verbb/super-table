# Block Querying

The below is by no means a full solution, but will certainly assist in querying content from a Super Table field.

::: code
```twig Twig
{% set blocks = craft.superTable.blocks({ ownerId: '149081', fieldId: '563' }).all() %}

{% for block in blocks %}
    {% for field in block.getFieldLayout().getFields() %}
        {% set value = block.getFieldValue(field.handle) %}

        {{ dump(value) }}
    {% endfor %}
{% endfor %}
```

```php PHP
use verbb\supertable\elements\SuperTableBlockElement;

$query = SuperTableBlockElement::find()
    ->ownerId('149081'); // Element ID - commonly an entry
    ->fieldId('563'); // Super Table field ID
    
$blocks = $query->all();

foreach ($blocks as $block) {
    $values = [];

    foreach ($block->getFieldLayout()->getFields() as $field) {
        $value = $block->getFieldValue($field->handle);

        var_dump($value);
    }
}
```
:::
