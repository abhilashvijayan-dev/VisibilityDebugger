/**
 * Copyright © Company. All rights reserved.
 * History sidebar rendering and interactions.
 */
define(['jquery'], function ($) {
    'use strict';

    function parseJsonString(raw) {
        if (!raw) {
            return {};
        }

        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function fmtDate(raw) {
        if (!raw) {
            return '';
        }

        var dt = new Date(raw.replace(' ', 'T') + 'Z');
        if (isNaN(dt.getTime())) {
            return raw;
        }

        return dt.toLocaleString();
    }

    return function createHistoryManager(config) {
        var $root = config.root;
        var $list = $root.find('[data-role="history-list"]');
        var $empty = $root.find('[data-role="history-empty"]');
        var $more = $root.find('[data-role="history-more"]');
        var pageSize = 10;
        var currentPage = 1;
        var totalCount = 0;

        function renderItem(item) {
            var title = item.operation_name || 'Anonymous operation';
            var stateClass = item.status === 'success' ? 'is-success' : 'is-error';
            var starText = Number(item.is_starred) === 1 ? '★' : '☆';

            return [
                '<div class="company-graphql-studio__history-item ' + stateClass + '" data-history-id="' + item.entity_id + '">',
                '  <div class="company-graphql-studio__history-item-top">',
                '    <button type="button" class="company-graphql-studio__history-star" data-role="history-star" data-history-id="' + item.entity_id + '" data-is-starred="' + item.is_starred + '">' + starText + '</button>',
                '    <button type="button" class="company-graphql-studio__history-open" data-role="history-open" data-history-id="' + item.entity_id + '">' + title + '</button>',
                '  </div>',
                '  <div class="company-graphql-studio__history-meta">',
                '    <span>' + fmtDate(item.created_at) + '</span>',
                '    <span>' + (item.duration_ms || 0) + ' ms</span>',
                '  </div>',
                '</div>'
            ].join('');
        }

        function refreshVisibility() {
            var hasItems = $list.children().length > 0;
            $empty.toggle(!hasItems);
            $more.toggle(hasItems && (currentPage * pageSize < totalCount));
        }

        function load(reset) {
            if (reset) {
                currentPage = 1;
                totalCount = 0;
                $list.empty();
            }

            return $.ajax({
                url: config.historyListUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    currentPage: currentPage,
                    pageSize: pageSize
                }
            }).done(function (res) {
                if (!res || !res.success) {
                    refreshVisibility();
                    return;
                }

                totalCount = Number(res.total_count || 0);
                (res.items || []).forEach(function (item) {
                    $list.append(renderItem(item));
                });
                refreshVisibility();
            });
        }

        function loadMore() {
            currentPage += 1;
            return load(false);
        }

        function openItem(id) {
            return $.ajax({
                url: config.historyViewUrl,
                type: 'GET',
                dataType: 'json',
                data: { id: id }
            }).done(function (res) {
                if (!res || !res.success || !res.item) {
                    return;
                }

                var item = res.item;
                config.setEndpoint(item.endpoint || config.defaultEndpoint);
                config.setQuery(item.query_text || '');
                config.setVariables(JSON.stringify(parseJsonString(item.variables_json), null, 2));
                config.setHeaders(JSON.stringify(parseJsonString(item.headers_json), null, 2));
            });
        }

        function toggleStar(id, isStarred) {
            return $.ajax({
                url: config.historyStarUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: config.formKey,
                    id: id,
                    is_starred: isStarred ? 1 : 0
                }
            }).done(function (res) {
                if (!res || !res.success) {
                    return;
                }

                load(true);
            });
        }

        $more.on('click', function () {
            loadMore();
        });

        $root.on('click', '[data-role="history-open"]', function () {
            openItem($(this).data('history-id'));
        });

        $root.on('click', '[data-role="history-star"]', function () {
            var id = $(this).data('history-id');
            var current = Number($(this).data('is-starred')) === 1;
            toggleStar(id, !current);
        });

        return {
            refresh: function () {
                return load(true);
            }
        };
    };
});
