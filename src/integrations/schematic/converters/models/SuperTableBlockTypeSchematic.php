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
        $definition['handle'] = $record->handle;

        unset($definition['attributes']['fieldId']);
        unset($definition['attributes']['hasFieldErrors']);

        $definition['fields'] = Craft::$app->controller->module->modelMapper->export($record->fieldLayout->getFields());

        return $definition;
    }

    public function saveRecord(Model $record, array $definition): bool
    {
        // Save the super table block
        return SuperTable::$plugin->getService()->saveBlockType($record, false);
    }

    public function setRecordAttributes(Model &$record, array $definition, array $defaultAttributes)
    {
        // Set the content table for this super table block
        $originalContentTable = Craft::$app->content->contentTable;
        if ($record->fieldId) {
            $superTableField = Craft::$app->fields->getFieldById($record->fieldId);
            $contentTable = SuperTable::$plugin->getService()->getContentTableName($superTableField);
            Craft::$app->content->contentTable = $contentTable;
        }

        parent::setRecordAttributes($record, $definition, $defaultAttributes);
        if (array_key_exists('fields', $definition)) {
            // Get the super table block fields from the definition
            $modelMapper = Craft::$app->controller->module->modelMapper;
            $fields = $modelMapper->import($definition['fields'], $record->getFields(), [], false);
            $record->setFields($fields);
        }

        // Restore the content table to what it was before
        Craft::$app->content->contentTable = $originalContentTable;
    }

    public function deleteRecord(Model $record): bool
    {
        return SuperTable::$plugin->getService()->deleteBlockType($record);
    }
}
