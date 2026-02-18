<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Verifies visibility is not "Not Visible Individually". Warns if "Catalog" only or "Search" only.
 */
class VisibilityAttributeCheck extends AbstractCheck
{
    private const CODE = 'visibility_attribute';

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
                (string) __('Visibility'),
                VisibilityIssueInterface::STATUS_FAIL,
                (string) __('Product not found.'),
                null
            )];
        }

        $visibility = $product->getData(ProductInterface::VISIBILITY);
        $visibility = $visibility !== null ? (int) $visibility : Visibility::VISIBILITY_NOT_VISIBLE;

        if ($visibility === Visibility::VISIBILITY_NOT_VISIBLE) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Visibility'),
                VisibilityIssueInterface::STATUS_FAIL,
                (string) __('Visibility is "Not Visible Individually".'),
                (string) __('Set Visibility to "Catalog", "Search", or "Catalog, Search" so the product can be seen.')
            )];
        }

        if ($visibility === Visibility::VISIBILITY_IN_CATALOG) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Visibility'),
                VisibilityIssueInterface::STATUS_WARN,
                (string) __('Visibility is "Catalog" only — product will not appear in search.'),
                (string) __('To show in search results, set Visibility to "Catalog, Search".')
            )];
        }

        if ($visibility === Visibility::VISIBILITY_IN_SEARCH) {
            return [$this->createIssue(
                self::CODE,
                (string) __('Visibility'),
                VisibilityIssueInterface::STATUS_WARN,
                (string) __('Visibility is "Search" only — product will not appear in category pages.'),
                (string) __('To show in category listing, set Visibility to "Catalog, Search".')
            )];
        }

        return [$this->createIssue(
            self::CODE,
            (string) __('Visibility'),
            VisibilityIssueInterface::STATUS_PASS,
            (string) __('Visibility allows storefront display (Catalog, Search).'),
            null
        )];
    }
}
