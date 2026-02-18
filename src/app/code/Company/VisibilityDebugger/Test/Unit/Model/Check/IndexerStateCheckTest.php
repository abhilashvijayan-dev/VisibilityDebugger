<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Test\Unit\Model\Check;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;
use Company\VisibilityDebugger\Model\Check\IndexerStateCheck;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\StateInterface;
use Magento\Indexer\Model\Indexer\Collection;
use Magento\Indexer\Model\Indexer\CollectionFactory as IndexerCollectionFactory;
use PHPUnit\Framework\TestCase;

class IndexerStateCheckTest extends TestCase
{
    /**
     * @var IndexerCollectionFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $collectionFactory;

    /**
     * @var IndexerStateCheck
     */
    private $check;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(IndexerCollectionFactory::class);
        $this->check = new IndexerStateCheck($this->collectionFactory);
    }

    public function testCheckReturnsPassWhenAllKeyIndexersValid(): void
    {
        $collection = $this->createMock(Collection::class);
        $this->collectionFactory->method('create')->willReturn($collection);

        $indexer = $this->createMock(IndexerInterface::class);
        $state = $this->createMock(StateInterface::class);
        $state->method('getStatus')->willReturn(StateInterface::STATUS_VALID);
        $indexer->method('getId')->willReturn('catalog_product_attribute');
        $indexer->method('getState')->willReturn($state);

        $collection->method('load')->willReturnSelf();
        $collection->method('getItems')->willReturn([$indexer]);

        $issues = $this->check->check(42, 1);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_PASS, $issues[0]->getStatus());
    }

    public function testCheckReturnsWarnWhenSomeIndexersInvalid(): void
    {
        $collection = $this->createMock(Collection::class);
        $this->collectionFactory->method('create')->willReturn($collection);

        $indexer = $this->createMock(IndexerInterface::class);
        $state = $this->createMock(StateInterface::class);
        $state->method('getStatus')->willReturn(StateInterface::STATUS_INVALID);
        $indexer->method('getId')->willReturn('catalog_product_attribute');
        $indexer->method('getState')->willReturn($state);

        $collection->method('load')->willReturnSelf();
        $collection->method('getItems')->willReturn([$indexer]);

        $issues = $this->check->check(42, 1);
        $this->assertCount(1, $issues);
        $this->assertSame(VisibilityIssueInterface::STATUS_WARN, $issues[0]->getStatus());
        $this->assertNotNull($issues[0]->getSuggestion());
        $this->assertStringContainsString('indexer:reindex', $issues[0]->getSuggestion());
    }

    public function testGetRecommendedReindexCommandIncludesCategoryIndexersForCategoryAssignment(): void
    {
        $cmd = IndexerStateCheck::getRecommendedReindexCommand(['category_assignment']);
        $this->assertStringContainsString('catalog_category_product', $cmd);
    }

    public function testGetRecommendedReindexCommandIncludesAttributeIndexerForVisibilityAttribute(): void
    {
        $cmd = IndexerStateCheck::getRecommendedReindexCommand(['visibility_attribute']);
        $this->assertStringContainsString('catalog_product_attribute', $cmd);
    }

    public function testGetRecommendedReindexCommandDoesNotRecommendCategoryReindexForStockSalable(): void
    {
        $cmd = IndexerStateCheck::getRecommendedReindexCommand(['stock_salable']);
        $this->assertStringNotContainsString('catalog_category_product', $cmd);
    }

    public function testGetRecommendedReindexCommandReturnsReindexPriceWhenNoFailedChecks(): void
    {
        $cmd = IndexerStateCheck::getRecommendedReindexCommand([]);
        $this->assertStringContainsString('catalog_product_price', $cmd);
        $this->assertStringContainsString('indexer:reindex', $cmd);
    }
}
