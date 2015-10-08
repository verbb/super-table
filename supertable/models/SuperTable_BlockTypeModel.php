<?php
namespace Craft;

class SuperTable_BlockTypeModel extends BaseModel
{
    // Properties
    // =========================================================================

    public $hasFieldErrors = false;
    private $_fields;

    // Public Methods
    // =========================================================================

    public function __toString()
    {
        return $this->id;
    }

    public function behaviors()
    {
        return array(
            'fieldLayout' => new FieldLayoutBehavior('SuperTable_Block'),
        );
    }

    public function isNew()
    {
        return (!$this->id || strncmp($this->id, 'new', 3) === 0);
    }

    public function getFields()
    {
        if (!isset($this->_fields)) {
            $this->_fields = array();
            
            // Preload all of the fields in this block type's context
            craft()->fields->getAllFields(null, 'superTableBlockType:'.$this->id);

            $fieldLayoutFields = $this->getFieldLayout()->getFields();

            foreach ($fieldLayoutFields as $fieldLayoutField) {
                $field = $fieldLayoutField->getField();
                $field->required = $fieldLayoutField->required;
                $this->_fields[] = $field;
            }
        }

        return $this->_fields;
    }

    public function setFields($fields)
    {
        $this->_fields = $fields;
    }

    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array(
            'id'            => AttributeType::Number,
            'fieldId'       => AttributeType::Number,
            'fieldLayoutId' => AttributeType::String,
        );
    }
}
