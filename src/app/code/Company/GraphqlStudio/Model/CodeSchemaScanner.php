<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Module\ModuleListInterface;

class CodeSchemaScanner
{
    private const CACHE_KEY = 'company_graphqlstudio_code_schema_scan_v1';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly StudioConfig $studioConfig,
        private readonly ModuleListInterface $moduleList,
        private readonly ModuleDirReader $moduleDirReader
    ) {
    }

    /**
     * @return array{
     *   modules: array<int, array<string, mixed>>,
     *   total_modules: int,
     *   total_fields: int,
     *   cache_hit: bool
     * }
     */
    public function scan(bool $forceRefresh = false): array
    {
        if (!$forceRefresh) {
            $cached = $this->cache->load(self::CACHE_KEY);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    $decoded['cache_hit'] = true;
                    return $decoded;
                }
            }
        }

        $modules = [];
        $totalFields = 0;
        foreach ($this->moduleList->getNames() as $moduleName) {
            $schemaPath = $this->resolveSchemaPath($moduleName);
            if ($schemaPath === '' || !is_file($schemaPath)) {
                continue;
            }

            $contents = @file_get_contents($schemaPath);
            if (!is_string($contents) || trim($contents) === '') {
                continue;
            }

            $queries = $this->extractFields($contents, 'Query');
            $mutations = $this->extractFields($contents, 'Mutation');
            if (!$queries && !$mutations) {
                continue;
            }

            $totalFields += count($queries) + count($mutations);
            $modules[] = [
                'module' => $moduleName,
                'queries' => array_values($queries),
                'mutations' => array_values($mutations),
                'schema_file' => $schemaPath,
            ];
        }

        usort($modules, static fn(array $a, array $b): int => strcmp($a['module'], $b['module']));

        $payload = [
            'modules' => $modules,
            'total_modules' => count($modules),
            'total_fields' => $totalFields,
            'cache_hit' => false,
        ];

        $this->cache->save(
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
            self::CACHE_KEY,
            [],
            $this->studioConfig->getDocsCacheTtl()
        );

        return $payload;
    }

    private function resolveSchemaPath(string $moduleName): string
    {
        try {
            $etcDir = $this->moduleDirReader->getModuleDir(Dir::MODULE_ETC_DIR, $moduleName);
        } catch (\Throwable) {
            return '';
        }

        return rtrim($etcDir, '/') . '/schema.graphqls';
    }

    /**
     * @return array<string, array{name:string,description:string|null}>
     */
    private function extractFields(string $schema, string $typeName): array
    {
        $fields = [];
        $pattern = '/(?:extend\s+)?type\s+' . preg_quote($typeName, '/') . '\s*\{([\s\S]*?)\}/m';
        if (!preg_match_all($pattern, $schema, $matches)) {
            return $fields;
        }

        foreach ($matches[1] as $block) {
            $pendingDescription = null;
            $descriptionLines = [];
            $inTripleQuote = false;
            $tripleBuffer = [];

            foreach (preg_split('/\R/', (string) $block) as $line) {
                $trimmed = trim((string) $line);
                if ($trimmed === '') {
                    continue;
                }

                if ($inTripleQuote) {
                    if (str_contains($trimmed, '"""')) {
                        $before = strstr($trimmed, '"""', true);
                        if (is_string($before) && $before !== '') {
                            $tripleBuffer[] = trim($before);
                        }
                        $pendingDescription = trim(implode(' ', array_filter($tripleBuffer)));
                        $inTripleQuote = false;
                        $tripleBuffer = [];
                    } else {
                        $tripleBuffer[] = $trimmed;
                    }
                    continue;
                }

                if (str_starts_with($trimmed, '"""')) {
                    $rest = substr($trimmed, 3);
                    if (str_contains($rest, '"""')) {
                        $singleLine = strstr($rest, '"""', true);
                        $pendingDescription = trim((string) $singleLine);
                    } else {
                        if ($rest !== '') {
                            $tripleBuffer[] = trim($rest);
                        }
                        $inTripleQuote = true;
                    }
                    continue;
                }

                if (str_starts_with($trimmed, '#')) {
                    $descriptionLines[] = ltrim(substr($trimmed, 1));
                    continue;
                }

                if ($descriptionLines) {
                    $pendingDescription = trim(implode(' ', $descriptionLines));
                    $descriptionLines = [];
                }

                if (!preg_match('/^([_A-Za-z][_0-9A-Za-z]*)\s*(?:\(|:)/', $trimmed, $fieldMatch)) {
                    continue;
                }

                $fieldName = $fieldMatch[1];
                if (isset($fields[$fieldName])) {
                    $pendingDescription = null;
                    continue;
                }

                $fields[$fieldName] = [
                    'name' => $fieldName,
                    'description' => $pendingDescription !== '' ? $pendingDescription : null,
                ];
                $pendingDescription = null;
            }
        }

        return $fields;
    }
}
