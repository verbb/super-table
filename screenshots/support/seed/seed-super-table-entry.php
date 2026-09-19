/** Seed genuine Craft 4 Super Table fields and a populated entry. */

use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;
use craft\fields\Lightswitch;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use verbb\supertable\fields\SuperTableField;

$fieldsService = Craft::$app->getFields();
$sections = Craft::$app->getSections();
$site = Craft::$app->getSites()->getPrimarySite();

$fieldGroupId = $fieldsService->getAllGroups()[0]->id ?? null;
$makeField = static function(string $name, string $handle, string $layout, bool $static, array $fields) use ($fieldsService, $fieldGroupId): SuperTableField {
    $field = $fieldsService->getFieldByHandle($handle);
    if ($field instanceof SuperTableField) return $field;

    $field = new SuperTableField([
        'name' => $name,
        'handle' => $handle,
        'groupId' => $fieldGroupId,
        'fieldLayout' => $layout,
        'staticField' => $static,
        'selectionLabel' => 'Add an item',
    ]);
    foreach ($fields as &$fieldConfig) {
        $fieldConfig['uid'] = $fieldConfig['uid'] ?? StringHelper::UUID();
    }
    unset($fieldConfig);
    $field->setBlockTypes([['fields' => $fields]]);
    foreach ($field->getBlockTypes()[0]->getCustomFields() as $nestedField) {
        $nestedField->uid = $nestedField->uid ?? StringHelper::UUID();
    }
    if (!$fieldsService->saveField($field)) throw new RuntimeException("Unable to save {$name}: " . Json::encode($field->getErrors()));
    return $field;
};

$plain = static fn(string $name, string $handle, int $width = 100) => [
    'type' => PlainText::class, 'name' => $name, 'handle' => $handle, 'width' => $width,
    'typesettings' => ['multiline' => false],
];
$dropdown = static fn(string $name, string $handle, array $options, int $width = 100) => [
    'type' => Dropdown::class, 'name' => $name, 'handle' => $handle, 'width' => $width,
    'typesettings' => ['options' => array_map(static fn($label) => ['label' => $label, 'value' => strtolower(str_replace(' ', '-', $label)), 'default' => false], $options)],
];
$makeMatrixField = static function() use ($fieldsService, $fieldGroupId, $plain): Matrix {
    $field = new Matrix(['name' => 'Page builder', 'handle' => 'docsSuperTableMatrix', 'groupId' => $fieldGroupId]);
    $field->setBlockTypes([[
        'name' => 'Content block',
        'handle' => 'contentBlock',
        'fields' => [[
            'type' => SuperTableField::class,
            'name' => 'Feature cards',
            'handle' => 'featureCards',
            'uid' => StringHelper::UUID(),
            'width' => 100,
            'typesettings' => [
                'fieldLayout' => 'row',
                'staticField' => false,
                'selectionLabel' => 'Add a card',
                'blockTypes' => [['fields' => [
                    array_merge($plain('Heading', 'cardHeading', 40), ['uid' => StringHelper::UUID()]),
                    array_merge($plain('Description', 'cardDescription', 60), ['uid' => StringHelper::UUID()]),
                ]]],
            ],
        ]],
    ]]);
    foreach ($field->getBlockTypes()[0]->getCustomFields() as $nestedField) {
        $nestedField->uid = $nestedField->uid ?? StringHelper::UUID();
        if ($nestedField instanceof SuperTableField) {
            foreach ($nestedField->getBlockTypes()[0]->getCustomFields() as $cardField) {
                $cardField->uid = $cardField->uid ?? StringHelper::UUID();
            }
        }
    }
    if (!$fieldsService->saveField($field)) throw new RuntimeException('Unable to save Matrix field: ' . Json::encode($field->getErrors()));
    /** @var Matrix $savedField */
    $savedField = $fieldsService->getFieldById($field->id);
    return $savedField;
};

$variant = '__VARIANT__';
$activeField = match ($variant) {
    'table' => $makeField('Image gallery', 'docsSuperTableTable', 'table', false, [
        $plain('Image', 'imageName', 42),
        $dropdown('Type', 'imageType', ['Large', 'Medium', 'Small'], 28),
        ['type' => Lightswitch::class, 'name' => 'Featured', 'handle' => 'featured', 'width' => 20, 'typesettings' => ['default' => false]],
    ]),
    'row' => $makeField('Content rows', 'docsSuperTableRow', 'row', false, [
        $plain('Heading', 'rowHeading', 35),
        $plain('Text', 'rowText', 45),
        $dropdown('Alignment', 'rowAlignment', ['Left', 'Centre', 'Right'], 20),
    ]),
    'static' => $makeField('Hero settings', 'docsSuperTableStatic', 'row', true, [
        $plain('Heading', 'heroHeading', 50),
        $dropdown('Text alignment', 'heroAlignment', ['Left', 'Centre', 'Right'], 25),
        $dropdown('Height', 'heroHeight', ['Small', 'Medium', 'Large'], 25),
    ]),
    'matrix' => $makeMatrixField(),
    default => throw new RuntimeException('Unknown Super Table screenshot variant.'),
};

