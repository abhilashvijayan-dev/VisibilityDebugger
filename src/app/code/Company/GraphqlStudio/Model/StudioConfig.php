<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;

class StudioConfig
{
    private const XML_PATH_ENABLED = 'company_graphqlstudio/general/enabled';
    private const XML_PATH_ALLOW_ENDPOINT_OVERRIDE = 'company_graphqlstudio/security/allow_endpoint_override';
    private const XML_PATH_ALLOW_MUTATIONS = 'company_graphqlstudio/security/allow_mutations';
    private const XML_PATH_INTROSPECTION_ENABLED = 'company_graphqlstudio/security/introspection_enabled';
    private const XML_PATH_TIMEOUT_SECONDS = 'company_graphqlstudio/execution/timeout_seconds';
    private const XML_PATH_MAX_RESPONSE_KB = 'company_graphqlstudio/execution/max_response_kb';
    private const XML_PATH_HISTORY_LIMIT_PER_USER = 'company_graphqlstudio/history/retention_limit_per_user';
    private const XML_PATH_HISTORY_TTL_DAYS = 'company_graphqlstudio/history/retention_ttl_days';
    private const XML_PATH_ALLOW_GLOBAL_HISTORY = 'company_graphqlstudio/history/allow_global';
    private const XML_PATH_DOCS_CACHE_TTL = 'company_graphqlstudio/docs/cache_ttl';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly State $appState
    ) {
    }

    public function isStudioEnabled(): bool
    {
        $raw = $this->scopeConfig->getValue(self::XML_PATH_ENABLED);
        if ($raw === null || $raw === '') {
            return !$this->isProductionMode();
        }

        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    public function isEndpointOverrideAllowed(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ALLOW_ENDPOINT_OVERRIDE);
    }

    public function isMutationsAllowed(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ALLOW_MUTATIONS);
    }

    public function isIntrospectionEnabled(): bool
    {
        $raw = $this->scopeConfig->getValue(self::XML_PATH_INTROSPECTION_ENABLED);
        if ($raw === null || $raw === '') {
            return $this->isDeveloperMode();
        }

        return $this->scopeConfig->isSetFlag(self::XML_PATH_INTROSPECTION_ENABLED);
    }

    public function getTimeoutSeconds(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_TIMEOUT_SECONDS);
        return max(3, min(120, $value));
    }

    public function getMaxResponseBytes(): int
    {
        $kb = (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_RESPONSE_KB);
        $kb = max(64, min(10240, $kb));
        return $kb * 1024;
    }

    public function getHistoryRetentionLimitPerUser(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_HISTORY_LIMIT_PER_USER);
        return max(10, min(2000, $value));
    }

    public function getHistoryTtlDays(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_HISTORY_TTL_DAYS);
        return max(1, min(365, $value));
    }

    public function isGlobalHistoryAllowed(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ALLOW_GLOBAL_HISTORY);
    }

    public function getDocsCacheTtl(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_DOCS_CACHE_TTL);
        return max(30, min(86400, $value));
    }

    public function isDeveloperMode(): bool
    {
        try {
            return $this->appState->getMode() === State::MODE_DEVELOPER;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isProductionMode(): bool
    {
        try {
            return $this->appState->getMode() === State::MODE_PRODUCTION;
        } catch (\Throwable) {
            return false;
        }
    }
}
