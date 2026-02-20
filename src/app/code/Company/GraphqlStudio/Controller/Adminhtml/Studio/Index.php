<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Controller\Adminhtml\Studio;

use Company\GraphqlStudio\Model\StudioConfig;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Company_GraphqlStudio::studio';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly StudioConfig $studioConfig
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if (!$this->studioConfig->isStudioEnabled()) {
            $this->messageManager->addErrorMessage(__('GraphQL Studio is disabled by configuration.'));
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $redirect->setPath('adminhtml/dashboard/index');
            return $redirect;
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Company_GraphqlStudio::studio');
        $resultPage->getConfig()->getTitle()->prepend((string) __('GraphQL Studio'));
        return $resultPage;
    }
}
