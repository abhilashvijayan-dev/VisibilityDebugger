/**
 * Copyright © Company. All rights reserved.
 * Docs sidebar from GraphQL introspection endpoint.
 */
define(['jquery'], function ($) {
    'use strict';

    var STORAGE_KEY = 'company_graphqlstudio_docs_endpoint';

    function stripTypeWrappers(typeName) {
        return (typeName || '').replace(/[!\[\]\s]/g, '');
    }

    function isListType(typeName) {
        return (typeName || '').indexOf('[') !== -1;
    }

    function sampleScalarValue(fieldName, typeName) {
        var name = (fieldName || '').toLowerCase();
        var baseType = stripTypeWrappers(typeName);
        if (name.indexOf('email') !== -1) {
            return 'customer@example.com';
        }
        if (name.indexOf('password') !== -1) {
            return 'REPLACE_WITH_PASSWORD';
        }
        if (name === 'cart_id' || name.indexOf('cartid') !== -1) {
            return 'REPLACE_CART_ID';
        }
        if (name === 'sku') {
            return 'REPLACE_SKU';
        }
        if (name === 'uid' || name.endsWith('_id') || name === 'id') {
            return 'REPLACE_ID';
        }
        if (name.indexOf('qty') !== -1 || name.indexOf('quantity') !== -1) {
            return 1;
        }

        if (baseType === 'Int' || baseType === 'Float') {
            return 1;
        }
        if (baseType === 'Boolean') {
            return false;
        }
        return 'REPLACE_ME';
    }

    function buildInputObjectTemplate(typeName, inputObjectFields, depth) {
        var baseType = stripTypeWrappers(typeName);
        var fields = inputObjectFields[baseType];
        if (!fields || depth > 2) {
            return {};
        }

        var obj = {};
        fields.forEach(function (field) {
            var fieldType = field.type || '';
            var nestedBase = stripTypeWrappers(fieldType);
            var nestedInput = inputObjectFields[nestedBase];
            if (nestedInput) {
                var nested = buildInputObjectTemplate(fieldType, inputObjectFields, depth + 1);
                obj[field.name] = isListType(fieldType) ? [nested] : nested;
                return;
            }

            if (field.enum_values && field.enum_values.length) {
                obj[field.name] = isListType(fieldType) ? [field.enum_values[0]] : field.enum_values[0];
                return;
            }

            var scalarSample = sampleScalarValue(field.name, fieldType);
            obj[field.name] = isListType(fieldType) ? [scalarSample] : scalarSample;
        });
        return obj;
    }

    function buildVariablesTemplate(operation, inputObjectFields) {
        var args = operation.args || [];
        var payload = {};
        args.forEach(function (arg) {
            var argType = arg.type || '';
            var baseType = stripTypeWrappers(argType);

            if (inputObjectFields[baseType]) {
                payload[arg.name] = buildInputObjectTemplate(argType, inputObjectFields, 0);
                return;
            }

            if (arg.enum_values && arg.enum_values.length) {
                payload[arg.name] = arg.enum_values[0];
                return;
            }

            payload[arg.name] = sampleScalarValue(arg.name, argType);
        });

        return payload;
    }

    function buildHeadersTemplate() {
        return {
            'Content-Type': 'application/json'
        };
    }

    function buildSelectionSet(typeName, outputObjectFields, depth, visited) {
        var fields = outputObjectFields[typeName] || [];
        var scalarLines = [];
        var objectLines = [];
        var nextVisited = $.extend({}, visited || {});
        var maxFields = depth === 0 ? 8 : 5;

        if (!typeName || nextVisited[typeName]) {
            return ['__typename'];
        }
        nextVisited[typeName] = true;

        fields.forEach(function (field) {
            if ((scalarLines.length + objectLines.length) >= maxFields || field.has_required_args) {
                return;
            }

            if (field.base_kind === 'SCALAR' || field.base_kind === 'ENUM') {
                scalarLines.push(field.name);
                return;
            }

            if ((field.base_kind === 'OBJECT' || field.base_kind === 'INTERFACE') && depth < 1) {
                var nested = buildSelectionSet(field.base_type, outputObjectFields, depth + 1, nextVisited);
                objectLines.push(field.name + ' {\n' + nested.map(function (line) {
                    return '  ' + line;
                }).join('\n') + '\n}');
            }
        });

        if (!scalarLines.length && !objectLines.length) {
            return ['__typename'];
        }

        return scalarLines.concat(objectLines);
    }

    function toTemplate(operation, kind, outputObjectFields) {
        var args = operation.args || [];
        var hasArgs = args.length > 0;

        var varDefs = hasArgs ? '(' + args.map(function (arg) {
            return '$' + arg.name + ': ' + arg.type;
        }).join(', ') + ')' : '';

        var argValues = hasArgs ? '(' + args.map(function (arg) {
            return arg.name + ': $' + arg.name;
        }).join(', ') + ')' : '';

        var needsSelection = ['OBJECT', 'INTERFACE', 'UNION'].indexOf(operation.return_kind) !== -1;
        var opKeyword = kind === 'mutation' ? 'mutation' : 'query';
        var opName = operation.name || (kind === 'mutation' ? 'ExampleMutation' : 'ExampleQuery');

        if (needsSelection) {
            var selectionLines = operation.return_kind === 'UNION'
                ? ['__typename']
                : buildSelectionSet(operation.return_name || '', outputObjectFields || {}, 0, {});
            return [
                opKeyword + ' ' + opName + varDefs + ' {',
                '  ' + opName + argValues + ' {',
                selectionLines.map(function (line) {
                    return '    ' + line;
                }).join('\n'),
                '  }',
                '}'
            ].join('\n');
        }

        return [
            opKeyword + ' ' + opName + varDefs + ' {',
            '  ' + opName + argValues,
            '}'
        ].join('\n');
    }

    return function createDocsManager(config) {
        var $root = config.root;
        var $endpointInput = $root.find('[data-role="docs-endpoint-input"]');
        var $endpointSave = $root.find('[data-role="docs-endpoint-save"]');
        var $endpointNote = $root.find('[data-role="docs-endpoint-note"]');
        var $search = $root.find('[data-role="docs-search"]');
        var $list = $root.find('[data-role="docs-list"]');
        var $empty = $root.find('[data-role="docs-empty"]');
        var $codeList = $root.find('[data-role="code-docs-list"]');
        var $codeEmpty = $root.find('[data-role="code-docs-empty"]');
        var $codeRefresh = $root.find('[data-role="code-docs-refresh"]');
        var docs = { queries: [], mutations: [], input_object_fields: {}, output_object_fields: {}, enum_types: {} };
        var codeDocs = [];

        function getSavedEndpoint() {
            try {
                return window.localStorage.getItem(STORAGE_KEY) || '';
            } catch (e) {
                return '';
            }
        }

        function saveEndpointLocal(value) {
            try {
                window.localStorage.setItem(STORAGE_KEY, value);
                return true;
            } catch (e) {
                return false;
            }
        }

        function getCurrentEndpoint() {
            return ($endpointInput.val() || config.docsDefaultEndpoint || '').toString().trim();
        }

        function setEndpointNote(text, isError) {
            $endpointNote
                .toggleClass('is-error', !!isError)
                .text(text || '');
        }

        function setEmpty(text, show) {
            if (typeof text === 'string') {
                $empty.text(text);
            }
            $empty.toggle(!!show);
        }

        function buildOpRow(kind, op) {
            var badge = kind === 'mutation' ? 'M' : 'Q';
            var returnType = op.return_type || 'Unknown';
            return [
                '<button type="button" class="company-graphql-studio__docs-item" data-role="docs-op" data-kind="' + kind + '" data-name="' + op.name + '">',
                '  <span class="company-graphql-studio__docs-badge">' + badge + '</span>',
                '  <span class="company-graphql-studio__docs-name">' + op.name + '</span>',
                '  <span class="company-graphql-studio__docs-return">' + returnType + '</span>',
                '</button>'
            ].join('');
        }

        function render() {
            var query = ($search.val() || '').toString().trim().toLowerCase();
            var html = '';
            var operations = [];

            docs.queries.forEach(function (op) {
                operations.push({ kind: 'query', op: op });
            });
            docs.mutations.forEach(function (op) {
                operations.push({ kind: 'mutation', op: op });
            });

            if (query) {
                operations = operations.filter(function (item) {
                    return (item.op.name || '').toLowerCase().indexOf(query) !== -1;
                });
            }

            operations.forEach(function (item) {
                html += buildOpRow(item.kind, item.op);
            });

            $list.html(html);
            setEmpty(query ? 'No matching operations.' : 'No docs operations available.', operations.length === 0);
            renderCodeDocs(query);
        }

        function setCodeEmpty(text, show) {
            if (typeof text === 'string') {
                $codeEmpty.text(text);
            }
            $codeEmpty.toggle(!!show);
        }

        function buildCodeFieldItem(moduleName, kind, field) {
            var badge = kind === 'mutation' ? 'M' : 'Q';
            var desc = field.description ? '<div class="company-graphql-studio__code-docs-desc">' + field.description + '</div>' : '';
            return [
                '<button type="button" class="company-graphql-studio__code-docs-item" data-role="code-docs-op" data-kind="' + kind + '" data-name="' + field.name + '" data-module="' + moduleName + '">',
                '  <span class="company-graphql-studio__docs-badge">' + badge + '</span>',
                '  <span class="company-graphql-studio__code-docs-name">' + field.name + '</span>',
                '  <span class="company-graphql-studio__code-docs-module">' + moduleName + '</span>',
                desc,
                '</button>'
            ].join('');
        }

        function buildCodeTemplate(kind, fieldName) {
            var opKeyword = kind === 'mutation' ? 'mutation' : 'query';
            var opName = fieldName || (kind === 'mutation' ? 'ExampleMutation' : 'ExampleQuery');
            return [
                opKeyword + ' ' + opName + ' {',
                '  ' + opName + ' {',
                '    __typename',
                '  }',
                '}'
            ].join('\n');
        }

        function renderCodeDocs(query) {
            var html = '';
            var count = 0;
            var search = (query || '').toLowerCase();

            codeDocs.forEach(function (moduleItem) {
                var moduleHtml = '';
                (moduleItem.queries || []).forEach(function (field) {
                    if (search && field.name.toLowerCase().indexOf(search) === -1) {
                        return;
                    }
                    moduleHtml += buildCodeFieldItem(moduleItem.module, 'query', field);
                    count += 1;
                });
                (moduleItem.mutations || []).forEach(function (field) {
                    if (search && field.name.toLowerCase().indexOf(search) === -1) {
                        return;
                    }
                    moduleHtml += buildCodeFieldItem(moduleItem.module, 'mutation', field);
                    count += 1;
                });

                if (moduleHtml) {
                    html += '<div class="company-graphql-studio__code-docs-module-group">';
                    html += '<div class="company-graphql-studio__code-docs-module-title">' + moduleItem.module + '</div>';
                    html += moduleHtml;
                    html += '</div>';
                }
            });

            $codeList.html(html);
            setCodeEmpty(search ? 'No matching fields from code.' : 'No Query/Mutation fields found in schema.graphqls files.', count === 0);
        }

        function loadCodeDocs(forceRefresh) {
            setCodeEmpty('Scanning module schema files...', true);
            $codeList.empty();

            return $.ajax({
                url: config.codeDocsUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    refresh: forceRefresh ? 1 : 0
                }
            }).done(function (res) {
                if (!res || !res.success) {
                    setCodeEmpty((res && res.error) || 'Unable to scan schema files.', true);
                    return;
                }

                codeDocs = res.modules || [];
                renderCodeDocs(($search.val() || '').toString().trim());
            }).fail(function () {
                setCodeEmpty('Unable to scan schema files.', true);
            });
        }

        function load() {
            setEmpty('Docs are loading...', true);
            $list.empty();

            return $.ajax({
                url: config.docsUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    store_code: config.storeCode,
                    endpoint: getCurrentEndpoint()
                }
            }).done(function (res) {
                if (!res || !res.success || !res.docs) {
                    setEmpty((res && res.error) || 'Unable to load docs.', true);
                    return;
                }

                docs = {
                    queries: res.docs.queries || [],
                    mutations: res.docs.mutations || [],
                    input_object_fields: res.docs.input_object_fields || {},
                    output_object_fields: res.docs.output_object_fields || {},
                    enum_types: res.docs.enum_types || {}
                };
                render();
            }).fail(function () {
                setEmpty('Unable to load docs.', true);
            });
        }

        $search.on('input', function () {
            render();
        });

        $codeRefresh.on('click', function () {
            loadCodeDocs(true);
        });

        $endpointSave.on('click', function () {
            var endpoint = getCurrentEndpoint();
            if (!/^https?:\/\//i.test(endpoint)) {
                setEndpointNote('Enter a valid endpoint URL starting with http:// or https://', true);
                return;
            }

            $.ajax({
                url: config.docsEndpointSaveUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: config.formKey,
                    endpoint: endpoint
                }
            }).done(function (res) {
                if (!res || !res.success) {
                    setEndpointNote((res && res.error) || 'Unable to save docs endpoint.', true);
                    return;
                }

                saveEndpointLocal(endpoint);
                setEndpointNote('Docs endpoint saved.', false);
                load();
            }).fail(function () {
                if (saveEndpointLocal(endpoint)) {
                    setEndpointNote('Saved locally only (server save failed).', true);
                    load();
                    return;
                }
                setEndpointNote('Unable to save endpoint.', true);
            });
        });

        $root.on('click', '[data-role="docs-op"]', function () {
            var $button = $(this);
            var kind = $button.data('kind');
            var name = $button.data('name');

            var source = kind === 'mutation' ? docs.mutations : docs.queries;
            var op = source.find(function (candidate) {
                return candidate.name === name;
            });
            if (!op) {
                return;
            }

            config.setQuery(toTemplate(op, kind, docs.output_object_fields || {}));
            var variablePayload = buildVariablesTemplate(op, docs.input_object_fields || {});
            config.setVariables(JSON.stringify(variablePayload, null, 2));
            config.setHeaders(JSON.stringify(buildHeadersTemplate(), null, 2));
        });

        $root.on('click', '[data-role="code-docs-op"]', function () {
            var $button = $(this);
            config.setQuery(buildCodeTemplate($button.data('kind'), $button.data('name')));
            config.setVariables(JSON.stringify({}, null, 2));
            config.setHeaders(JSON.stringify(buildHeadersTemplate(), null, 2));
        });

        return {
            refresh: function () {
                var serverSaved = (config.docsSavedEndpoint || '').toString().trim();
                var localSaved = getSavedEndpoint();
                if (serverSaved) {
                    $endpointInput.val(serverSaved);
                    saveEndpointLocal(serverSaved);
                    setEndpointNote('Using saved docs endpoint.', false);
                } else if (localSaved) {
                    $endpointInput.val(localSaved);
                    setEndpointNote('Using saved docs endpoint.', false);
                } else {
                    $endpointInput.val(config.docsDefaultEndpoint || '');
                    setEndpointNote('Defaulted to web URL GraphQL endpoint.', false);
                }

                return $.ajax({
                    url: config.docsEndpointLoadUrl,
                    type: 'GET',
                    dataType: 'json'
                }).done(function (res) {
                    if (res && res.success && res.endpoint) {
                        $endpointInput.val(res.endpoint);
                        saveEndpointLocal(res.endpoint);
                        setEndpointNote('Using saved docs endpoint.', false);
                    }
                }).always(function () {
                    load();
                    loadCodeDocs(false);
                });
            }
        };
    };
});
