<?php
namespace verbb\supertable\integrations\schematic\converters\fields;

use verbb\supertable\SuperTable;

use Craft;
use craft\base\Model;

use NerdsAndCompany\Schematic\Schematic;
use NerdsAndCompany\Schematic\Converters\Base\Field;

class SuperTableSchematic extends Field
{
    public function getRecordDefinition(Model $record): array
    {
        $definition = parent::getRecordDefinition($record);

        $definition['blockTypes'] = $this->export($record->getBlockTypes());

        return $definition;
    }

    public function saveRecord(Model $record, array $definition): bool
    {
        if (parent::saveRecord($record, $definition)) {
            if (array_key_exists('blockTypes', $definition)) {
                $this->resetSuperTableServiceBlockTypesCache();
                $this->resetSuperTableFieldBlockTypesCache($record);

                Craft::$app->controller->module->modelMapper->import(
                    $definition['blockTypes'],
                    $record->getBlockTypes(),
                    ['fieldId' => $record->id]
                );
            }

            return true;
        }

        return false;
    }

    private function resetSuperTableServiceBlockTypesCache()
    {
        $obj = SuperTable::$plugin->service;
        $refObject = new \ReflectionObject($obj);

        if ($refObject->hasProperty('_fetchedAllBlockTypesForFieldId')) {
            $refProperty1 = $refObject->getProperty('_fetchedAllBlockTypesForFieldId');
            $refProperty1->setAccessible(true);
            $refProperty1->setValue($obj, false);
        }
    }

    private function resetSuperTableFieldBlockTypesCache(Model $record)
    {
        $obj = $record;
        $refObject = new \ReflectionObject($obj);

        if ($refObject->hasProperty('_blockTypes')) {
            $refProperty1 = $refObject->getProperty('_blockTypes');
            $refProperty1->setAccessible(true);
            $refProperty1->setValue($obj, null);
        }
    }

    private function export(array $records): array
    {
        $result = [];
        foreach ($records as $record) {
            $modelClass = get_class($record);
            $converter = Craft::$app->controller->module->getConverter($modelClass);
            if ($converter) {
                $result['new' . $record->id] = $converter->getRecordDefinition($record);
            }
        }

        return $result;
    }
}
