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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Star extends Action implements HttpPostActionInterface
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
        $isStarred = (int) $this->getRequest()->getParam('is_starred', 0) === 1;

        if ($entityId <= 0) {
            return $result->setData([
                'success' => false,
                'error' => (string) __('Invalid history ID.'),
            ]);
        }

        $adminUserId = (int) ($this->adminSession->getUser()?->getId() ?? 0);
        $updated = $this->historyRepository->setStar(
            $entityId,
            $isStarred,
            $adminUserId,
            $this->studioConfig->isGlobalHistoryAllowed()
        );

        return $result->setData([
            'success' => $updated,
            'id' => $entityId,
            'is_starred' => $isStarred ? 1 : 0,
            'error' => $updated ? null : (string) __('Unable to update history entry.'),
        ]);
    }
}
