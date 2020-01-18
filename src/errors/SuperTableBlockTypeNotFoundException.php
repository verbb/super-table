<?php
namespace verbb\supertable\errors;

use yii\base\Exception;

class SuperTableBlockTypeNotFoundException extends Exception
{
    public function getName()
    {
        return 'Super Table block type not found';
    }
}
