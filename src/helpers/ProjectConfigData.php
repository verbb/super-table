<?php
namespace verbb\supertable\helpers;

use verbb\supertable\elements\SuperTableBlockElement;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Json;

class ProjectConfigData
{
    // Project config rebuild methods
    // =========================================================================

    public static function rebuildProjectConfig(): array
    {
        $data = [];

        $superTableBlockTypes = (new Query())
            ->select([
                'bt.fieldId',
                'bt.fieldLayoutId',
                'bt.uid',
                'f.uid AS field',
            ])
            ->from(['{{%supertableblocktypes}} bt'])
            ->innerJoin('{{%fields}} f', '[[bt.fieldId]] = [[f.id]]')
            ->all();

        $layoutIds = [];
        $blockTypeData = [];

        foreach ($superTableBlockTypes as $superTableBlockType) {
            $fieldId = $superTableBlockType['fieldId'];
            unset($superTableBlockType['fieldId']);

            $layoutIds[] = $superTableBlockType['fieldLayoutId'];
            $blockTypeData[$fieldId][$superTableBlockType['uid']] = $superTableBlockType;
        }

        $superTableFieldLayouts = self::_generateFieldLayoutArray($layoutIds);

        // Fetch the subfields
        $superTableSubfieldRows = (new Query())
            ->select([
                'fields.id',
                'fields.name',
                'fields.handle',
                'fields.instructions',
                'fields.searchable',
                'fields.translationMethod',
                'fields.translationKeyFormat',
                'fields.type',
                'fields.settings',
                'fields.context',
                'fields.uid',
                'fieldGroups.uid AS fieldGroup',
            ])
            ->from(['{{%fields}} fields'])
            ->leftJoin('{{%fieldgroups}} fieldGroups', '[[fields.groupId]] = [[fieldGroups.id]]')
            ->where(['like', 'fields.context', 'superTableBlockType:'])
            ->all();

        $superTableSubFields = [];
        $fieldService = Craft::$app->getFields();

        // Massage the data and index by UID
        foreach ($superTableSubfieldRows as $superTableSubfieldRow) {
            $superTableSubfieldRow['settings'] = Json::decodeIfJson($superTableSubfieldRow['settings']);
            $fieldInstance = $fieldService->getFieldById($superTableSubfieldRow['id']);
            $superTableSubfieldRow['contentColumnType'] = $fieldInstance->getContentColumnType();
            list (, $blockTypeUid) = explode(':', $superTableSubfieldRow['context']);
            
            if (empty($superTableSubFields[$blockTypeUid])) {
                $superTableSubFields[$blockTypeUid] = [];
            }

            $fieldUid = $superTableSubfieldRow['uid'];
            unset($superTableSubfieldRow['uid'], $superTableSubfieldRow['id'], $superTableSubfieldRow['context']);
            $superTableSubFields[$blockTypeUid][$fieldUid] = $superTableSubfieldRow;
        }

        foreach ($blockTypeData as &$blockTypes) {
            foreach ($blockTypes as &$blockType) {
                $blockTypeUid = $blockType['uid'];
                $layout = $superTableFieldLayouts[$blockType['fieldLayoutId']];
                unset($blockType['uid'], $blockType['fieldLayoutId']);
                $blockType['fieldLayouts'] = [$layout['uid'] => ['tabs' => $layout['tabs']]];
                $blockType['fields'] = $superTableSubFields[$blockTypeUid] ?? [];
                $data[$blockTypeUid] = $blockType;
            }
        }

        return $data;
    }

    private static function _generateFieldLayoutArray(array $layoutIds): array
    {
        // Get all the UIDs
        $fieldLayoutUids = (new Query())
            ->select(['id', 'uid'])
            ->from([Table::FIELDLAYOUTS])
            ->where(['id' => $layoutIds])
            ->pairs();
        
        $fieldLayouts = [];
        
        foreach ($fieldLayoutUids as $id => $uid) {
            $fieldLayouts[$id] = [
                'uid' => $uid,
                'tabs' => [],
            ];
        }

        // Get the tabs and fields
        $fieldRows = (new Query())
            ->select([
                'fields.handle',
                'fields.uid AS fieldUid',
                'layoutFields.fieldId',
                'layoutFields.required',
                'layoutFields.sortOrder AS fieldOrder',
                'tabs.id AS tabId',
                'tabs.name as tabName',
                'tabs.sortOrder AS tabOrder',
                'tabs.uid AS tabUid',
                'layouts.id AS layoutId',
            ])
            ->from(['{{%fieldlayoutfields}} AS layoutFields'])
            ->innerJoin('{{%fieldlayouttabs}} AS tabs', '[[layoutFields.tabId]] = [[tabs.id]]')
            ->innerJoin('{{%fieldlayouts}} AS layouts', '[[layoutFields.layoutId]] = [[layouts.id]]')
            ->innerJoin('{{%fields}} AS fields', '[[layoutFields.fieldId]] = [[fields.id]]')
            ->where(['layouts.id' => $layoutIds])
            ->orderBy(['tabs.sortOrder' => SORT_ASC, 'layoutFields.sortOrder' => SORT_ASC])
            ->all();

        foreach ($fieldRows as $fieldRow) {
            $layout = &$fieldLayouts[$fieldRow['layoutId']];
            
            if (empty($layout['tabs'][$fieldRow['tabUid']])) {
                $layout['tabs'][$fieldRow['tabUid']] =
                    [
                        'name' => $fieldRow['tabName'],
                        'sortOrder' => $fieldRow['tabOrder'],
                    ];
            }

            $tab = &$layout['tabs'][$fieldRow['tabUid']];

            $field['required'] = $fieldRow['required'];
            $field['sortOrder'] = $fieldRow['fieldOrder'];

            $tab['fields'][$fieldRow['fieldUid']] = $field;
        }

        // Get rid of UIDs
        foreach ($fieldLayouts as &$fieldLayout) {
            $fieldLayout['tabs'] = array_values($fieldLayout['tabs']);
        }

        return $fieldLayouts;
    }
}