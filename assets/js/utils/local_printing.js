/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

App.Utils.LocalPrinting = (function () {
    const DEFAULT_TIMEOUT = 15000;

    function isEnabled() {
        return Boolean(Number(vars('local_printing_enabled') || 0));
    }

    function getEndpoint() {
        return vars('local_printing_endpoint') || 'http://127.0.0.1:8787/print';
    }

    function getToken() {
        return vars('local_printing_token') || '';
    }

    function print(ticket) {
        if (!isEnabled()) {
            return Promise.resolve({skipped: true});
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), DEFAULT_TIMEOUT);

        return fetch(getEndpoint(), {
            method: 'POST',
            mode: 'cors',
            cache: 'no-store',
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                'X-Print-Token': getToken(),
            },
            body: JSON.stringify(ticket),
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.error || 'Local print service returned HTTP ' + response.status);
                }

                return payload;
            })
            .catch((error) => {
                if (error.name === 'AbortError') {
                    throw new Error('Local print service timeout after ' + DEFAULT_TIMEOUT / 1000 + ' seconds');
                }

                throw error;
            })
            .finally(() => {
                clearTimeout(timeoutId);
            });
    }

    return {
        isEnabled,
        print,
    };
})();
