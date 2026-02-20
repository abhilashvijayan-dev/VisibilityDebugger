<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

class SensitiveDataSanitizer
{
    private const REDACTED = '[REDACTED]';

    /** @var string[] */
    private array $allowlistKeys = [
        'content-type',
        'accept',
        'store',
        'storecode',
        'token_type',
        'currentpage',
        'pagesize',
    ];

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function sanitizeVariables(array $variables): array
    {
        return $this->sanitizeArray($variables);
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function sanitizeHeaders(array $headers): array
    {
        $filtered = [];
        foreach ($headers as $name => $value) {
            $normalized = strtolower(trim((string) $name));
            if ($normalized === 'authorization' || $normalized === 'proxy-authorization') {
                continue;
            }
            $filtered[(string) $name] = $value;
        }

        return $this->sanitizeArray($filtered);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $keyString = (string) $key;
            if ($this->shouldRedact($keyString)) {
                $result[$keyString] = self::REDACTED;
                continue;
            }

            if (is_array($value)) {
                $result[$keyString] = $this->sanitizeArray($value);
                continue;
            }

            if (is_string($value)) {
                $result[$keyString] = mb_substr($value, 0, 4000);
                continue;
            }

            $result[$keyString] = $value;
        }

        return $result;
    }

    private function shouldRedact(string $key): bool
    {
        $normalized = strtolower(trim($key));
        if (in_array($normalized, $this->allowlistKeys, true)) {
            return false;
        }

        return (bool) preg_match(
            '/(password|passwd|token|authorization|secret|api[_-]?key|access[_-]?key|private[_-]?key|bearer|cookie)/i',
            $normalized
        );
    }
}
