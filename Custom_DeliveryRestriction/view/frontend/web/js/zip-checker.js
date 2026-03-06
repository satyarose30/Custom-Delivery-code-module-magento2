/**
 * Custom_DeliveryRestriction/js/zip-checker
 *
 * Magento 2 RequireJS widget for the product-page delivery availability checker.
 *
 * Behaviour:
 *  - Validates zip format client-side before sending to the server
 *  - Sends a POST AJAX request with form_key (CSRF protection)
 *  - Shows available / unavailable / error feedback inline
 *  - If available, shows estimated delivery date range (if configured)
 *  - Persists the entered zip in sessionStorage so it survives page reloads
 *  - Auto-checks on page load if a zip was previously stored
 */
define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    /** @type {string} sessionStorage key */
    var SESSION_KEY = 'dr_checked_zip';

    /** Sanitise user input — same rules as the server-side regex */
    function isValidZipFormat(zip) {
        return /^[a-zA-Z0-9\s\-]{2,10}$/.test(zip);
    }

    /**
     * Safely encode a string for insertion as HTML text.
     * Avoids XSS even if the server ever returns unexpected data.
     */
    function escapeHtml(str) {
        return $('<div>').text(String(str)).html();
    }

    return function (config, element) {
        var $widget   = $(element);
        var $input    = $widget.find('#dr-zip-input');
        var $btn      = $widget.find('#dr-zip-btn');
        var $loading  = $widget.find('#dr-loading');
        var $result   = $widget.find('#dr-result');

        // ── Restore previously checked zip ────────────────────────────────────
        var saved = sessionStorage.getItem(SESSION_KEY);
        if (saved) {
            $input.val(saved);
        }

        // ── UI helpers ─────────────────────────────────────────────────────────

        function showLoading() {
            $result.hide().html('');
            $loading.show();
            $btn.prop('disabled', true).addClass('dr-loading-state');
        }

        function hideLoading() {
            $loading.hide();
            $btn.prop('disabled', false).removeClass('dr-loading-state');
        }

        function showConnectionError(canRetry) {
            var message = canRetry
                ? $t('Connection timeout. Please try again.')
                : $t('Connection error. Please check your internet and try again.');

            showResult(
                '<span class="dr-icon-inline">⚠</span> ' + escapeHtml(message),
                'warning'
            );
        }

        /**
         * @param {string} html   — already escaped HTML string
         * @param {string} type   — 'available' | 'unavailable' | 'warning'
         */
        function showResult(html, type) {
            $result
                .removeClass('dr-available dr-unavailable dr-warning')
                .addClass('dr-' + type)
                .html(html)
                .fadeIn(250);
        }

        // ── Core check ─────────────────────────────────────────────────────────

        function performCheck(retryCount) {
            retryCount = retryCount || 0;
            var zip = $.trim($input.val());

            if (!zip) {
                showResult(
                    '<span class="dr-icon-inline">⚠</span> ' + escapeHtml($t('Please enter a zip code.')),
                    'warning'
                );
                return;
            }

            if (!isValidZipFormat(zip)) {
                showResult(
                    '<span class="dr-icon-inline">⚠</span> ' +
                    escapeHtml($t('Please enter a valid zip / postal code (2–10 alphanumeric characters).')),
                    'warning'
                );
                return;
            }

            showLoading();

            var retrying = false;

            $.ajax({
                url:      config.ajaxUrl,
                type:     'POST',
                dataType: 'json',
                timeout:  Number(config.requestTimeoutMs || 8000),
                data: {
                    zip_code: zip,
                    form_key: config.formKey
                },

                success: function (resp) {
                    if (!resp || resp.error) {
                        var errMsg = (resp && resp.message) ? resp.message : $t('An error occurred. Please try again.');
                        showResult(
                            '<span class="dr-icon-inline">⚠</span> ' + escapeHtml(errMsg),
                            'warning'
                        );
                        return;
                    }

                    if (resp.available) {
                        sessionStorage.setItem(SESSION_KEY, zip);

                        var html = '<span class="dr-icon-inline dr-ok">✔</span> ' +
                                   escapeHtml(resp.message);

                        if (resp.delivery_message) {
                            html += '<div class="dr-estimate">' +
                                    '<span class="dr-icon-inline">🚚</span> ' +
                                    escapeHtml(resp.delivery_message) +
                                    '</div>';
                        }

                        showResult(html, 'available');
                    } else {
                        sessionStorage.removeItem(SESSION_KEY);
                        showResult(
                            '<span class="dr-icon-inline dr-no">✘</span> ' + escapeHtml(resp.message),
                            'unavailable'
                        );
                    }
                },

                error: function (xhr, status) {
                    if (status === 'timeout' && retryCount < 1) {
                        retrying = true;
                        performCheck(retryCount + 1);
                        return;
                    }

                    showConnectionError(status === 'timeout');
                },

                complete: function () {
                    if (!retrying) {
                        hideLoading();
                    }
                }
            });
        }

        // ── Event bindings ─────────────────────────────────────────────────────

        $btn.on('click', performCheck);

        $input.on('keydown', function (e) {
            // Enter key
            if (e.which === 13) {
                e.preventDefault();
                performCheck();
            }
        });

        // Clear result when user starts typing a different zip
        $input.on('input', function () {
            if ($result.is(':visible')) {
                $result.fadeOut(150);
            }
        });

        // ── Auto-check on load if we have a saved zip ──────────────────────────
        if (saved && isValidZipFormat(saved)) {
            performCheck();
        }
    };
});
