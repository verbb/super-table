<?php
namespace verbb\supertable\elements;

use Craft;
use craft\elements\Entry;

class SuperTableBlockElement extends Entry
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('super-table', 'SuperTable Block');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('super-table', 'SuperTable block');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('super-table', 'SuperTable Blocks');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('super-table', 'SuperTable blocks');
    }

    public static function refHandle(): ?string
    {
        return 'supertableblock';
    }
}
