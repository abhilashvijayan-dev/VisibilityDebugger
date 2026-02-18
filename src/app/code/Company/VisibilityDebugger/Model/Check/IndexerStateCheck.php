<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\StateInterface;
use Magento\Indexer\Model\Indexer\CollectionFactory as IndexerCollectionFactory;

/**
 * Warns when key indexers are invalid. Suggests minimal reindex commands and status/cache commands.
 */
class IndexerStateCheck extends AbstractCheck
{
    private const CODE = 'indexer_state';

    /** Key indexers that affect product visibility */
    private const KEY_INDEXERS = [
        'catalog_product_attribute',
        'catalog_category_product',
        'catalog_product_price',
        'catalogsearch_fulltext',
    ];

    /**
     * @var IndexerCollectionFactory
     */
    private IndexerCollectionFactory $indexerCollectionFactory;

    public function __construct(IndexerCollectionFactory $indexerCollectionFactory)
    {
        $this->indexerCollectionFactory = $indexerCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function check(int $productId, int $storeId): array
    {
        $invalid = [];
        $collection = $this->indexerCollectionFactory->create();
        $collection->load();

        foreach ($collection->getItems() as $indexer) {
            if (!$indexer instanceof IndexerInterface) {
                continue;
            }
            $id = $indexer->getId();
            if (!in_array($id, self::KEY_INDEXERS, true)) {
                continue;
            }
            $state = $indexer->getState();
            if ($state && $state->getStatus() !== StateInterface::STATUS_VALID) {
                $invalid[] = $id;
            }
        }

        if (count($invalid) === 0) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Indexer Status'),
                VisibilityIssueInterface::STATUS_PASS,
                (string) __('Key catalog indexers are valid.'),
                null
            )];
        }

        $reindexCmd = 'bin/magento indexer:reindex ' . implode(' ', $invalid);
        $suggestion = $reindexCmd . "\n\n"
            . (string) __('Also run:') . "\n"
            . "bin/magento indexer:status\n"
            . "bin/magento cache:status";

        return [$this->createIssue(
            self::CODE,
            (string) __('Indexer Status'),
            VisibilityIssueInterface::STATUS_WARN,
            (string) __('Some key indexers are invalid: %1.', implode(', ', $invalid)),
            $suggestion
        )];
    }

    /**
     * Build minimal reindex suggestion based on other check results (for UI "Recommended commands").
     * Called by controller or UI with failed check codes.
     *
     * @param string[] $failedCheckCodes e.g. ['category_assignment', 'visibility_attribute']
     * @return string
     */
    public static function getRecommendedReindexCommand(array $failedCheckCodes): string
    {
        $indexers = [];
        if (in_array('category_assignment', $failedCheckCodes, true)) {
            $indexers[] = 'catalog_category_product';
            $indexers[] = 'catalog_product_category';
        }
        if (in_array('visibility_attribute', $failedCheckCodes, true)) {
            $indexers[] = 'catalog_product_attribute';
        }
        if (in_array('stock_salable', $failedCheckCodes, true)) {
            // Do NOT recommend reindex for stock; suggest stock/source checks
            // (handled in suggestion text, not here)
        }
        // Search visibility
        if (in_array('visibility_attribute', $failedCheckCodes, true)) {
            $indexers[] = 'catalogsearch_fulltext';
        }
        // Price
        $indexers[] = 'catalog_product_price';
        $indexers = array_unique($indexers);
        $existing = array_intersect($indexers, self::KEY_INDEXERS);
        if (count($existing) === 0) {
            return "bin/magento indexer:status\nbin/magento cache:status";
        }
        return 'bin/magento indexer:reindex ' . implode(' ', $existing);
    }
}
