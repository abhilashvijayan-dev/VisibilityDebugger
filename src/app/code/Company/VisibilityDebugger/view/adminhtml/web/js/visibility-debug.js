/**
 * Copyright © Company. All rights reserved.
 * AJAX call to visibility debug endpoint and render results.
 */
define([
    'jquery',
    'Magento_Ui/js/modal/alert'
], function ($, alert) {
    'use strict';

    return function (config) {
        var $run = $('#visibility-debug-run');
        var $store = $('#visibility-debug-store');
        var $results = $('#visibility-debug-results');
        var $tbody = $('#visibility-debug-tbody');
        var $commands = $('#visibility-debug-commands');
        var $commandsText = $('#visibility-debug-commands-text');
        var $messages = $('.company-visibility-debug-section .messages');

        function showMessage(type, text) {
            $messages.removeClass('message-success message-error').addClass('message-' + type);
            $messages.find('.message').remove();
            $messages.append($('<div class="message message-' + type + '">').text(text));
        }

        function statusIcon(status) {
            if (status === 'pass') {
                return '&#10004;'; // check
            }
            if (status === 'fail') {
                return '&#10008;'; // cross
            }
            return '&#9888;'; // warn
        }

        function statusClass(status) {
            if (status === 'pass') return 'visibility-status-pass';
            if (status === 'fail') return 'visibility-status-fail';
            return 'visibility-status-warn';
        }

        $run.on('click', function () {
            var productId = config.productId;
            var storeId = parseInt($store.val(), 10);
            var url = config.debugUrl + '?product_id=' + productId + '&store_id=' + storeId;

            $run.prop('disabled', true);
            $tbody.empty();
            $results.hide();
            $commands.hide();
            $messages.find('.message').remove();

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json'
            }).done(function (data) {
                $run.prop('disabled', false);
                if (!data.success) {
                    showMessage('error', data.error || 'Request failed.');
                    return;
                }
                var issues = data.issues || [];
                issues.forEach(function (issue) {
                    var tr = $('<tr>');
                    tr.append($('<td>').addClass(statusClass(issue.status)).html(statusIcon(issue.status)));
                    tr.append($('<td>').text(issue.title || ''));
                    tr.append($('<td>').text(issue.message || ''));
                    tr.append($('<td>').text(issue.suggestion || ''));
                    $tbody.append(tr);
                });
                $results.show();
                if (data.recommended_commands) {
                    $commandsText.text(data.recommended_commands);
                    $commands.show();
                }
            }).fail(function () {
                $run.prop('disabled', false);
                showMessage('error', 'Request failed. Check permissions and try again.');
            });
        });
    };
});
