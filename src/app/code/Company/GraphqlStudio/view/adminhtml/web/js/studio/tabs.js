/**
 * Copyright © Company. All rights reserved.
 * Generic tab toggling helper.
 */
define([], function () {
    'use strict';

    return function bindTabs($tabContainer) {
        if (!$tabContainer.length) {
            return;
        }

        var $buttons = $tabContainer.find('[data-tab-target]');
        var $panels = $tabContainer.find('[data-tab-panel]');

        $buttons.on('click', function () {
            var target = this.getAttribute('data-tab-target');
            if (!target) {
                return;
            }

            $buttons.removeClass('is-active');
            $panels.removeClass('is-active');

            this.classList.add('is-active');
            $tabContainer.find('[data-tab-panel="' + target + '"]').addClass('is-active');
        });
    };
});
