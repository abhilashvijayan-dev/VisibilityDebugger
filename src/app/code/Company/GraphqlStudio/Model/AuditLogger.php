<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\ResourceConnection;

class AuditLogger
{
    private const TABLE = 'company_graphqlstudio_audit';
    private const MAX_ENDPOINT_LENGTH = 2048;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AdminSession $adminSession
    ) {
    }

    public function log(string $endpoint, string $status, int $durationMs): void
    {
        $adminUserId = (int) ($this->adminSession->getUser()?->getId() ?? 0);
        if ($adminUserId <= 0) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->insert($table, [
            'admin_user_id' => $adminUserId,
            'endpoint' => mb_substr($endpoint, 0, self::MAX_ENDPOINT_LENGTH),
            'status' => $status === 'success' ? 'success' : 'error',
            'duration_ms' => max(0, $durationMs),
        ]);
    }
}
