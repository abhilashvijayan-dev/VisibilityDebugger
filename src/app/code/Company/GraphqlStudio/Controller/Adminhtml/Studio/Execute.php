<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Controller\Adminhtml\Studio;

use Company\GraphqlStudio\Model\AuditLogger;
use Company\GraphqlStudio\Model\EndpointResolver;
use Company\GraphqlStudio\Model\GraphqlExecutor;
use Company\GraphqlStudio\Model\HistoryRepository;
use Company\GraphqlStudio\Model\StudioConfig;
use Magento\Backend\App\Action;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\State;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

class Execute extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Company_GraphqlStudio::studio';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly EndpointResolver $endpointResolver,
        private readonly GraphqlExecutor $graphqlExecutor,
        private readonly StudioConfig $studioConfig,
        private readonly HistoryRepository $historyRepository,
        private readonly AuditLogger $auditLogger,
        private readonly AdminSession $adminSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $startedAt = hrtime(true);
        $queryForHistory = (string) $this->getRequest()->getParam('query');
        $variablesForHistory = [];
        $headersForHistory = [];
        $endpointForHistory = $this->endpointResolver->getDefaultEndpoint();
        $auditStatus = 'error';
        $durationMs = 0;

        try {
            if (!$this->studioConfig->isStudioEnabled()) {
                return $result->setData([
                    'success' => false,
                    'error' => (string) __('GraphQL Studio is disabled by configuration.'),
                ]);
            }

            $query = trim($queryForHistory);
            if ($query === '') {
                return $result->setData([
                    'success' => false,
                    'error' => (string) __('Query is required.'),
                ]);
            }

            if (!$this->studioConfig->isMutationsAllowed() && $this->isMutationOperation($query)) {
                return $result->setData([
                    'success' => false,
                    'error' => (string) __('Mutations are disabled by configuration.'),
                ]);
            }

            $variables = $this->decodeJsonObject(
                (string) $this->getRequest()->getParam('variables', '{}'),
                'variables'
            );
            $variablesForHistory = $variables;
            $headers = $this->normalizeHeaders(
                $this->decodeJsonObject(
                    (string) $this->getRequest()->getParam('headers', '{}'),
                    'headers'
                )
            );
            $headersForHistory = $headers;

            $endpoint = $this->endpointResolver->resolveEndpoint(
                (string) $this->getRequest()->getParam('endpoint')
            );
            $endpointForHistory = $endpoint;

            $execution = $this->graphqlExecutor->execute(
                $endpoint,
                $query,
                $variables,
                $headers
            );

            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            if ($execution['truncated']) {
                return $result->setData([
                    'success' => false,
                    'error' => (string) __(
                        'Response exceeded the maximum allowed size of %1 bytes.',
                        $this->studioConfig->getMaxResponseBytes()
                    ),
                    'duration_ms' => $durationMs,
                    'http_status' => $execution['http_status'],
                ]);
            }

            $decodedResponse = json_decode($execution['response_body'], true);
            $jsonDecodeOk = json_last_error() === JSON_ERROR_NONE;
            $status = 'success';
            $errorSummary = null;
            if ($jsonDecodeOk && isset($decodedResponse['errors']) && is_array($decodedResponse['errors']) && $decodedResponse['errors']) {
                $status = 'error';
                $firstError = $decodedResponse['errors'][0]['message'] ?? 'GraphQL returned errors.';
                $errorSummary = (string) $firstError;
            }
            $auditStatus = $status;

            $this->historyRepository->save([
                'admin_user_id' => (int) ($this->adminSession->getUser()?->getId() ?? 0),
                'endpoint' => $endpoint,
                'store_code' => (string) $this->storeManager->getStore()->getCode(),
                'query' => $query,
                'variables' => $variablesForHistory,
                'headers' => $headersForHistory,
                'status' => $status,
                'error_summary' => $errorSummary,
                'duration_ms' => $durationMs,
            ]);

            return $result->setData([
                'success' => true,
                'duration_ms' => $durationMs,
                'http_status' => $execution['http_status'],
                'endpoint_used' => $endpoint,
                'response_json' => $jsonDecodeOk ? $decodedResponse : null,
                'response_text' => $jsonDecodeOk ? null : $execution['response_body'],
            ]);
        } catch (\InvalidArgumentException $exception) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $this->historyRepository->save([
                'admin_user_id' => (int) ($this->adminSession->getUser()?->getId() ?? 0),
                'endpoint' => $endpointForHistory,
                'store_code' => (string) $this->storeManager->getStore()->getCode(),
                'query' => $queryForHistory,
                'variables' => $variablesForHistory,
                'headers' => $headersForHistory,
                'status' => 'error',
                'error_summary' => $exception->getMessage(),
                'duration_ms' => $durationMs,
            ]);
            return $result->setData([
                'success' => false,
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $errorMessage = (string) __('Request execution failed.');
            if ($this->isDeveloperMode()) {
                $errorMessage = sprintf(
                    '%s (%s)',
                    $exception->getMessage() ?: 'Unknown error',
                    $exception::class
                );
            }

            $this->historyRepository->save([
                'admin_user_id' => (int) ($this->adminSession->getUser()?->getId() ?? 0),
                'endpoint' => $endpointForHistory,
                'store_code' => (string) $this->storeManager->getStore()->getCode(),
                'query' => $queryForHistory,
                'variables' => $variablesForHistory,
                'headers' => $headersForHistory,
                'status' => 'error',
                'error_summary' => $errorMessage,
                'duration_ms' => $durationMs,
            ]);

            return $result->setData([
                'success' => false,
                'error' => $errorMessage,
            ]);
        } finally {
            try {
                $durationMs = $durationMs > 0 ? $durationMs : (int) round((hrtime(true) - $startedAt) / 1_000_000);
                $this->auditLogger->log($endpointForHistory, $auditStatus, $durationMs);
            } catch (\Throwable) {
                // Keep execution response stable if audit insert fails.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $rawJson, string $label): array
    {
        $trimmed = trim($rawJson);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \InvalidArgumentException(
                (string) __('Invalid %1 JSON payload.', $label)
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $headerName = trim((string) $name);
            if ($headerName === '') {
                continue;
            }

            $headerValue = is_scalar($value) ? (string) $value : json_encode($value);
            $normalized[$headerName] = (string) $headerValue;
        }

        return $normalized;
    }

    private function isDeveloperMode(): bool
    {
        try {
            return $this->appState->getMode() === State::MODE_DEVELOPER;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isMutationOperation(string $query): bool
    {
        $normalized = ltrim($query);
        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, '{')) {
            return false;
        }

        return (bool) preg_match('/^mutation\b/i', $normalized);
    }
}
