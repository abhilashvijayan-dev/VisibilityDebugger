<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Test\Unit\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Api\StoreResolverInterface;
use Company\VisibilityDebugger\Model\Check\ProductStatusCheck;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;

class ProductStatusCheckTest extends TestCase
{
    private ProductRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject $productRepository;
    private StoreResolverInterface|\PHPUnit\Framework\MockObject\MockObject $storeResolver;
    private ProductStatusCheck $check;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->storeResolver = $this->createMock(StoreResolverInterface::class);
        $this->check = new ProductStatusCheck($this->productRepository, $this->storeResolver);
    }

    public function testCheckReturnsPassWhenProductIsEnabled(): void
    {
        $storeId = 1;
        $productId = 42;
        $this->storeResolver->method('getStore')->with($storeId)->willReturn($this->createMock(StoreInterface::class));

        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getData')->with(ProductInterface::STATUS)->willReturn((string) Status::STATUS_ENABLED);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_PASS, $issues[0]->getStatus());
        $this->assertSame('product_status', $issues[0]->getCode());
    }

    public function testCheckReturnsFailWhenProductIsDisabled(): void
    {
        $storeId = 1;
        $productId = 42;
        $this->storeResolver->method('getStore')->with($storeId)->willReturn($this->createMock(StoreInterface::class));

        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getData')->with(ProductInterface::STATUS)->willReturn((string) Status::STATUS_DISABLED);
        $this->productRepository->method('getById')->with($productId, false, $storeId)->willReturn($product);

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_FAIL, $issues[0]->getStatus());
        $this->assertNotNull($issues[0]->getSuggestion());
    }

    public function testCheckReturnsFailWhenProductNotFound(): void
    {
        $storeId = 1;
        $productId = 999;
        $this->storeResolver->method('getStore')->with($storeId)->willReturn($this->createMock(StoreInterface::class));
        $this->productRepository->method('getById')->with($productId, false, $storeId)
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        $issues = $this->check->check($productId, $storeId);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_FAIL, $issues[0]->getStatus());
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
}
