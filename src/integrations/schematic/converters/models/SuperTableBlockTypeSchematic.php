<?php
namespace verbb\supertable\integrations\schematic\converters\models;

use verbb\supertable\SuperTable;

use Craft;
use craft\base\Model;

use NerdsAndCompany\Schematic\Converters\Models\Base;

class SuperTableBlockTypeSchematic extends Base
{
    public function getRecordDefinition(Model $record): array
    {
        $definition = parent::getRecordDefinition($record);

        unset($definition['attributes']['fieldId']);
        unset($definition['attributes']['hasFieldErrors']);

        $definition['fields'] = Craft::$app->controller->module->modelMapper->export($record->fieldLayout->getFields());

        return $definition;
    }

    public function saveRecord(Model $record, array $definition): bool
    {
        // Set the content table for this super table block
        $originalContentTable = Craft::$app->content->contentTable;
        $superTableField = Craft::$app->fields->getFieldById($record->fieldId);
        $contentTable = SuperTable::$plugin->service->getContentTableName($superTableField);
        Craft::$app->content->contentTable = $contentTable;

        // Get the super table block fields from the definition
        $modelMapper = Craft::$app->controller->module->modelMapper;
        $fields = $modelMapper->import($definition['fields'], $record->getFields(), [], false);
        $record->setFields($fields);

        // Save the super table block
        $result = SuperTable::$plugin->service->saveBlockType($record, false);

        // Restore the content table to what it was before
        Craft::$app->content->contentTable = $originalContentTable;

        return $result;
    }

    public function deleteRecord(Model $record): bool
    {
        return SuperTable::$plugin->service->deleteBlockType($record);
    }
}
