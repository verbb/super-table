<?php
namespace verbb\supertable\errors;

use yii\base\Exception;

class SuperTableBlockTypeNotFoundException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Super Table block type not found';
    }
}
