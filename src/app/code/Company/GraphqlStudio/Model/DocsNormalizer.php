<?php
/**
 * Copyright © Company. All rights reserved.
 */

declare(strict_types=1);

namespace Company\GraphqlStudio\Model;

class DocsNormalizer
{
    /**
     * @param array<string, mixed> $schema
     * @return array{
     *   queries: array<int, array<string, mixed>>,
     *   mutations: array<int, array<string, mixed>>,
     *   input_types: array<int, string>,
     *   object_types: array<int, string>,
     *   input_object_fields: array<string, array<int, array{name:string,type:string,base_type:string,enum_values:array<int,string>}>>,
     *   output_object_fields: array<string, array<int, array{name:string,type:string,base_type:string,base_kind:string,has_required_args:bool}>>,
     *   enum_types: array<string, array<int, string>>
     * }
     */
    public function normalize(array $schema): array
    {
        $types = is_array($schema['types'] ?? null) ? $schema['types'] : [];
        $typeMap = $this->buildTypeMap($types);
        $enumMap = $this->extractEnumMap($types);

        $queryTypeName = (string) (($schema['queryType']['name'] ?? '') ?: '');
        $mutationTypeName = (string) (($schema['mutationType']['name'] ?? '') ?: '');

        return [
            'queries' => $this->extractOperations($queryTypeName, $typeMap, $enumMap),
            'mutations' => $this->extractOperations($mutationTypeName, $typeMap, $enumMap),
            'input_types' => $this->extractTypeNames($types, 'INPUT_OBJECT'),
            'object_types' => $this->extractTypeNames($types, 'OBJECT'),
            'input_object_fields' => $this->extractInputObjectFields($typeMap, $enumMap),
            'output_object_fields' => $this->extractOutputObjectFields($typeMap),
            'enum_types' => $enumMap,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $types
     * @return array<string, array<string, mixed>>
     */
    private function buildTypeMap(array $types): array
    {
        $map = [];
        foreach ($types as $type) {
            $name = (string) ($type['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $map[$name] = $type;
        }
        return $map;
    }

    /**
     * @param array<string, array<string, mixed>> $typeMap
     * @param array<string, array<int, string>> $enumMap
     * @return array<int, array<string, mixed>>
     */
    private function extractOperations(string $rootTypeName, array $typeMap, array $enumMap): array
    {
        if ($rootTypeName === '' || !isset($typeMap[$rootTypeName])) {
            return [];
        }

        $fields = is_array($typeMap[$rootTypeName]['fields'] ?? null) ? $typeMap[$rootTypeName]['fields'] : [];
        $result = [];
        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $args = [];
            foreach ((array) ($field['args'] ?? []) as $arg) {
                $typeRef = (array) ($arg['type'] ?? []);
                $argType = $this->typeRefToString($typeRef);
                $baseType = $this->unwrapTypeRef($typeRef);
                $args[] = [
                    'name' => (string) ($arg['name'] ?? ''),
                    'type' => $argType,
                    'base_type' => (string) ($baseType['name'] ?? ''),
                    'enum_values' => $enumMap[(string) ($baseType['name'] ?? '')] ?? [],
                ];
            }

            $returnType = $this->unwrapTypeRef((array) ($field['type'] ?? []));
            $result[] = [
                'name' => $name,
                'args' => $args,
                'return_type' => $this->typeRefToString((array) ($field['type'] ?? [])),
                'return_kind' => (string) ($returnType['kind'] ?? ''),
                'return_name' => (string) ($returnType['name'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $types
     * @return array<int, string>
     */
    private function extractTypeNames(array $types, string $kind): array
    {
        $names = [];
        foreach ($types as $type) {
            $typeKind = (string) ($type['kind'] ?? '');
            $name = (string) ($type['name'] ?? '');
            if ($typeKind !== $kind || $name === '' || str_starts_with($name, '__')) {
                continue;
            }
            $names[] = $name;
        }
        sort($names);
        return $names;
    }

    /**
     * @param array<string, array<string, mixed>> $typeMap
     * @param array<string, array<int, string>> $enumMap
     * @return array<string, array<int, array{name:string,type:string,base_type:string,enum_values:array<int,string>}>>
     */
    private function extractInputObjectFields(array $typeMap, array $enumMap): array
    {
        $result = [];
        foreach ($typeMap as $typeName => $typeDef) {
            if ((string) ($typeDef['kind'] ?? '') !== 'INPUT_OBJECT') {
                continue;
            }

            $fields = is_array($typeDef['inputFields'] ?? null) ? $typeDef['inputFields'] : [];
            $normalizedFields = [];
            foreach ($fields as $field) {
                $fieldName = (string) ($field['name'] ?? '');
                if ($fieldName === '') {
                    continue;
                }

                $normalizedFields[] = [
                    'name' => $fieldName,
                    'type' => $this->typeRefToString((array) ($field['type'] ?? [])),
                    'base_type' => (string) ($this->unwrapTypeRef((array) ($field['type'] ?? []))['name'] ?? ''),
                    'enum_values' => $enumMap[(string) ($this->unwrapTypeRef((array) ($field['type'] ?? []))['name'] ?? '')] ?? [],
                ];
            }

            $result[$typeName] = $normalizedFields;
        }

        ksort($result);
        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $typeMap
     * @return array<string, array<int, array{name:string,type:string,base_type:string,base_kind:string,has_required_args:bool}>>
     */
    private function extractOutputObjectFields(array $typeMap): array
    {
        $result = [];
        foreach ($typeMap as $typeName => $typeDef) {
            $kind = (string) ($typeDef['kind'] ?? '');
            if (!in_array($kind, ['OBJECT', 'INTERFACE'], true) || str_starts_with($typeName, '__')) {
                continue;
            }

            $fields = is_array($typeDef['fields'] ?? null) ? $typeDef['fields'] : [];
            $normalizedFields = [];
            foreach ($fields as $field) {
                $fieldName = (string) ($field['name'] ?? '');
                if ($fieldName === '' || str_starts_with($fieldName, '__')) {
                    continue;
                }

                $typeRef = (array) ($field['type'] ?? []);
                $baseType = $this->unwrapTypeRef($typeRef);
                $hasRequiredArgs = false;
                foreach ((array) ($field['args'] ?? []) as $arg) {
                    $argType = $this->typeRefToString((array) ($arg['type'] ?? []));
                    if (str_ends_with($argType, '!')) {
                        $hasRequiredArgs = true;
                        break;
                    }
                }

                $normalizedFields[] = [
                    'name' => $fieldName,
                    'type' => $this->typeRefToString($typeRef),
                    'base_type' => (string) ($baseType['name'] ?? ''),
                    'base_kind' => (string) ($baseType['kind'] ?? ''),
                    'has_required_args' => $hasRequiredArgs,
                ];
            }

            $result[$typeName] = $normalizedFields;
        }

        ksort($result);
        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $types
     * @return array<string, array<int, string>>
     */
    private function extractEnumMap(array $types): array
    {
        $result = [];
        foreach ($types as $type) {
            if ((string) ($type['kind'] ?? '') !== 'ENUM') {
                continue;
            }

            $name = (string) ($type['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $values = [];
            foreach ((array) ($type['enumValues'] ?? []) as $enumValue) {
                $valueName = (string) ($enumValue['name'] ?? '');
                if ($valueName !== '') {
                    $values[] = $valueName;
                }
            }

            if ($values) {
                $result[$name] = $values;
            }
        }

        ksort($result);
        return $result;
    }

    /**
     * @param array<string, mixed> $typeRef
     */
    private function typeRefToString(array $typeRef): string
    {
        $kind = (string) ($typeRef['kind'] ?? '');
        if ($kind === 'NON_NULL') {
            return $this->typeRefToString((array) ($typeRef['ofType'] ?? [])) . '!';
        }
        if ($kind === 'LIST') {
            return '[' . $this->typeRefToString((array) ($typeRef['ofType'] ?? [])) . ']';
        }

        return (string) (($typeRef['name'] ?? '') ?: 'Unknown');
    }

    /**
     * @param array<string, mixed> $typeRef
     * @return array{kind:string,name:string}
     */
    private function unwrapTypeRef(array $typeRef): array
    {
        $current = $typeRef;
        while (in_array((string) ($current['kind'] ?? ''), ['NON_NULL', 'LIST'], true)) {
            $current = (array) ($current['ofType'] ?? []);
        }

        return [
            'kind' => (string) ($current['kind'] ?? ''),
            'name' => (string) ($current['name'] ?? ''),
        ];
    }
}
