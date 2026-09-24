/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * App global namespace object.
 *
 * This script should be loaded before the other modules in order to define the global application namespace.
 */
window.App = (function () {
    const CSRF_REFRESH_INTERVAL_MS = 90 * 60 * 1000;

    let csrfToken = null;
    let rateLimitedUntil = 0;
    let lastCsrfRefresh = Date.now();

    function updateResponseState(jqXHR) {
        const responseCsrfToken = jqXHR.getResponseHeader('X-CSRF-Token');

        if (responseCsrfToken) {
            csrfToken = responseCsrfToken;
            lastCsrfRefresh = Date.now();
        }

        if (jqXHR.status !== 429) {
            return;
        }

        const retryAfterSeconds = Number.parseInt(jqXHR.getResponseHeader('Retry-After'), 10);
        const retryDelay = Number.isFinite(retryAfterSeconds) ? Math.max(1, retryAfterSeconds) * 1000 : 60000;

        rateLimitedUntil = Math.max(rateLimitedUntil, Date.now() + retryDelay);

        document.dispatchEvent(
            new CustomEvent('aad:rate-limit', {
                detail: {retryAfter: retryDelay},
            }),
        );
    }

    function getCsrfToken() {
        if (csrfToken) {
            return csrfToken;
        }

        return typeof window.vars === 'function' ? vars('csrf_token') : null;
    }

    function getRateLimitDelay() {
        return Math.max(0, rateLimitedUntil - Date.now());
    }

    function deferUntilRateLimitEnds(callback) {
        const run = () => {
            const delay = getRateLimitDelay();

            if (delay > 0) {
                window.setTimeout(run, delay);
                return;
            }

            callback();
        };

        // Defer once so the global AJAX completion handler can first process
        // Retry-After from the request that just finished.
        window.setTimeout(run, 0);
    }

    function refreshCsrfToken() {
        if (document.hidden || !App.Utils.Url?.siteUrl) {
            return;
        }

        // The global AJAX completion handler reads the renewed token from the
        // response header; it is intentionally not exposed in the JSON body.
        $.get(App.Utils.Url.siteUrl('csrf/refresh'));
    }

    function onAjaxError(event, jqXHR, ajaxSettings, errorThrown) {
        console.error('Unexpected HTTP Error: ', jqXHR, ajaxSettings, errorThrown);

        let response;
        const renewedCsrfToken = jqXHR.getResponseHeader('X-CSRF-Token');
        const csrfError = jqXHR.getResponseHeader('X-CSRF-Error');

        if (jqXHR.status === 403 && csrfError === 'token-mismatch' && renewedCsrfToken) {
            csrfToken = renewedCsrfToken;
            lastCsrfRefresh = Date.now();
            response = {message: 'Security validation expired. Please try again.'};
        } else {
            try {
                response = JSON.parse(jqXHR.responseText); // JSON response
            } catch (error) {
                response = {message: jqXHR.responseText}; // String response
            }
        }

        if (!response || !response.message) {
            return;
        }

        if (App.Utils.Message) {
            App.Utils.Message.show('Easy!Appointments', lang('unexpected_issues_message'));

            $('<div/>', {
                'class': 'card',
                'html': [
                    $('<div/>', {
                        'class': 'card-body overflow-auto',
                        'html': response.message,
                    }),
                ],
            }).appendTo('#message-modal .modal-body');
        }
    }

    $.ajaxPrefilter((options, originalOptions, jqXHR) => {
        const method = String(options.type || options.method || 'GET').toUpperCase();

        if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            return;
        }

        const token = getCsrfToken();

        if (token) {
            jqXHR.setRequestHeader('X-CSRF', token);
        }
    });

    $(document).ajaxComplete((event, jqXHR) => updateResponseState(jqXHR));
    $(document).ajaxError(onAjaxError);

    $(function () {
        if (window.moment) {
            window.moment.locale(vars('language_code'));
        }

        window.setInterval(refreshCsrfToken, CSRF_REFRESH_INTERVAL_MS);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && Date.now() - lastCsrfRefresh >= CSRF_REFRESH_INTERVAL_MS) {
                refreshCsrfToken();
            }
        });
    });

    return {
        Components: {},
        Http: {},
        Layouts: {},
        Pages: {},
        Utils: {},
        deferUntilRateLimitEnds,
        getRateLimitDelay,
    };
})();
