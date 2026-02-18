<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Test\Unit\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Api\StoreResolverInterface;
use Company\VisibilityDebugger\Model\Check\WebsiteAssignmentCheck;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;

class WebsiteAssignmentCheckTest extends TestCase
{
    private ProductRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject $productRepository;
    private StoreResolverInterface|\PHPUnit\Framework\MockObject\MockObject $storeResolver;
    private WebsiteAssignmentCheck $check;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->storeResolver = $this->createMock(StoreResolverInterface::class);
        $this->check = new WebsiteAssignmentCheck($this->productRepository, $this->storeResolver);
    }

    public function testCheckReturnsPassWhenProductAssignedToStoreWebsite(): void
    {
        $storeId = 1;
        $productId = 42;
        $websiteId = 1;
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn($websiteId);
        $this->storeResolver->method('getStore')->with($storeId)->willReturn($store);

        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getWebsiteIds')->willReturn([1, 2]);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_PASS, $issues[0]->getStatus());
    }

    public function testCheckReturnsFailWhenProductNotAssignedToStoreWebsite(): void
    {
        $storeId = 1;
        $productId = 42;
        $websiteId = 2;
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn($websiteId);
        $this->storeResolver->method('getStore')->with($storeId)->willReturn($store);

        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getWebsiteIds')->willReturn([1]);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_FAIL, $issues[0]->getStatus());
        $this->assertNotNull($issues[0]->getSuggestion());
    }

    public function testCheckReturnsWarnWhenStoreInvalid(): void
    {
        $storeId = 999;
        $productId = 42;
        $this->storeResolver->method('getStore')->with($storeId)->willThrowException(new \Exception('Invalid store'));

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_WARN, $issues[0]->getStatus());
    }

    public function testCheckReturnsFailWhenProductNotFound(): void
    {
        $storeId = 1;
        $productId = 999;
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $this->storeResolver->method('getStore')->with($storeId)->willReturn($store);
        $this->productRepository->method('getById')->with($productId, false, $storeId)
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_FAIL, $issues[0]->getStatus());
    }
}
