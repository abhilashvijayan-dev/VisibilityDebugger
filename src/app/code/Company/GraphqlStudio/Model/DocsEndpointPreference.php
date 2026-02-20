<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\FlagManager;

class DocsEndpointPreference
{
    private const FLAG_PREFIX = 'company_graphqlstudio_docs_endpoint_';
    private const FLAG_GLOBAL = 'company_graphqlstudio_docs_endpoint_global';
    private const MAX_LENGTH = 2048;

    public function __construct(
        private readonly FlagManager $flagManager,
        private readonly AdminSession $adminSession
    ) {
    }

    public function getForCurrentUser(): string
    {
        $userId = (int) ($this->adminSession->getUser()?->getId() ?? 0);
        if ($userId > 0) {
            $value = (string) ($this->flagManager->getFlagData($this->getFlagCode($userId)) ?? '');
            if ($this->isValidEndpoint($value)) {
                return $value;
            }
        }

        $globalValue = (string) ($this->flagManager->getFlagData(self::FLAG_GLOBAL) ?? '');
        return $this->isValidEndpoint($globalValue) ? $globalValue : '';
    }

    public function saveForCurrentUser(string $endpoint): bool
    {
        $userId = (int) ($this->adminSession->getUser()?->getId() ?? 0);
        $endpoint = mb_substr(trim($endpoint), 0, self::MAX_LENGTH);
        if (!$this->isValidEndpoint($endpoint)) {
            return false;
        }

        if ($userId > 0) {
            $this->flagManager->saveFlag($this->getFlagCode($userId), $endpoint);
        }
        $this->flagManager->saveFlag(self::FLAG_GLOBAL, $endpoint);
        return true;
    }

    private function getFlagCode(int $userId): string
    {
        return self::FLAG_PREFIX . $userId;
    }

    private function isValidEndpoint(string $endpoint): bool
    {
        return (bool) preg_match('#^https?://#i', $endpoint);
    }
}
