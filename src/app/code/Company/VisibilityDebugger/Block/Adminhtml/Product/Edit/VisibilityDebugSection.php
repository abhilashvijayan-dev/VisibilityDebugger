<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Block\Adminhtml\Product\Edit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Product edit section: "Why isn't this product visible?" with store selector and Run Debug.
 */
class VisibilityDebugSection extends Template
{
    private Registry $registry;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->storeManager = $storeManager;
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getProductId(): int
    {
        $product = $this->registry->registry('current_product');
        return $product ? (int) $product->getId() : 0;
    }

    /**
     * Current store id from request (store switcher) or default.
     */
    public function getStoreId(): int
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        return (int) $storeId;
    }

    /**
     * Base URL for admin debug endpoint.
     */
    public function getDebugUrl(): string
    {
        return $this->getUrl('company_visibilitydebugger/product/debug', ['_secure' => true]);
    }

    /**
     * Store list for the store view dropdown (value => store id, label => store name).
     *
     * @return list<array{value: int, label: string}>
     */
    public function getStoreList(): array
    {
        $stores = $this->storeManager->getStores(true);
        $list = [];
        foreach ($stores as $store) {
            $list[] = [
                'value' => (int) $store->getId(),
                'label' => $store->getName(),
            ];
        }
        return $list;
    }
}
