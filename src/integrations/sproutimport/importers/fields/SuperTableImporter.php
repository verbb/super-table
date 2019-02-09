<?php
namespace verbb\supertable\integrations\sproutimport\importers\fields;

use Craft;
use verbb\supertable\SuperTable;
use barrelstrength\sproutbase\app\import\base\FieldImporter;
use barrelstrength\sproutimport\SproutImport;
use verbb\supertable\fields\SuperTableField;

class SuperTableImporter extends FieldImporter
{
    /**
     * @return string
     */
    public function getModelName(): string
    {
        return SuperTableField::class;
    }

    /**
     * @return mixed
     */
    public function getMockData()
    {
        $fieldId = $this->model->id;
        $blocks = SuperTable::$plugin->getService()->getBlockTypesByFieldId($fieldId);

        $values = [];

        if (!empty($blocks)) {
            $count = 1;

            foreach ($blocks as $block) {
                $key = 'new'.$count;

                $values[$key] = [
                    'type' => $block->id,
                    'enabled' => 1
                ];

                $fieldLayoutId = $block->fieldLayoutId;

                $fieldLayouts = Craft::$app->getFields()->getFieldsByLayoutId($fieldLayoutId);

                $values[$key]['fields'] = SproutImport::$app->fieldImporter->getFieldsWithMockData($fieldLayouts);

                $count++;
            }
        }

        return $values;
    }
}
