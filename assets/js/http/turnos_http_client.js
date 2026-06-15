/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

/**
 * Queue display HTTP client.
 */
App.Http.Turnos = (function () {
    function getData() {
        return $.get(vars('turnos_data_url'));
    }

    return {
        getData,
    };
})();
