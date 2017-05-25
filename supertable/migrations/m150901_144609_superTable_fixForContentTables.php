<?php
namespace Craft;

class m150901_144609_superTable_fixForContentTables extends BaseMigration
{
    public function safeUp()
    {
        // Get all Super Table fields - but only the ones inside Matrix fields
        $fields = craft()->fields->getAllFields();
        $superTableFields = array();

        foreach ($fields as $field) {
            if ($field->type == 'Matrix') {
                $blockTypes = craft()->matrix->getBlockTypesByFieldId($field->id);

                foreach ($blockTypes as $blockType) {
                    foreach ($blockType->getFields() as $blockTypeField) {
                        if ($blockTypeField->type == 'SuperTable') {
                            $superTableFields[] = array(
                                'field' => $blockTypeField,
                                'parentFieldId' => $blockType->id,
                            );
                        }
                    }
                }
            }
        }

        // Now, we need to create new tables which incorporate the Matrix field this Super Table is sitting inside.
        // This will mean the supertablecontent_fieldhandle tables will now include the Matrix field id.
        //
        // So, we need to duplicate each table structure and content into new tables. We aren't removing the old tables
        // so as not to be destructive, and potentially loose content somwehere along the way.
        foreach ($superTableFields as $options) {
            $field = $options['field'];
            $parentFieldId = $options['parentFieldId'];

            // The latest code will actually mean this points to the new table already!
            $newContentTable = craft()->superTable->getContentTableName($field);

            if (!craft()->db->tableExists($newContentTable)) {
                $oldContentTable = str_replace('_'.$parentFieldId, '', $newContentTable);

                if (!craft()->db->tableExists($oldContentTable)) {
                    continue;
                }

                // Grab all existing data from old table
                $tableData = craft()->db->createCommand()
                    ->select('*')
                    ->from($oldContentTable)
                    ->queryAll();

                // Get the table creation raw SQL
                $tableSchema = craft()->db->createCommand('SHOW CREATE TABLE craft_' . $oldContentTable)->queryRow();
                $newTableSql = $tableSchema['Create Table'];

                // Create the new table
                $newTableSql = str_replace($oldContentTable, $newContentTable, $newTableSql);
                craft()->db->createCommand($newTableSql)->execute();

                // Copy the existing data into newly created table
                if ($tableData) {
                    $columns = array_keys($tableData[0]);
                    $rows = array();

                    foreach ($tableData as $key => $row) {
                        $rows[] = array_values($row);
                    }

                    foreach ($columns as $key => $column) {

                        // Craft does these fields for us.
                        if ($column == 'dateCreated' || $column == 'dateUpdated' || $column == 'uid') {
                            unset($columns[$key]);
                        }
                    }

                    // In the new content goes!
                    craft()->db->createCommand()->insertAll($newContentTable, $columns, $rows);
                }
            }
        }

        return true;
    }
}
