<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Model\Service;

use Company\VisibilityDebugger\Api\StoreResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves store by ID via StoreManager. Exceptions bubble to callers.
 */
class StoreResolver implements StoreResolverInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getStore(int $storeId): StoreInterface
    {
        return $this->storeManager->getStore($storeId);
    }
}
