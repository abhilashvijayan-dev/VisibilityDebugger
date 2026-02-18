<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Verifies product is assigned to at least one category (lightweight: has category IDs).
 */
class CategoryAssignmentCheck extends AbstractCheck
{
    private const CODE = 'category_assignment';

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @inheritdoc
     */
    public function check(int $productId, int $storeId): array
    {
        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Category Assignment'),
                VisibilityIssueInterface::STATUS_FAIL,
                (string) __('Product not found.'),
                null
            )];
        }

        $categoryIds = $product->getCategoryIds();
        if (!is_array($categoryIds)) {
            $categoryIds = [];
        }
        $categoryIds = array_filter(array_map('intval', $categoryIds));

        if (count($categoryIds) > 0) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Category Assignment'),
                VisibilityIssueInterface::STATUS_PASS,
                (string) __('Product is assigned to at least one category.'),
                null
            )];
        }

        return [$this->createIssue(
            self::CODE,
            (string) __('Category Assignment'),
            VisibilityIssueInterface::STATUS_WARN,
            (string) __('Product is not assigned to any category.'),
            (string) __('Assign the product to at least one category in Product Edit > Categories. For "Catalog" visibility, category assignment is required for listing.')
        )];
    }
}
