# PHP Example

The below is an example in PHP to programatically save a Super Table field with content.

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