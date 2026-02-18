<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Controller\Adminhtml\Product;

use Company\VisibilityDebugger\Api\StoreResolverInterface;
use Company\VisibilityDebugger\Model\Check\IndexerStateCheck;
use Company\VisibilityDebugger\Model\Service\VisibilityDiagnosticsService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Admin JSON endpoint: run visibility diagnostics for a product and store.
 */
class Debug extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Company_VisibilityDebugger::debug';

    public function __construct(
        Context $context,
        private readonly VisibilityDiagnosticsService $diagnosticsService,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreResolverInterface $storeResolver
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        /** @var Json $result */

        $productId = (int) $this->getRequest()->getParam('product_id');
        $storeId = (int) $this->getRequest()->getParam('store_id');

        if ($productId <= 0) {
            $result->setData([
                'success' => false,
                'error' => (string) __('Invalid product ID.'),
                'product_id' => $productId,
                'store_id' => $storeId,
                'issues' => [],
            ]);
            return $result;
        }

        try {
            $this->storeResolver->getStore($storeId);
        } catch (\Throwable) {
            $result->setData([
                'success' => false,
                'error' => (string) __('Invalid store.'),
                'product_id' => $productId,
                'store_id' => $storeId,
                'issues' => [],
            ]);
            return $result;
        }

        try {
            $this->productRepository->getById($productId, false, $storeId);
        } catch (\Throwable) {
            $result->setData([
                'success' => false,
                'error' => (string) __('Product not found.'),
                'product_id' => $productId,
                'store_id' => $storeId,
                'issues' => [],
            ]);
            return $result;
        }

        try {
            $issues = $this->diagnosticsService->run($productId, $storeId);
        } catch (\Throwable) {
            $result->setData([
                'success' => false,
                'error' => (string) __('Diagnostics could not be completed.'),
                'product_id' => $productId,
                'store_id' => $storeId,
                'issues' => [],
            ]);
            return $result;
        }

        $issuesData = array_map(static function ($issue) {
            return [
                'code' => $issue->getCode(),
                'title' => $issue->getTitle(),
                'status' => $issue->getStatus(),
                'message' => $issue->getMessage(),
                'suggestion' => $issue->getSuggestion(),
                'docs_url' => $issue->getDocsUrl(),
            ];
        }, $issues);

        $failedCodes = array_map(
            static fn($i) => $i->getCode(),
            array_filter($issues, static fn($i) => $i->getStatus() === 'fail')
        );
        $recommendedCommand = count($failedCodes) > 0
            ? IndexerStateCheck::getRecommendedReindexCommand(array_values($failedCodes))
            : '';

        $result->setData([
            'success' => true,
            'product_id' => $productId,
            'store_id' => $storeId,
            'issues' => $issuesData,
            'recommended_commands' => $recommendedCommand,
        ]);

        return $result;
    }
}
