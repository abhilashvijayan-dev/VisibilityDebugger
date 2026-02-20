<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Controller\Adminhtml\Api;

use Company\GraphqlStudio\Model\DocsProvider;
use Company\GraphqlStudio\Model\StudioConfig;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

class Docs extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Company_GraphqlStudio::studio';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly DocsProvider $docsProvider,
        private readonly StudioConfig $studioConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        if (!$this->studioConfig->isStudioEnabled()) {
            return $result->setData([
                'success' => false,
                'error' => (string) __('GraphQL Studio is disabled by configuration.'),
            ]);
        }

        $storeCode = trim((string) $this->getRequest()->getParam('store_code'));
        if ($storeCode === '') {
            $storeCode = (string) $this->storeManager->getStore()->getCode();
        }

        if (!$this->studioConfig->isIntrospectionEnabled()) {
            return $result->setData([
                'success' => false,
                'error' => (string) __('Introspection is disabled by configuration.'),
            ]);
        }

        try {
            $docs = $this->docsProvider->getDocs(
                $storeCode,
                (string) $this->getRequest()->getParam('endpoint')
            );

            return $result->setData([
                'success' => true,
                'store_code' => $storeCode,
                'endpoint_used' => $docs['endpoint_used'],
                'cache_hit' => $docs['cache_hit'],
                'docs' => $docs['data'],
            ]);
        } catch (\Throwable $exception) {
            return $result->setData([
                'success' => false,
                'error' => (string) __('Unable to load GraphQL docs: %1', $exception->getMessage()),
            ]);
        }
    }
}
