<?php
namespace Craft;

class SuperTable_BlockModel extends BaseElementModel
{    
    // Properties
    // =========================================================================

    protected $elementType = 'SuperTable_Block';
    private $_owner;
    private $_eagerLoadedBlockTypeElements;

    // Public Methods
    // =========================================================================

    public function getFieldLayout()
    {
        $blockType = $this->getType();

        if ($blockType) {
            return $blockType->getFieldLayout();
        }
    }

    public function getLocales()
    {
        // If the SuperTable field is translatable, than each individual block is tied to a single locale, and thus aren't
        // translatable. Otherwise all blocks belong to all locales, and their content is translatable.

        if ($this->ownerLocale) {
            return array($this->ownerLocale);
        } else {
            $owner = $this->getOwner();

            if ($owner) {
                // Just send back an array of locale IDs -- don't pass along enabledByDefault configs
                $localeIds = array();

                foreach ($owner->getLocales() as $localeId => $localeInfo) {
                    if (is_numeric($localeId) && is_string($localeInfo)) {
                        $localeIds[] = $localeInfo;
                    } else {
                        $localeIds[] = $localeId;
                    }
                }

                return $localeIds;
            } else {
                return array(craft()->i18n->getPrimarySiteLocaleId());
            }
        }
    }

    public function getType()
    {
        if ($this->typeId) {
            return craft()->superTable->getBlockTypeById($this->typeId);
        }
    }

    public function getOwner()
    {
        if (!isset($this->_owner) && $this->ownerId) {
            $this->_owner = craft()->elements->getElementById($this->ownerId, null, $this->locale);

            if (!$this->_owner) {
                $this->_owner = false;
            }
        }

        if ($this->_owner) {
            return $this->_owner;
        }
    }

    public function setOwner(BaseElementModel $owner)
    {
        $this->_owner = $owner;
    }

    public function getContentTable()
    {
        return craft()->superTable->getContentTableName($this->_getField());
    }

    public function getFieldColumnPrefix()
    {
        return 'field_';
    }

    public function getFieldContext()
    {
        return 'superTableBlockType:'.$this->typeId;
    }

    public function hasEagerLoadedElements($handle)
    {
        if (isset($this->_eagerLoadedBlockTypeElements[$handle])) {
            return true;
        }

        return parent::hasEagerLoadedElements($handle);
    }

    public function getEagerLoadedElements($handle)
    {
        if (isset($this->_eagerLoadedBlockTypeElements[$handle])) {
            return $this->_eagerLoadedBlockTypeElements[$handle];
        }

        return parent::getEagerLoadedElements($handle);
    }

    public function setEagerLoadedElements($handle, $elements)
    {
        $this->_eagerLoadedBlockTypeElements[$handle] = $elements;

        parent::setEagerLoadedElements($handle, $elements);
    }

    public function getHasFreshContent()
    {
        // Defer to the owner element
        $owner = $this->getOwner();

        return $owner ? $owner->getHasFreshContent() : false;
    }


    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), array(
            'fieldId'     => AttributeType::Number,
            'ownerId'     => AttributeType::Number,
            'ownerLocale' => AttributeType::Locale,
            'typeId'      => AttributeType::Number,
            'sortOrder'   => AttributeType::Number,
        ));
    }


    // Private Methods
    // =========================================================================

    private function _getField()
    {
        return craft()->fields->getFieldById($this->fieldId);
    }
}
