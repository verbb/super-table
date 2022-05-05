# PHP Example

The below is an example in PHP to programmatically save a Super Table field with content.

```php
use Craft;
use craft\elements\Entry;
use verbb\supertable\SuperTable;

// Set up your new Entry
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

## Updating existing field content
Using the above example as a basis, you can not only add new blocks, but retain existing blocks as well. Here, we make use of Craft's delta updates feature to save partial Super Table data.

```php
use Craft;
use craft\elements\Entry;
use verbb\supertable\SuperTable;

// Get our Super Table field
$field = Craft::$app->getFields()->getFieldByHandle('superTableField');
$blockTypes = SuperTable::$plugin->getService()->getBlockTypesByFieldId($field->id);
$blockType = $blockTypes[0]; // There will only ever be one SuperTable_BlockType

// Get the entry we want to modify
$entry = Entry::find()->id(1234)->one();

// Get the `sortOrder` of all existing blocks. This helps with delta updates.
$sortOrder = (clone $entry->superTableField)->anyStatus()->ids();
$sortOrder[] = 'new:1';

// Create your new block info
$newBlock = [
    'type' => $blockType->id,
    'enabled' => true,
    'fields' => [
        'fieldOne' => 'This is new block text',
    ],
];

// Set the field value for your Super Table field, including the new block, and existing `sortOrder`
$entry->setFieldValue('superTableField', [
    'sortOrder' => $sortOrder,
    'blocks' => [
        'new:1' => $newBlock,
    ],
]);

// When you're done, save the entry
Craft::$app->elements->saveElement($entry);
```
