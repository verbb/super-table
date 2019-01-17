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

    public function setRecordAttributes(Model &$record, array $definition, array $defaultAttributes)
    {
        parent::setRecordAttributes($record, $definition, $defaultAttributes);
        if (array_key_exists('blockTypes', $definition)) {
            $this->resetSuperTableServiceBlockTypesCache();
            $this->resetSuperTableFieldBlockTypesCache($record);

            // Get the supertable block types from the definition
            $modelMapper = Craft::$app->controller->module->modelMapper;
            $blockTypes = $modelMapper->import($definition['blockTypes'], $record->getBlockTypes(), [], false);
            $record->setBlockTypes($blockTypes);
        }
    }

    private function resetSuperTableServiceBlockTypesCache()
    {
        $obj = SuperTable::$plugin->getService();
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
                $result[$record->handle] = $converter->getRecordDefinition($record);
            }
        }

        return $result;
    }
}
