<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Test\Unit\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Model\Check\VisibilityAttributeCheck;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

class VisibilityAttributeCheckTest extends TestCase
{
    /**
     * @var ProductRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $productRepository;

    /**
     * @var VisibilityAttributeCheck
     */
    private $check;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->check = new VisibilityAttributeCheck($this->productRepository);
    }

    public function testCheckReturnsPassWhenVisibilityCatalogAndSearch(): void
    {
        $productId = 42;
        $storeId = 1;
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getData')->with(ProductInterface::VISIBILITY)
            ->willReturn((string) Visibility::VISIBILITY_BOTH);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_PASS, $issues[0]->getStatus());
    }

    public function testCheckReturnsFailWhenNotVisibleIndividually(): void
    {
        $productId = 42;
        $storeId = 1;
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getData')->with(ProductInterface::VISIBILITY)
            ->willReturn((string) Visibility::VISIBILITY_NOT_VISIBLE);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_FAIL, $issues[0]->getStatus());
        $this->assertNotNull($issues[0]->getSuggestion());
    }

    public function testCheckReturnsWarnWhenCatalogOnly(): void
    {
        $productId = 42;
        $storeId = 1;
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getData')->with(ProductInterface::VISIBILITY)
            ->willReturn((string) Visibility::VISIBILITY_IN_CATALOG);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_WARN, $issues[0]->getStatus());
    }

    public function testCheckReturnsWarnWhenSearchOnly(): void
    {
        $productId = 42;
        $storeId = 1;
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getData')->with(ProductInterface::VISIBILITY)
            ->willReturn((string) Visibility::VISIBILITY_IN_SEARCH);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_WARN, $issues[0]->getStatus());
    }

    public function testCheckReturnsFailWhenProductNotFound(): void
    {
        $productId = 999;
        $storeId = 1;
        $this->productRepository->method('getById')->with($productId, false, $storeId)
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_FAIL, $issues[0]->getStatus());
    }
}
