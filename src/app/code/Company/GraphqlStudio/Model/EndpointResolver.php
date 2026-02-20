<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

use Magento\Store\Model\StoreManagerInterface;

class EndpointResolver
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly StudioConfig $studioConfig
    ) {
    }

    public function getDefaultEndpoint(): string
    {
        $envEndpoint = trim((string) getenv('GRAPHQLSTUDIO_DEFAULT_ENDPOINT'));
        if ($this->isValidEndpoint($envEndpoint)) {
            return $envEndpoint;
        }

        $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
        return rtrim($baseUrl, '/') . '/graphql';
    }

    public function isEndpointOverrideAllowed(): bool
    {
        return $this->studioConfig->isEndpointOverrideAllowed();
    }

    public function resolveEndpoint(?string $requestedEndpoint): string
    {
        if (!$this->isEndpointOverrideAllowed()) {
            return $this->getDefaultEndpoint();
        }

        $requestedEndpoint = trim((string) $requestedEndpoint);
        if ($requestedEndpoint === '') {
            return $this->getDefaultEndpoint();
        }

        if (!$this->isValidEndpoint($requestedEndpoint)) {
            return $this->getDefaultEndpoint();
        }

        return $requestedEndpoint;
    }

    private function isValidEndpoint(string $endpoint): bool
    {
        return (bool) preg_match('#^https?://#i', trim($endpoint));
    }

}
