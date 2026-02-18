<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\VisibilityDebugger\Api;

use Company\VisibilityDebugger\Api\Data\VisibilityIssueInterface;

/**
 * Single visibility check: runs one diagnostic and returns one or more issues.
 *
 * @api
 */
interface VisibilityCheckInterface
{
    /**
     * Run this check for the given product and store.
     *
     * @param int $productId
     * @param int $storeId
     * @return VisibilityIssueInterface[]
     */
    public function check(int $productId, int $storeId): array;
}
