<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Controller\Adminhtml\Api;

use Company\GraphqlStudio\Model\DocsEndpointPreference;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Company_GraphqlStudio::studio';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly DocsEndpointPreference $preference
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $endpoint = (string) $this->getRequest()->getParam('endpoint');

        if (!$this->preference->saveForCurrentUser($endpoint)) {
            return $result->setData([
                'success' => false,
                'error' => (string) __('Invalid docs endpoint URL.'),
            ]);
        }

        return $result->setData([
            'success' => true,
            'endpoint' => $this->preference->getForCurrentUser(),
        ]);
    }
}
