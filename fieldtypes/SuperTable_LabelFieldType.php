<?php
namespace Craft;

class SuperTable_LabelFieldType extends BaseFieldType
{
    public function getName()
    {
        return Craft::t('Label');
    }

    public function defineContentAttribute()
    {
        return AttributeType::String;
    }

    public function getInputHtml($name, $value)
    {
        $value = $this->settings->value;

        return craft()->templates->render('supertable/label/input', array(
            'id'    => craft()->templates->formatInputId($name),
            'name'  => $name,
            'value' => $value,
        ));
    }

    public function getSettingsHtml()
    {
        return craft()->templates->render('supertable/label/settings', array(
            'settings' => $this->getSettings()
        ));
    }


    // Protected Methods
    // =========================================================================

    protected function defineSettings()
    {
        return array(
            'value' => array(AttributeType::String),
        );
    }

}
