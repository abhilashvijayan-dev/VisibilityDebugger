<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Test\Unit\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Model\Check\CategoryAssignmentCheck;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

class CategoryAssignmentCheckTest extends TestCase
{
    /**
     * @var ProductRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $productRepository;

    /**
     * @var CategoryAssignmentCheck
     */
    private $check;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->check = new CategoryAssignmentCheck($this->productRepository);
    }

    public function testCheckReturnsPassWhenProductHasCategories(): void
    {
        $productId = 42;
        $storeId = 1;
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getCategoryIds')->willReturn([3, 5]);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_PASS, $issues[0]->getStatus());
    }

    public function testCheckReturnsWarnWhenProductHasNoCategories(): void
    {
        $productId = 42;
        $storeId = 1;
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getCategoryIds')->willReturn([]);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_WARN, $issues[0]->getStatus());
        $this->assertNotNull($issues[0]->getSuggestion());
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
