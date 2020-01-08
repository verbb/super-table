<?php
namespace verbb\supertable\queue\jobs;

use verbb\supertable\SuperTable;
use verbb\supertable\elements\SuperTableBlockElement;
use verbb\supertable\fields\SuperTableField;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\events\BatchElementActionEvent;
use craft\queue\BaseJob;
use craft\services\Elements;

class ApplySuperTablePropagationMethod extends BaseJob
{
    // Properties
    // =========================================================================

    public $fieldId;
    public $oldPropagationMethod;
    public $newPropagationMethod;


    // Public Methods
    // =========================================================================

    public function execute($queue)
    {
        // Let's save ourselves some trouble and just clear all the caches for this element class
        Craft::$app->getTemplateCaches()->deleteCachesByElementType(SuperTableBlockElement::class);

        $query = SuperTableBlockElement::find()
            ->fieldId($this->fieldId)
            ->siteId('*')
            ->unique()
            ->anyStatus();

        $total = $query->count();
        $superTableService = SuperTable::$plugin->getService();
        $elementsService = Craft::$app->getElements();
        
        $callback = function(BatchElementActionEvent $e) use ($queue, $query, $total, $superTableService, $elementsService) {
            if ($e->query === $query) {
                $this->setProgress($queue, ($e->position - 1) / $total, Craft::t('app', '{step} of {total}', [
                    'step' => $e->position,
                    'total' => $total,
                ]));
                
                if ($this->oldPropagationMethod === SuperTableField::PROPAGATION_METHOD_NONE) {
                    // Blocks only lived in a single site to begin with, so there's nothing else to do here
                    return;
                }
                
                /** @var SuperTableBlockElement $block */
                $block = $e->element;
                $owner = $block->getOwner();
                
                $oldSiteIds = $superTableService->getSupportedSiteIds($this->oldPropagationMethod, $owner);
                $newSiteIds = $superTableService->getSupportedSiteIds($this->newPropagationMethod, $owner);
                $removedSiteIds = array_diff($oldSiteIds, $newSiteIds);
                
                if (!empty($removedSiteIds)) {
                    // Fetch the block in each of the sites that it will be removed in
                    $otherSiteBlocks = SuperTableBlockElement::find()
                        ->id($block->id)
                        ->fieldId($this->fieldId)
                        ->siteId($removedSiteIds)
                        ->anyStatus()
                        ->indexBy('siteId')
                        ->all();

                    // Duplicate those blocks so their content can live on
                    while (!empty($otherSiteBlocks)) {
                        $otherSiteBlock = array_pop($otherSiteBlocks);
                        
                        /** @var SuperTableBlockElement $newBlock */
                        $newBlock = $elementsService->duplicateElement($otherSiteBlock);
                        
                        // This may support more than just the site it was saved in
                        $newBlockSiteIds = $superTableService->getSupportedSiteIds($this->newPropagationMethod, $newBlock->getOwner());
                        
                        foreach ($newBlockSiteIds as $newBlockSiteId) {
                            unset($otherSiteBlocks[$newBlockSiteId]);
                        }
                    }
                }
            }
        };

        $elementsService->on(Elements::EVENT_BEFORE_RESAVE_ELEMENT, $callback);
        $elementsService->resaveElements($query);
        $elementsService->off(Elements::EVENT_BEFORE_RESAVE_ELEMENT, $callback);
    }


    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): string
    {
        return Craft::t('super-table', 'Applying new propagation method to Super Table blocks');
    }
}