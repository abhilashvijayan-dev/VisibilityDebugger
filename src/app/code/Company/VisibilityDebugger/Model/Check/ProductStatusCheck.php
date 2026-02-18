<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Api\StoreResolverInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Verifies product is enabled in the store scope (store-specific override possible).
 */
class ProductStatusCheck extends AbstractCheck
{
    private const CODE = 'product_status';

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
                (string) __('Product Status'),
                VisibilityIssueInterface::STATUS_WARN,
                (string) __('Store is invalid.'),
                (string) __('Select a valid store view.')
            )];
        }

        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Product Status'),
                VisibilityIssueInterface::STATUS_FAIL,
                (string) __('Product not found.'),
                null
            )];
        }

        $status = $product->getData(ProductInterface::STATUS);
        $isEnabled = (int) $status === Status::STATUS_ENABLED;

        if ($isEnabled) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Product Status'),
                VisibilityIssueInterface::STATUS_PASS,
                (string) __('Product is enabled in this store view.'),
                null
            )];
        }

        return [$this->createIssue(
            self::CODE,
            (string) __('Product Status'),
            VisibilityIssueInterface::STATUS_FAIL,
            (string) __('Product is disabled in this store view.'),
            (string) __('Enable the product: Product Edit > Enable Product, or set Status = Enabled for this store view.')
        )];
    }
}
