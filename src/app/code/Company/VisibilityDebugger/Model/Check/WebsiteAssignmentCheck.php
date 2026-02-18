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
 * Verifies product is assigned to the website of the given store.
 */
class WebsiteAssignmentCheck extends AbstractCheck
{
    private const CODE = 'website_assignment';

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
            $store = $this->storeResolver->getStore($storeId);
        } catch (\Throwable) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Website Assignment'),
                VisibilityIssueInterface::STATUS_WARN,
                (string) __('Store is invalid.'),
                null
            )];
        }

        $websiteId = (int) $store->getWebsiteId();

        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Website Assignment'),
                VisibilityIssueInterface::STATUS_FAIL,
                (string) __('Product not found.'),
                null
            )];
        }

        $productWebsiteIds = $product->getWebsiteIds();
        if (!is_array($productWebsiteIds)) {
            $productWebsiteIds = [];
        }
        $assigned = in_array($websiteId, array_map('intval', $productWebsiteIds), true);

        if ($assigned) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Website Assignment'),
                VisibilityIssueInterface::STATUS_PASS,
                (string) __('Product is assigned to the website for this store view.'),
                null
            )];
        }

        return [$this->createIssue(
            self::CODE,
            (string) __('Website Assignment'),
            VisibilityIssueInterface::STATUS_FAIL,
            (string) __('Product is not assigned to the website for this store view.'),
            (string) __('In Product Edit, under "Product in Websites", check the website that contains this store.')
        )];
    }
}
