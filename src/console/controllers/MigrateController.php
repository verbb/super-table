<?php
namespace verbb\supertable\console\controllers;

use verbb\supertable\migrations\FixContentTableIndexes;

use craft\console\Controller;
use craft\helpers\Console;

use Throwable;

use yii\console\ExitCode;

/**
 * Manages Super Table utilities.
 */
class MigrateController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Fix the indexes for Super Table content.
     */
    public function actionFixContentTableIndexes(): int
    {
        $migration = new FixContentTableIndexes();
        $migration->up();

        return ExitCode::OK;
    }
}
