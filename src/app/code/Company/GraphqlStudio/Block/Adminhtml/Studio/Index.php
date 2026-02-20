<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Block\Adminhtml\Studio;

use Company\GraphqlStudio\Model\EndpointResolver;
use Company\GraphqlStudio\Model\DocsEndpointPreference;
use Company\GraphqlStudio\Model\StudioConfig;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;

class Index extends Template
{
    public function __construct(
        Context $context,
        private readonly EndpointResolver $endpointResolver,
        private readonly DocsEndpointPreference $docsEndpointPreference,
        private readonly StudioConfig $studioConfig,
        private readonly FormKey $formKeyProvider,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getExecuteUrl(): string
    {
        return $this->getUrl('graphqlstudio/studio/execute');
    }

    public function getDefaultEndpoint(): string
    {
        return $this->endpointResolver->getDefaultEndpoint();
    }

    public function isEndpointOverrideAllowed(): bool
    {
        return $this->endpointResolver->isEndpointOverrideAllowed();
    }

    public function getFormKey(): string
    {
        return $this->formKeyProvider->getFormKey();
    }

    public function getHistoryListUrl(): string
    {
        return $this->getUrl('graphqlstudio/history/index');
    }

    public function getHistoryViewUrl(): string
    {
        return $this->getUrl('graphqlstudio/history/view');
    }

    public function getHistoryStarUrl(): string
    {
        return $this->getUrl('graphqlstudio/history/star');
    }

    public function getDocsUrl(): string
    {
        return $this->getUrl('graphqlstudio/api/docs');
    }

    public function getCurrentStoreCode(): string
    {
        return (string) $this->storeManager->getStore()->getCode();
    }

    public function getWebDefaultEndpoint(): string
    {
        $baseUrl = (string) $this->storeManager->getStore()->getBaseUrl();
        return rtrim($baseUrl, '/') . '/graphql';
    }

    public function getSavedDocsEndpoint(): string
    {
        return $this->docsEndpointPreference->getForCurrentUser();
    }

    public function getSavedOrDefaultDocsEndpoint(): string
    {
        $saved = $this->getSavedDocsEndpoint();
        return $saved !== '' ? $saved : $this->getWebDefaultEndpoint();
    }

    public function getGetDocsEndpointUrl(): string
    {
        return $this->getUrl('graphqlstudio/api/get');
    }

    public function getSaveDocsEndpointUrl(): string
    {
        return $this->getUrl('graphqlstudio/api/save');
    }

    public function getCodeDocsUrl(): string
    {
        return $this->getUrl('graphqlstudio/api/codeDocs');
    }

    public function getEffectiveConfigRows(): array
    {
        return [
            ['label' => 'Studio Enabled', 'value' => $this->boolLabel($this->studioConfig->isStudioEnabled())],
            ['label' => 'Endpoint Override', 'value' => $this->boolLabel($this->studioConfig->isEndpointOverrideAllowed())],
            ['label' => 'Mutations Allowed', 'value' => $this->boolLabel($this->studioConfig->isMutationsAllowed())],
            ['label' => 'Introspection Enabled', 'value' => $this->boolLabel($this->studioConfig->isIntrospectionEnabled())],
            ['label' => 'Timeout (s)', 'value' => (string) $this->studioConfig->getTimeoutSeconds()],
            ['label' => 'Max Response (KB)', 'value' => (string) (int) floor($this->studioConfig->getMaxResponseBytes() / 1024)],
            ['label' => 'History Limit/User', 'value' => (string) $this->studioConfig->getHistoryRetentionLimitPerUser()],
            ['label' => 'History TTL (days)', 'value' => (string) $this->studioConfig->getHistoryTtlDays()],
            ['label' => 'Global History', 'value' => $this->boolLabel($this->studioConfig->isGlobalHistoryAllowed())],
            ['label' => 'Docs Cache TTL (s)', 'value' => (string) $this->studioConfig->getDocsCacheTtl()],
            ['label' => 'App Mode', 'value' => $this->studioConfig->isDeveloperMode() ? 'developer' : 'non-developer'],
        ];
    }

    private function boolLabel(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
