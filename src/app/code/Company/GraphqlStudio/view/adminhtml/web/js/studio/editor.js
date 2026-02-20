/**
 * Copyright © Company. All rights reserved.
 * Query editor adapter with optional CodeMirror.
 */
define([], function () {
    'use strict';

    function createTextareaAdapter(textarea) {
        return {
            getValue: function () {
                return textarea.value;
            },
            setValue: function (value) {
                textarea.value = value || '';
            }
        };
    }

    return function createEditor($textarea) {
        var textarea = $textarea.get(0);
        if (!textarea) {
            return {
                getValue: function () {
                    return '';
                },
                setValue: function () {
                    return;
                }
            };
        }

        // Prefer CodeMirror when available in the runtime. Fall back to textarea otherwise.
        if (window.CodeMirror) {
            var editor = window.CodeMirror.fromTextArea(textarea, {
                mode: 'graphql',
                lineNumbers: true,
                lineWrapping: true,
                viewportMargin: Infinity
            });
            return {
                getValue: function () {
                    return editor.getValue();
                },
                setValue: function (value) {
                    editor.setValue(value || '');
                }
            };
        }

        return createTextareaAdapter(textarea);
    };
});
