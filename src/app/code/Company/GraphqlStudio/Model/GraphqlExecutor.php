<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;

class GraphqlExecutor
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly StudioConfig $studioConfig
    ) {
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, string> $headers
     * @return array{
     *   http_status:int,
     *   response_body:string,
     *   truncated:bool
     * }
     */
    public function execute(
        string $endpoint,
        string $query,
        array $variables,
        array $headers
    ): array {
        /** @var Curl $client */
        $client = $this->curlFactory->create();
        $client->setTimeout($this->studioConfig->getTimeoutSeconds());
        $client->setOption(CURLOPT_FOLLOWLOCATION, true);
        $client->setOption(CURLOPT_MAXREDIRS, 3);

        if ($this->isDevTlsRelaxed() && str_starts_with(strtolower($endpoint), 'https://')) {
            // Local Docker/dev SSL certs are often self-signed.
            $client->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $client->setOption(CURLOPT_SSL_VERIFYHOST, 0);
        }

        $client->addHeader('Content-Type', 'application/json');
        $client->addHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            if (!$this->isHeaderAllowed($name)) {
                continue;
            }
            $client->addHeader($name, $value);
        }

        $payload = [
            'query' => $query,
            'variables' => $variables,
        ];

        $client->post($endpoint, json_encode($payload, JSON_UNESCAPED_SLASHES));
        $responseBody = (string) $client->getBody();
        $maxResponseBytes = $this->studioConfig->getMaxResponseBytes();
        $isTruncated = strlen($responseBody) > $maxResponseBytes;
        if ($isTruncated) {
            $responseBody = substr($responseBody, 0, $maxResponseBytes);
        }

        return [
            'http_status' => (int) $client->getStatus(),
            'response_body' => $responseBody,
            'truncated' => $isTruncated,
        ];
    }

    private function isHeaderAllowed(string $name): bool
    {
        $normalized = strtolower(trim($name));
        return !in_array($normalized, ['host', 'content-length'], true);
    }

    private function isDevTlsRelaxed(): bool
    {
        return $this->studioConfig->isDeveloperMode();
    }
}
