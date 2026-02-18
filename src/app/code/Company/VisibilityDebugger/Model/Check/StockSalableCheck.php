<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Api\StoreResolverInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Checks product salability for the store's stock.
 * Uses product->isSalable() which works with both legacy catalog-inventory and MSI (2.4.8).
 */
class StockSalableCheck extends AbstractCheck
{
    private const CODE = 'stock_salable';

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreResolverInterface $storeResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function check(int $productId, int $storeId): array
    {
        try {
            $this->storeResolver->getStore($storeId);
        } catch (\Throwable) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Stock / Salability'),
                VisibilityIssueInterface::STATUS_WARN,
                (string) __('Store is invalid.'),
                null
            )];
        }

        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Stock / Salability'),
                VisibilityIssueInterface::STATUS_FAIL,
                (string) __('Product not found.'),
                null
            )];
        }

        $isSalable = $product->isSalable();

        if ($isSalable) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Stock / Salability'),
                VisibilityIssueInterface::STATUS_PASS,
                (string) __('Product is salable for this store.'),
                null
            )];
        }

        return [$this->createIssue(
            self::CODE,
            (string) __('Stock / Salability'),
            VisibilityIssueInterface::STATUS_FAIL,
            (string) __('Product is not salable (out of stock or source misconfiguration).'),
            (string) __('Check Sources and Stock in Inventory, or Catalog > Product > Stock. Do not run reindex for stock — fix source/quantity instead.')
        )];
    }
}
