<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Controller\Adminhtml\Api;

use Company\GraphqlStudio\Model\CodeSchemaScanner;
use Company\GraphqlStudio\Model\StudioConfig;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class CodeDocs extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Company_GraphqlStudio::studio';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CodeSchemaScanner $scanner,
        private readonly StudioConfig $studioConfig
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

        try {
            $refresh = (int) $this->getRequest()->getParam('refresh', 0) === 1;
            $scanResult = $this->scanner->scan($refresh);

            return $result->setData([
                'success' => true,
                'modules' => $scanResult['modules'],
                'total_modules' => $scanResult['total_modules'],
                'total_fields' => $scanResult['total_fields'],
                'cache_hit' => $scanResult['cache_hit'],
            ]);
        } catch (\Throwable $exception) {
            return $result->setData([
                'success' => false,
                'error' => (string) __('Unable to scan schema files: %1', $exception->getMessage()),
            ]);
        }
    }
}
