<?php extend('layouts/message_layout'); ?>

<?php section('styles'); ?>
<style>
    html.walkin-kiosk-page,
    body.walkin-kiosk-page {
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    body.walkin-kiosk-page #main {
        width: 100vw;
        max-width: none;
        height: 100vh;
        padding: 0;
        overflow: hidden;
    }

    body.walkin-kiosk-page .wrapper {
        width: 100%;
        height: 100%;
        min-height: 100vh !important;
        margin: 0;
        padding: 0 !important;
    }

    body.walkin-kiosk-page #message-frame {
        width: 100%;
        max-width: none;
        height: 100%;
        margin: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: clamp(1.25rem, 3vw, 2.5rem) clamp(1.25rem, 4vw, 3.5rem) 7.25rem !important;
    }

    body.walkin-kiosk-page #message-frame > .mt-2 {
        display: none;
    }

    .walkin-accessible {
        width: 100%;
        max-width: none;
        margin: 0 auto;
        font-size: clamp(1.15rem, 1.6vw, 1.55rem);
        line-height: 1.5;
    }

    .walkin-accessible h3 {
        font-size: clamp(2rem, 3.6vw, 3.25rem);
        line-height: 1.25;
    }

    .walkin-accessible .alert,
    .walkin-accessible .form-label {
        font-size: clamp(1.15rem, 1.6vw, 1.55rem);
    }

    .walkin-accessible .form-label {
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .walkin-accessible .form-select,
    .walkin-accessible .btn {
        min-height: clamp(3.75rem, 7vh, 4.75rem);
        font-size: clamp(1.2rem, 1.8vw, 1.7rem);
    }

    .walkin-accessible .form-select {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .walkin-accessible .btn {
        font-weight: 600;
    }

    .walkin-accessible #service-documentation-text {
        width: 100%;
        max-height: calc(100vh - 15rem);
        font-size: clamp(1.15rem, 1.7vw, 1.65rem);
        line-height: 1.45;
        overflow: hidden;
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .walkin-accessible #walkin-service-step,
    .walkin-accessible #walkin-documentation-step,
    .walkin-accessible #walkin-time-step {
        width: 100%;
    }

    .walkin-accessible #walkin-time-value {
        font-size: clamp(3rem, 8vw, 7rem);
        line-height: 1;
    }

    .walkin-accessible .walkin-actions {
        position: fixed;
        right: clamp(1rem, 4vw, 4rem);
        bottom: 1rem;
        left: clamp(1rem, 4vw, 4rem);
        z-index: 1000;
        display: flex;
        gap: clamp(0.75rem, 2vw, 1.5rem);
    }

    .walkin-accessible .walkin-actions .btn {
        flex: 1 1 0;
    }

    @media (max-width: 575.98px) {
        body.walkin-kiosk-page #message-frame {
            padding: 1rem 1rem 5.75rem !important;
        }

        .walkin-accessible {
            font-size: 1.1rem;
        }

        .walkin-accessible .alert,
        .walkin-accessible .form-label,
        .walkin-accessible .form-select,
        .walkin-accessible .btn {
            font-size: 1.1rem;
        }

        .walkin-accessible .walkin-actions {
            right: 1rem;
            bottom: 1rem;
            left: 1rem;
        }
    }
</style>
<?php end_section('styles'); ?>

<?php section('content'); ?>

<script>
    document.documentElement.classList.add('walkin-kiosk-page');
    document.body.classList.add('walkin-kiosk-page');
</script>

<div class="walkin-accessible">
    <?php if (vars('maintenance_access_notice')): ?>
        <div class="alert alert-warning text-start" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= e(vars('maintenance_access_notice')) ?>
        </div>
    <?php endif; ?>

    <div id="form-message" class="alert d-none"></div>

    <div id="walkin-service-step" class="text-start">
        <h3 class="fw-light mb-3 text-center"><?= lang('walkin_create') ?></h3>

        <div class="alert alert-info mb-4 text-center">
            <strong><?= lang('provider') ?>:</strong>
            <span id="department-name"></span>
        </div>

        <div class="mb-4">
            <label for="service" class="form-label"><?= lang('service') ?> <span class="text-danger">*</span></label>
            <select id="service" class="form-select required"></select>
        </div>

        <div class="walkin-actions">
            <button id="continue-walkin" class="btn btn-primary w-100">
                <?= lang('next') ?>
                <i class="fas fa-chevron-right ms-2"></i>
            </button>
        </div>
    </div>

    <div id="walkin-documentation-step" class="text-start d-none">
        <h3 class="fw-light mb-4 text-center"><?= lang('documentation_to_present') ?></h3>

        <div id="service-documentation-text" class="alert alert-warning mb-4"></div>

        <div class="walkin-actions">
            <button id="back-walkin" class="btn btn-outline-secondary flex-grow-1">
                <i class="fas fa-chevron-left me-2"></i>
                <?= lang('back') ?>
            </button>
            <button id="create-walkin" class="btn btn-primary flex-grow-1">
                <?= lang('next') ?>
                <i class="fas fa-chevron-right ms-2"></i>
            </button>
        </div>
    </div>

    <div id="walkin-time-step" class="text-center d-none">
        <h3 class="fw-light mb-4"><?= lang('walkin_estimated_time_title') ?></h3>

        <div class="alert alert-info mb-4">
            <div class="mb-3"><?= lang('walkin_estimated_time_intro') ?></div>
            <div id="walkin-time-value" class="fw-bold"></div>
            <div id="walkin-date-value" class="mt-3"></div>
        </div>

        <div class="walkin-actions">
            <button id="cancel-walkin-time" class="btn btn-outline-secondary flex-grow-1">
                <i class="fas fa-chevron-left me-2"></i>
                <?= lang('cancel') ?>
            </button>
            <button id="confirm-walkin-time" class="btn btn-primary flex-grow-1">
                <?= lang('next') ?>
                <i class="fas fa-chevron-right ms-2"></i>
            </button>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/http/in_person_appointments_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/local_printing.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/in_person_appointments.js') ?>"></script>
<?php end_section('scripts'); ?>
