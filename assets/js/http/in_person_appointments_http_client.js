/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

App.Http.InPersonAppointments = (function () {
    function store(postData) {
        return $.post(App.Utils.Url.siteUrl('in_person_appointments/store'), {
            csrf_token: vars('csrf_token'),
            post_data: postData,
        });
    }

    return {
        store,
    };
})();
