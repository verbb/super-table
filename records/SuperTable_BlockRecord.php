<?php
namespace Craft;

class SuperTable_BlockRecord extends BaseRecord
{
    // Public Methods
    // =========================================================================

    public function getTableName()
    {
        return 'supertableblocks';
    }

    public function defineRelations()
    {
        return array(
            'element'     => array(static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE),
            'owner'       => array(static::BELONGS_TO, 'ElementRecord', 'required' => true, 'onDelete' => static::CASCADE),
            'ownerLocale' => array(static::BELONGS_TO, 'LocaleRecord', 'ownerLocale', 'onDelete' => static::CASCADE, 'onUpdate' => static::CASCADE),
            'field'       => array(static::BELONGS_TO, 'FieldRecord', 'required' => true, 'onDelete' => static::CASCADE),
            'type'        => array(static::BELONGS_TO, 'SuperTable_BlockTypeRecord', 'onDelete' => static::CASCADE),
        );
    }

    public function defineIndexes()
    {
        return array(
            array('columns' => array('ownerId')),
            array('columns' => array('fieldId')),
            array('columns' => array('typeId')),
            array('columns' => array('sortOrder')),
        );
    }

    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array(
            'sortOrder' => AttributeType::SortOrder,
            'ownerLocale' => AttributeType::Locale,
        );
    }
}
