/**
 * Copyright © Company. All rights reserved.
 * GraphQL Studio admin page bootstrap.
 */
define([
    'jquery',
    'Company_GraphqlStudio/js/studio/editor',
    'Company_GraphqlStudio/js/studio/tabs',
    'Company_GraphqlStudio/js/studio/history',
    'Company_GraphqlStudio/js/studio/docs'
], function ($, createEditor, bindTabs, createHistoryManager, createDocsManager) {
    'use strict';

    function prettyPrintJson(value) {
        return JSON.stringify(value, null, 2);
    }

    function parseJsonSafe(rawValue) {
        var trimmed = (rawValue || '').trim();
        if (!trimmed) {
            return {};
        }
        return JSON.parse(trimmed);
    }

    function showMessage($messages, type, text) {
        $messages
            .removeClass('message-success message-error message-warning')
            .addClass('message-' + type)
            .text(text || '')
            .toggle(!!text);
    }

    function classifyAuthRequired(output) {
        var errors = output && output.errors ? output.errors : [];
        if (!Array.isArray(errors) || !errors.length) {
            return false;
        }

        return errors.some(function (error) {
            var message = ((error && error.message) || '').toString().toLowerCase();
            var category = (((error && error.extensions && error.extensions.category) || '') + '').toLowerCase();
            return (
                message.indexOf('customer token') !== -1 ||
                message.indexOf('current customer') !== -1 ||
                message.indexOf('authorization') !== -1 ||
                message.indexOf('bearer') !== -1 ||
                message.indexOf('authenticate') !== -1 ||
                category.indexOf('authorization') !== -1
            );
        });
    }

    function extractCustomerToken(output) {
        if (!output || !output.data || typeof output.data !== 'object') {
            return '';
        }

        var node = output.data.generateCustomerToken;
        if (!node) {
            return '';
        }

        if (typeof node === 'string') {
            return node.trim();
        }

        if (typeof node === 'object' && typeof node.token === 'string') {
            return node.token.trim();
        }

        return '';
    }

    return function (config) {
        var $root = $(config.rootSelector);
        if (!$root.length) {
            return;
        }

        var $studio = $root.find('.company-graphql-studio');
        var $runButton = $root.find('[data-role="run-query"]');
        var $endpoint = $root.find('[data-role="endpoint-input"]');
        var $variables = $root.find('[data-role="variables-editor"]');
        var $headers = $root.find('[data-role="headers-editor"]');
        var $response = $root.find('[data-role="response-panel"]');
        var $messages = $root.find('[data-role="messages"]');
        var $authBanner = $root.find('[data-role="auth-required-banner"]');
        var $tokenInsert = $root.find('[data-role="token-helper-insert"]');
        var $tokenCopy = $root.find('[data-role="token-helper-copy"]');
        var $tokenUse = $root.find('[data-role="token-helper-use"]');
        var $tokenClear = $root.find('[data-role="token-helper-clear"]');
        var $tokenNote = $root.find('[data-role="token-helper-note"]');
        var currentCustomerToken = '';

        var queryEditor = createEditor($root.find('[data-role="query-editor"]'));
        bindTabs($root.find('[data-role="request-tabs"]'));
        bindTabs($root.find('[data-role="sidebar-tabs"]'));

        $studio.removeClass('company-graphql-studio--loading');
        $response.text('{\n  "info": "Ready. Click Run to execute against GraphQL endpoint."\n}');
        $authBanner.hide();
        if (!config.allowEndpointOverride) {
            $endpoint.val(config.defaultEndpoint || $endpoint.val());
        }

        function setTokenButtonsVisible(visible) {
            $tokenCopy.toggle(visible);
            $tokenUse.toggle(visible);
        }

        function setTokenNote(text) {
            $tokenNote.text(text || '');
        }

        function upsertAuthorizationHeader(token) {
            var parsedHeaders;
            try {
                parsedHeaders = parseJsonSafe($headers.val());
            } catch (e) {
                parsedHeaders = {};
            }

            if (!token) {
                delete parsedHeaders.Authorization;
                delete parsedHeaders.authorization;
            } else {
                parsedHeaders.Authorization = 'Bearer ' + token;
            }

            $headers.val(prettyPrintJson(parsedHeaders));
        }

        $tokenInsert.on('click', function () {
            queryEditor.setValue(
                'mutation GenerateCustomerToken($email: String!, $password: String!) {\n' +
                '  generateCustomerToken(email: $email, password: $password) {\n' +
                '    token\n' +
                '  }\n' +
                '}'
            );
            $variables.val(prettyPrintJson({
                email: 'customer@example.com',
                password: 'REPLACE_WITH_PASSWORD'
            }));
            try {
                var currentHeaders = parseJsonSafe($headers.val());
                if (!currentHeaders['Content-Type'] && !currentHeaders['content-type']) {
                    currentHeaders['Content-Type'] = 'application/json';
                }
                $headers.val(prettyPrintJson(currentHeaders));
            } catch (e) {
                $headers.val(prettyPrintJson({
                    'Content-Type': 'application/json'
                }));
            }
            setTokenNote('Token helper inserted. Fill credentials and run.');
        });

        $tokenCopy.on('click', function () {
            if (!currentCustomerToken) {
                setTokenNote('No token available to copy.');
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(currentCustomerToken).then(function () {
                    setTokenNote('Token copied to clipboard.');
                }).catch(function () {
                    setTokenNote('Unable to copy token automatically.');
                });
                return;
            }

            setTokenNote('Clipboard API unavailable. Copy token from response.');
        });

        $tokenUse.on('click', function () {
            if (!currentCustomerToken) {
                setTokenNote('No token available to apply.');
                return;
            }

            upsertAuthorizationHeader(currentCustomerToken);
            setTokenNote('Authorization header added.');
        });

        $tokenClear.on('click', function () {
            currentCustomerToken = '';
            upsertAuthorizationHeader('');
            setTokenButtonsVisible(false);
            setTokenNote('Authorization header removed.');
        });

        var history = createHistoryManager({
            root: $root,
            historyListUrl: config.historyListUrl,
            historyViewUrl: config.historyViewUrl,
            historyStarUrl: config.historyStarUrl,
            defaultEndpoint: config.defaultEndpoint,
            formKey: config.formKey,
            setEndpoint: function (value) {
                $endpoint.val(value || config.defaultEndpoint || '');
            },
            setQuery: function (value) {
                queryEditor.setValue(value || '');
            },
            setVariables: function (value) {
                $variables.val(value || '{}');
            },
            setHeaders: function (value) {
                $headers.val(value || '{}');
            }
        });
        history.refresh();

        var docs = createDocsManager({
            root: $root,
            docsUrl: config.docsUrl,
            codeDocsUrl: config.codeDocsUrl,
            storeCode: config.storeCode,
            docsDefaultEndpoint: config.docsDefaultEndpoint || config.defaultEndpoint || '',
            docsEndpointLoadUrl: config.docsEndpointLoadUrl,
            docsEndpointSaveUrl: config.docsEndpointSaveUrl,
            formKey: config.formKey,
            setQuery: function (value) {
                queryEditor.setValue(value || '');
            },
            setVariables: function (value) {
                $variables.val(value || '{}');
            },
            setHeaders: function (value) {
                $headers.val(value || '{}');
            }
        });
        docs.refresh();

        $runButton.on('click', function () {
            $runButton.prop('disabled', true);
            showMessage($messages, 'success', '');
            $authBanner.hide();

            try {
                parseJsonSafe($variables.val());
                parseJsonSafe($headers.val());
            } catch (error) {
                $response.text(prettyPrintJson({
                    errors: [
                        {
                            message: error.message || 'Invalid variables/headers JSON.'
                        }
                    ]
                }));
                showMessage($messages, 'error', 'Fix invalid Variables/Headers JSON.');
                $runButton.prop('disabled', false);
                return;
            }

            var endpointValue = config.allowEndpointOverride ? ($endpoint.val() || '') : (config.defaultEndpoint || '');
            $.ajax({
                url: config.executeUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: config.formKey,
                    endpoint: endpointValue,
                    query: queryEditor.getValue(),
                    variables: $variables.val(),
                    headers: $headers.val(),
                    store_code: config.storeCode
                }
            }).done(function (data) {
                if (!data || !data.success) {
                    var backendError = data && data.error ? data.error : 'Execution failed.';
                    $response.text(prettyPrintJson({
                        errors: [
                            { message: backendError }
                        ]
                    }));
                    showMessage($messages, 'error', backendError);
                    return;
                }

                var output;
                if (data.response_json !== null && typeof data.response_json !== 'undefined') {
                    output = data.response_json;
                } else {
                    output = {
                        data: null,
                        errors: [
                            { message: 'Non-JSON response returned by endpoint.' }
                        ],
                        raw: data.response_text || ''
                    };
                }

                $response.text(prettyPrintJson(output));
                $authBanner.toggle(classifyAuthRequired(output));

                var token = extractCustomerToken(output);
                if (token) {
                    currentCustomerToken = token;
                    setTokenButtonsVisible(true);
                    setTokenNote('Customer token received. Use or copy it.');
                }

                if (output.errors && output.errors.length) {
                    showMessage($messages, 'warning', 'Completed with GraphQL errors (' + output.errors.length + ').');
                } else {
                    showMessage(
                        $messages,
                        'success',
                        'Completed in ' + (data.duration_ms || 0) + ' ms (HTTP ' + (data.http_status || 0) + ').'
                    );
                }
                history.refresh();
                docs.refresh();
            }).fail(function () {
                $response.text(prettyPrintJson({
                    errors: [
                        { message: 'Request failed. Check admin session and endpoint connectivity.' }
                    ]
                }));
                showMessage($messages, 'error', 'Request failed.');
            }).always(function () {
                $runButton.prop('disabled', false);
            });
        });
    };
});
