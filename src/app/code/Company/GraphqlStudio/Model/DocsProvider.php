<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

use Magento\Framework\App\CacheInterface;

class DocsProvider
{
    private const CACHE_PREFIX = 'company_graphqlstudio_docs_v2_';

    private const INTROSPECTION_QUERY = <<<'GRAPHQL'
query IntrospectionQuery {
  __schema {
    queryType { name }
    mutationType { name }
    types {
      kind
      name
      enumValues(includeDeprecated: true) {
        name
      }
      fields(includeDeprecated: true) {
        name
        args {
          name
          type {
            kind
            name
            ofType {
              kind
              name
              ofType {
                kind
                name
                ofType {
                  kind
                  name
                }
              }
            }
          }
        }
        type {
          kind
          name
          ofType {
            kind
            name
            ofType {
              kind
              name
              ofType {
                kind
                name
              }
            }
          }
        }
      }
      inputFields {
        name
        type {
          kind
          name
          ofType {
            kind
            name
            ofType {
              kind
              name
              ofType {
                kind
                name
              }
            }
          }
        }
      }
    }
  }
}
GRAPHQL;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly EndpointResolver $endpointResolver,
        private readonly GraphqlExecutor $graphqlExecutor,
        private readonly DocsNormalizer $normalizer,
        private readonly StudioConfig $studioConfig
    ) {
    }

    /**
     * @return array{
     *   endpoint_used: string,
     *   data: array<string, mixed>,
     *   cache_hit: bool
     * }
     */
    public function getDocs(string $storeCode, ?string $requestedEndpoint = null): array
    {
        $endpoint = $this->endpointResolver->resolveEndpoint($requestedEndpoint);
        $cacheKey = $this->buildCacheKey($storeCode, $endpoint);
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return [
                    'endpoint_used' => $endpoint,
                    'data' => $decoded,
                    'cache_hit' => true,
                ];
            }
        }

        $execution = $this->graphqlExecutor->execute(
            $endpoint,
            self::INTROSPECTION_QUERY,
            [],
            ['Store' => $storeCode]
        );

        if ($execution['truncated']) {
            throw new \RuntimeException(
                sprintf('Introspection response exceeded %d bytes.', $this->studioConfig->getMaxResponseBytes())
            );
        }

        $decoded = json_decode($execution['response_body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid introspection JSON response.');
        }

        if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
            $firstError = (string) ($decoded['errors'][0]['message'] ?? 'GraphQL introspection failed.');
            throw new \RuntimeException($firstError);
        }

        $schema = (array) ($decoded['data']['__schema'] ?? []);
        if (!$schema) {
            throw new \RuntimeException('Introspection schema payload missing.');
        }

        $simplified = $this->normalizer->normalize($schema);
        $this->cache->save(
            (string) json_encode($simplified, JSON_UNESCAPED_SLASHES),
            $cacheKey,
            [],
            $this->studioConfig->getDocsCacheTtl()
        );

        return [
            'endpoint_used' => $endpoint,
            'data' => $simplified,
            'cache_hit' => false,
        ];
    }

    private function buildCacheKey(string $storeCode, string $endpoint): string
    {
        return self::CACHE_PREFIX . hash('sha256', $storeCode . '|' . $endpoint);
    }
}
