<?php
namespace Craft;

class SuperTable_BlockTypeRecord extends BaseRecord
{
    // Public Methods
    // =========================================================================

    public function getTableName()
    {
        return 'supertableblocktypes';
    }

    public function defineRelations()
    {
        return array(
            'field'       => array(static::BELONGS_TO, 'FieldRecord', 'required' => true, 'onDelete' => static::CASCADE),
            'fieldLayout' => array(static::BELONGS_TO, 'FieldLayoutRecord', 'onDelete' => static::SET_NULL),
        );
    }
}
