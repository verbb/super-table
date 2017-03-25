<?php
namespace Craft;

class SuperTable_SettingsModel extends BaseModel
{
    // Properties
    // =========================================================================

    private $_superTableField;
    private $_blockTypes;

    // Public Methods
    // =========================================================================

    public function __construct(FieldModel $superTableField = null)
    {
        $this->_superTableField = $superTableField;
    }

    public function getField()
    {
        return $this->_superTableField;
    }

    public function getBlockTypes()
    {
        if (!isset($this->_blockTypes)) {
            if (!empty($this->_superTableField->id)) {
                $this->_blockTypes = craft()->superTable->getBlockTypesByFieldId($this->_superTableField->id);
            } else {
                $this->_blockTypes = array();
            }
        }

        return $this->_blockTypes;
    }

    public function setBlockTypes($blockTypes)
    {
        $this->_blockTypes = $blockTypes;
    }

    public function validate($attributes = null, $clearErrors = true)
    {
        // Enforce $clearErrors without copying code if we don't have to
        $validates = parent::validate($attributes, $clearErrors);

        if (!craft()->superTable->validateFieldSettings($this)) {
            $validates = false;
        }

        return $validates;
    }

    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array(
            'columns' => AttributeType::Mixed,
            'fieldLayout' => AttributeType::String,
            'staticField' => AttributeType::Bool,
            'selectionLabel' => AttributeType::String,
            'maxRows' => AttributeType::Number,
            'minRows' => AttributeType::Number,
        );
    }
}
