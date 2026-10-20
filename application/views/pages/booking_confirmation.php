<?php extend('layouts/message_layout'); ?>

<?php if (!empty(vars('queue_number'))): ?>
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

    .walkin-confirmation {
        width: 100%;
        height: 100%;
        max-width: none;
        margin: 0 auto;
        font-size: clamp(1.15rem, 1.6vw, 1.55rem);
        line-height: 1.5;
    }

    .walkin-confirmation h3 {
        font-size: clamp(2rem, 3.6vw, 3.25rem);
        line-height: 1.25;
    }

    .walkin-confirmation .walkin-confirmation-icon {
        width: clamp(5rem, 10vw, 8rem);
        height: clamp(5rem, 10vw, 8rem);
    }

    .walkin-confirmation .walkin-confirmation-icon .fas {
        font-size: clamp(2.5rem, 5vw, 4rem);
    }

    .walkin-confirmation .walkin-confirmation-text {
        font-size: clamp(1.15rem, 1.7vw, 1.65rem);
    }

    .walkin-confirmation .walkin-ticket-number {
        font-size: clamp(3.5rem, 9vw, 7rem);
        line-height: 1;
    }

    .walkin-confirmation .walkin-ticket-label,
    .walkin-confirmation #local-printing-message {
        font-size: clamp(1.15rem, 1.6vw, 1.55rem);
    }

    .walkin-confirmation .btn {
        min-height: clamp(3.75rem, 7vh, 4.75rem);
        font-size: clamp(1.2rem, 1.8vw, 1.7rem);
        font-weight: 600;
    }

    .walkin-confirmation .walkin-actions {
        position: fixed;
        right: clamp(1rem, 4vw, 4rem);
        bottom: 1rem;
        left: clamp(1rem, 4vw, 4rem);
        z-index: 1000;
        display: flex;
    }

    .walkin-confirmation .walkin-actions .btn {
        width: 100%;
    }

    @media (max-width: 575.98px) {
        body.walkin-kiosk-page #message-frame {
            padding: 1rem 1rem 5.75rem !important;
        }

        .walkin-confirmation {
            font-size: 1.1rem;
        }

        .walkin-confirmation .walkin-confirmation-text,
        .walkin-confirmation .btn {
            font-size: 1.1rem;
        }

        .walkin-confirmation .walkin-actions {
            right: 1rem;
            bottom: 1rem;
            left: 1rem;
        }
    }
</style>
<?php end_section('styles'); ?>
<?php endif; ?>

<?php section('content'); ?>

<?php if (!empty(vars('queue_number'))): ?>
<script>
    document.documentElement.classList.add('walkin-kiosk-page');
    document.body.classList.add('walkin-kiosk-page');
</script>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-center<?= !empty(vars('queue_number')) ? ' walkin-confirmation' : ' min-vh-100' ?>">
    <div class="text-center py-4 px-3">
        <div class="<?= !empty(vars('queue_number')) ? 'walkin-confirmation-icon ' : '' ?>d-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 mx-auto mb-4"<?= empty(vars('queue_number')) ? ' style="width: 100px; height: 100px;"' : '' ?>>
            <i class="fas fa-calendar-check fa-3x text-success"></i>
        </div>

        <h3 class="text-success fw-semibold mb-4"><?= lang('appointment_registered') ?></h3>

        <?php if (empty(vars('is_walkin'))): ?>
        <p class="walkin-confirmation-text fs-5 text-muted mb-1">
            <?= lang('appointment_details_was_sent_to_you') ?>
        </p>

        <p class="text-muted small mb-4">
            <?= lang('check_spam_folder') ?>
        </p>
        <?php endif; ?>

        <?php if (!empty(vars('queue_number'))): ?>
        <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary bg-opacity-10 text-primary px-4 py-3 mb-4">
            <div>
                <div class="walkin-ticket-label small text-uppercase fw-semibold mb-2"><?= lang('walkin_number_generated') ?></div>
                <div class="walkin-ticket-number display-6 fw-bold mb-0">#<?= e((string) vars('queue_number')) ?></div>
            </div>
        </div>
        <div id="local-printing-message" class="alert d-none mt-2" role="alert"></div>
        <div class="walkin-actions">
            <button id="reprint-walkin-ticket" type="button" class="btn btn-outline-primary">
                <i class="fas fa-print me-2"></i>
                <?= lang('reprint_ticket') ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<?php if (!empty(vars('queue_number'))): ?>
<script src="<?= asset_url('assets/js/utils/local_printing.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/in_person_confirmation.js') ?>"></script>
<?php endif; ?>

<?php component('google_analytics_script', ['google_analytics_code' => vars('google_analytics_code')]); ?>
<?php component('matomo_analytics_script', [
    'matomo_analytics_url' => vars('matomo_analytics_url'),
    'matomo_analytics_site_id' => vars('matomo_analytics_site_id'),
]); ?>

<?php end_section('scripts'); ?>
