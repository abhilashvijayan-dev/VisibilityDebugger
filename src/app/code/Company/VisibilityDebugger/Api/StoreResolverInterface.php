<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Api;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolves store by ID. Centralizes store validation for diagnostics and controller.
 *
 * @api
 */
interface StoreResolverInterface
{
    /**
     * Return store for the given store ID.
     *
     * @param int $storeId
     * @return StoreInterface
     * @throws \Throwable When store does not exist or is invalid
     */
    public function getStore(int $storeId): StoreInterface;
}
