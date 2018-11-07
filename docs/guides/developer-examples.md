# Developer Examples

## Updating a Super Table field from a front end form

Updating a Super Table field from a front-end form is straightforward - its very similar to a Matrix.

Refer to the following code - we have a Super Table field with handle `mySuperTableField` in our User profile, which we want to modify. This could apply to any endpoint in Craft, be it Entries, Global Set, etc.

First, we need to output any existing rows for this field, otherwise they will be overwritten. These can be hidden inputs if you don't want them to appear.

The variables below will help you figure out the correct block type to use (even though a Super Table field will only ever have one block type).

```twig
{% set fieldHandle = 'mySuperTableField' %}
{% set field = craft.fields.getFieldByHandle(fieldHandle) %}
{% set blocktype = craft.superTable.getSuperTableBlocks(field.id)[0] %}

<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    <input type="hidden" name="action" value="users/save-user">
    <input type="hidden" name="userId" value="{{ currentUser.id }}">

    {# Start with the top-level Super Table field #}
    <input type="hidden" name="fields[{{ fieldHandle }}]" value="">

    {# Ensure any existing rows in your Super Table field are saved #}
    {% for block in currentUser[fieldHandle] %}
        <input type="hidden" name="fields[{{ fieldHandle }}][{{ block.id }}][type]" value="{{ blocktype }}">
        <input type="hidden" name="fields[{{ fieldHandle }}][{{ block.id }}][enabled]" value="1">

        <input type="text" name="fields[{{ fieldHandle }}][{{ block.id }}][fields][firstName]" value="{{ block.plainText1 }}">
    {% endfor %}

    {# Add a new row of data - note the `new1` #}
    <input type="hidden" name="fields[{{ fieldHandle }}][new1][type]" value="{{ blocktype }}">
    <input type="hidden" name="fields[{{ fieldHandle }}][new1][enabled]" value="1">

    {# Repeat for all your fields in your Super Table field #}
    <input type="text" name="fields[{{ fieldHandle }}][new1][fields][firstName]" value="John Smith">

    {# Add another new row of data #}
    <input type="hidden" name="fields[{{ fieldHandle }}][new2][type]" value="{{ blocktype }}">
    <input type="hidden" name="fields[{{ fieldHandle }}][new2][enabled]" value="1">
    
    {# Repeat for all your fields in your Super Table field #}
    <input type="text" name="fields[{{ fieldHandle }}][new2][fields][firstName]" value="Jane Smith">

    <input type="submit">
</form>
```

## Programatically saving a Super Table field with content

```php
use Craft;
use craft\elements\Entry;
use verbb\supertable\SuperTable;

// Setup your new Entry
$entry = new Entry();
$entry->getContent()->title = 'My Title';
$entry->sectionId = 1;
$entry->authorId = 1;

// Create your Super Table blocks. Make sure to change the `fields` array to reflect
// the handles of your fields in your Super Table field.
$superTableData = [];

// Get our Super Table field
$field = Craft::$app->getFields()->getFieldByHandle('socialMedia');
$blockTypes = SuperTable::$plugin->getService()->getBlockTypesByFieldId($field->id);
$blockType = $blockTypes[0]; // There will only ever be one SuperTable_BlockType

$superTableData['new1'] = [
    'type' => $blockType->id,
    'enabled' => true,
    'fields' => [
        'fieldOne' => 'Some string or data',
        'fieldTwo' => '12'
    ]
];

// You can repeat this part for each block you want to add, just increment the newN number.
$superTableData['new2'] = [
    'type' => $blockType->id,
    'enabled' => true,
    'fields' => [
        'fieldOne' => 'Some more text',
        'fieldTwo' => '16'
    ]
];

// Set the blocks for the Super Table field - ensure you change the handle to reflect your Super Table field handle
$entry->setFieldValues(['superTableField' => $superTableData]);

// When you're done, save the entry
Craft::$app->getElements()->saveElement($entry);
```

## Fetching content from a Super Table field

The below is by no means a full solution, but will certainly assist in querying content from a Super Table field.

```php
use verbb\supertable\elements\SuperTableBlockElement;

$criteria = SuperTableBlockElement::find()
    ->ownerId('149081'); // Element ID - commonly an entry
    ->fieldId('563'); // Super Table field ID
    
$blocks = $criteria->all();

foreach ($blocks as $block) {
    $values = [];

    foreach ($block->getFieldLayout()->getFields() as $field) {
        $value = $block->getFieldValue($field->handle);
        $values[$field->handle] = $value;
    }
    
    // This will be an array indexed by each row's field handle
    var_dump($values);
}
```

Or the equivalent in Twig

```twig
{% set blocks = craft.superTable.blocks({ ownerId: '149081', fieldId: '563' }).all() %}

{% for block in blocks %}
    {% for field in block.getFieldLayout().getFields() %}
        {{ field.handle }}
    {% endfor %}
{% endfor %}
```
