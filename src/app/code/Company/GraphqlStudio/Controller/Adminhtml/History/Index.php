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

class Index extends Action implements HttpGetActionInterface
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

        $pageSize = (int) $this->getRequest()->getParam('pageSize', 20);
        $currentPage = (int) $this->getRequest()->getParam('currentPage', 1);
        $adminUserId = (int) ($this->adminSession->getUser()?->getId() ?? 0);

        $list = $this->historyRepository->getList(
            $pageSize,
            $currentPage,
            $adminUserId,
            $this->studioConfig->isGlobalHistoryAllowed()
        );

        return $result->setData([
            'success' => true,
            'items' => $list['items'],
            'total_count' => $list['total_count'],
            'page_size' => max(1, min(100, $pageSize)),
            'current_page' => max(1, $currentPage),
        ]);
    }
}
