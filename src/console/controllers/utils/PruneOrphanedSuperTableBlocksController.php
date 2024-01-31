<?php

namespace verbb\supertable\console\controllers\utils;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Console;
use verbb\supertable\elements\SuperTableBlockElement;
use yii\console\ExitCode;

class PruneOrphanedSuperTableBlocksController extends Controller
{
    /**
     * Prunes orphaned super table blocks for each site.
     *
     * @return int
     */
    public function actionIndex(): int
    {
        if (!Craft::$app->getIsMultiSite()) {
            $this->stdout("This command should only be run for multi-site installs.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $elementsService = Craft::$app->getElements();

        // get all sites
        $sites = Craft::$app->getSites()->getAllSites();

        // for each site get all super table blocks with owner that doesn't exist for this site
        foreach ($sites as $site) {
            $this->stdout(sprintf('Finding orphaned super table blocks for site "%s" ... ', $site->getName()));

            $esSubQuery = (new Query())
                ->from(['es' => Table::ELEMENTS_SITES])
                ->where([
                    'and',
                    '[[es.elementId]] = [[supertableblocks.primaryOwnerId]]',
                    ['es.siteId' => $site->id],
                ]);

            $supertableBlocks = SuperTableBlockElement::find()
                ->status(null)
                ->siteId($site->id)
                ->where(['not exists', $esSubQuery])
                ->all();

            if (empty($supertableBlocks)) {
                $this->stdout("none found\n", Console::FG_GREEN);
                continue;
            }

            $this->stdout(sprintf("%s found\n", count($supertableBlocks)), Console::FG_RED);

            // delete the ones we found
            foreach ($supertableBlocks as $block) {
                $this->do(sprintf('Deleting block %s in %s', $block->id, $site->getName()), function() use ($block, $elementsService) {
                    $elementsService->deleteElementForSite($block);
                });
            }
        }

        $this->stdout("\nFinished pruning orphaned Super Table blocks.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}