$sectionHandle = 'docsScreenshotSuperTable';
$section = $sections->getSectionByHandle($sectionHandle);
if (!$section) {
    $entryType = new EntryType(['name' => 'Super Table showcase', 'handle' => $sectionHandle . 'Type', 'hasTitleField' => true]);
    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => Craft::t('app', 'Content'), 'layout' => $layout]);
    $tab->setElements([new CustomField($activeField)]);
    $layout->setTabs([$tab]);
    $entryType->setFieldLayout($layout);
    $section = new Section(['name' => 'Super Table showcase', 'handle' => $sectionHandle, 'type' => Section::TYPE_CHANNEL]);
    $section->setEntryTypes([$entryType]);
    $section->setSiteSettings([new Section_SiteSettings(['siteId' => $site->id, 'enabledByDefault' => true, 'hasUrls' => false])]);
    if (!$sections->saveSection($section)) throw new RuntimeException('Unable to save section: ' . Json::encode($section->getErrors()));
    $entryType = $sections->getEntryTypesBySectionId($section->id)[0];
    $entryType->setFieldLayout($layout);
    if (!$sections->saveEntryType($entryType)) throw new RuntimeException('Unable to save entry type layout: ' . Json::encode($entryType->getErrors()));
}

$type = $sections->getEntryTypesBySectionId($section->id)[0];
$entry = Entry::find()->sectionId($section->id)->siteId($site->id)->status(null)->one() ?? new Entry([
    'sectionId' => $section->id, 'typeId' => $type->id, 'siteId' => $site->id, 'slug' => 'super-table-showcase', 'enabled' => true,
]);
$entry->title = 'Super Table showcase';

$blockValue = static function(SuperTableField $field, array $rows): array {
    $type = $field->getBlockTypes()[0];
    $value = ['sortOrder' => [], 'blocks' => []];
    foreach ($rows as $index => $fields) {
        $key = 'new' . ($index + 1);
        $value['sortOrder'][] = $key;
        $value['blocks'][$key] = ['type' => $type->id, 'enabled' => true, 'fields' => $fields];
    }
    return $value;
};

$values = match ($variant) {
    'table' => [
        ['imageName' => 'Coastal sunrise.jpg', 'imageType' => 'large', 'featured' => true],
        ['imageName' => 'Mountain trail.jpg', 'imageType' => 'medium', 'featured' => false],
        ['imageName' => 'City at dusk.jpg', 'imageType' => 'small', 'featured' => false],
    ],
    'row' => [
        ['rowHeading' => 'Explore the coast', 'rowText' => 'A flexible row of related content.', 'rowAlignment' => 'left'],
        ['rowHeading' => 'Plan your stay', 'rowText' => 'Add as many structured rows as the page needs.', 'rowAlignment' => 'centre'],
    ],
    'static' => [['heroHeading' => 'Discover somewhere new', 'heroAlignment' => 'centre', 'heroHeight' => 'large']],
    'matrix' => [],
};
if ($activeField instanceof Matrix) {
    $matrixType = $activeField->getBlockTypes()[0];
    /** @var SuperTableField $nestedSuperTable */
    $nestedSuperTable = $matrixType->getCustomFields()[0];
    $entry->setFieldValue($activeField->handle, [
        'sortOrder' => ['new1'],
        'blocks' => ['new1' => [
            'type' => $matrixType->handle,
            'enabled' => true,
            'fields' => ['featureCards' => $blockValue($nestedSuperTable, [
                ['cardHeading' => 'Flexible content', 'cardDescription' => 'Keep fields together in repeatable blocks.'],
                ['cardHeading' => 'Clear structure', 'cardDescription' => 'Give editors the controls each component needs.'],
            ])],
        ]],
    ]);
} else {
    $entry->setFieldValue($activeField->handle, $blockValue($activeField, $values));
}

if (!Craft::$app->getElements()->saveElement($entry)) throw new RuntimeException('Unable to save entry: ' . Json::encode($entry->getErrors()));
echo Json::encode(['entryEditRoute' => parse_url((string)$entry->getCpEditUrl(), PHP_URL_PATH)], JSON_THROW_ON_ERROR);
