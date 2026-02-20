<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Controller\Adminhtml\History;

use Company\GraphqlStudio\Model\HistoryRepository;
use Company\GraphqlStudio\Model\StudioConfig;
use Magento\Backend\App\Action;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Company_GraphqlStudio::studio';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly HistoryRepository $historyRepository,
        private readonly StudioConfig $studioConfig,
        private readonly AdminSession $adminSession
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

        $entityId = (int) $this->getRequest()->getParam('id');
        if ($entityId <= 0) {
            return $result->setData([
                'success' => false,
                'error' => (string) __('Invalid history ID.'),
            ]);
        }

        $adminUserId = (int) ($this->adminSession->getUser()?->getId() ?? 0);
        $row = $this->historyRepository->getById(
            $entityId,
            $adminUserId,
            $this->studioConfig->isGlobalHistoryAllowed()
        );

        if (!$row) {
            return $result->setData([
                'success' => false,
                'error' => (string) __('History entry not found.'),
            ]);
        }

        return $result->setData([
            'success' => true,
            'item' => $row,
        ]);
    }
}
