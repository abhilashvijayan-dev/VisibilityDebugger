<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

use Magento\Framework\App\ResourceConnection;

class HistoryRepository
{
    private const TABLE = 'company_graphqlstudio_history';
    private const MAX_ENDPOINT_LENGTH = 2048;
    private const MAX_STORE_CODE_LENGTH = 64;
    private const MAX_OPERATION_NAME_LENGTH = 255;
    private const MAX_QUERY_LENGTH = 100000;
    private const MAX_JSON_LENGTH = 50000;
    private const MAX_ERROR_LENGTH = 2000;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly SensitiveDataSanitizer $sanitizer,
        private readonly StudioConfig $studioConfig
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $variables = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        $headers = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];
        $query = (string) ($payload['query'] ?? '');

        $sanitizedVariables = $this->sanitizer->sanitizeVariables($variables);
        $sanitizedHeaders = $this->sanitizer->sanitizeHeaders($headers);

        $data = [
            'admin_user_id' => (int) ($payload['admin_user_id'] ?? 0),
            'endpoint' => mb_substr((string) ($payload['endpoint'] ?? ''), 0, self::MAX_ENDPOINT_LENGTH),
            'store_code' => mb_substr((string) ($payload['store_code'] ?? ''), 0, self::MAX_STORE_CODE_LENGTH),
            'operation_name' => $this->extractOperationName($query),
            'query_text' => mb_substr($query, 0, self::MAX_QUERY_LENGTH),
            'variables_json' => $this->encodeSanitizedJson($sanitizedVariables),
            'headers_json' => $this->encodeSanitizedJson($sanitizedHeaders),
            'status' => ($payload['status'] ?? 'error') === 'success' ? 'success' : 'error',
            'error_summary' => $this->limitNullable((string) ($payload['error_summary'] ?? ''), self::MAX_ERROR_LENGTH),
            'duration_ms' => max(0, (int) ($payload['duration_ms'] ?? 0)),
            'is_starred' => 0,
        ];

        $connection->insert($table, $data);
        $entityId = (int) $connection->lastInsertId($table);

        $adminUserId = (int) ($payload['admin_user_id'] ?? 0);
        if ($adminUserId > 0) {
            $this->pruneForUser($adminUserId);
        }

        return $entityId;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total_count: int}
     */
    public function getList(int $pageSize, int $currentPage, int $adminUserId, bool $allowGlobal): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $pageSize = max(1, min(100, $pageSize));
        $currentPage = max(1, $currentPage);
        $offset = ($currentPage - 1) * $pageSize;

        $where = [];
        if (!$allowGlobal) {
            $where['admin_user_id = ?'] = $adminUserId;
        }

        $select = $connection->select()->from(
            ['h' => $table],
            [
                'entity_id',
                'admin_user_id',
                'endpoint',
                'store_code',
                'operation_name',
                'status',
                'error_summary',
                'duration_ms',
                'is_starred',
                'created_at',
            ]
        )->order('is_starred DESC')->order('entity_id DESC')->limit($pageSize, $offset);

        foreach ($where as $condition => $value) {
            $select->where($condition, $value);
        }

        $countSelect = $connection->select()->from(['h' => $table], ['total_count' => 'COUNT(*)']);
        foreach ($where as $condition => $value) {
            $countSelect->where($condition, $value);
        }

        return [
            'items' => $connection->fetchAll($select),
            'total_count' => (int) $connection->fetchOne($countSelect),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $entityId, int $adminUserId, bool $allowGlobal): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()->from(['h' => $table])->where('entity_id = ?', $entityId)->limit(1);
        if (!$allowGlobal) {
            $select->where('admin_user_id = ?', $adminUserId);
        }

        $row = $connection->fetchRow($select);
        return $row ?: null;
    }

    public function setStar(int $entityId, bool $isStarred, int $adminUserId, bool $allowGlobal): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $where = ['entity_id = ?' => $entityId];
        if (!$allowGlobal) {
            $where['admin_user_id = ?'] = $adminUserId;
        }

        $affected = $connection->update(
            $table,
            ['is_starred' => $isStarred ? 1 : 0],
            $where
        );

        return $affected > 0;
    }

    private function extractOperationName(string $query): ?string
    {
        if (preg_match('/\b(?:query|mutation|subscription)\s+([_A-Za-z][_0-9A-Za-z]*)/m', $query, $match)) {
            return mb_substr($match[1], 0, self::MAX_OPERATION_NAME_LENGTH);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeSanitizedJson(array $payload): string
    {
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (strlen($json) <= self::MAX_JSON_LENGTH) {
            return $json;
        }

        $previewMax = max(20, self::MAX_JSON_LENGTH - 100);
        return (string) json_encode(
            [
                '_truncated' => true,
                '_preview' => mb_substr($json, 0, $previewMax),
            ],
            JSON_UNESCAPED_SLASHES
        );
    }

    private function limitNullable(string $value, int $length): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $length);
    }

    private function pruneForUser(int $adminUserId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $limit = $this->studioConfig->getHistoryRetentionLimitPerUser();
        $ttlDays = $this->studioConfig->getHistoryTtlDays();

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($ttlDays * 86400));
        $connection->delete(
            $table,
            [
                'admin_user_id = ?' => $adminUserId,
                'created_at < ?' => $cutoff,
            ]
        );

        $idsToKeep = $connection->fetchCol(
            $connection->select()
                ->from($table, ['entity_id'])
                ->where('admin_user_id = ?', $adminUserId)
                ->order('entity_id DESC')
                ->limit($limit)
        );

        if (!$idsToKeep) {
            return;
        }

        $connection->delete(
            $table,
            [
                'admin_user_id = ?' => $adminUserId,
                'entity_id NOT IN (?)' => $idsToKeep,
            ]
        );
    }
}
