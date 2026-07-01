<?php extend('layouts/message_layout'); ?>

<?php if (!empty(vars('queue_number'))): ?>
<?php section('styles'); ?>
<style>
    .walkin-confirmation {
        font-size: 1.2rem;
        line-height: 1.55;
    }

    .walkin-confirmation h3 {
        font-size: clamp(1.8rem, 4vw, 2.35rem);
        line-height: 1.25;
    }

    .walkin-confirmation .walkin-confirmation-icon {
        width: 112px;
        height: 112px;
    }

    .walkin-confirmation .walkin-confirmation-text {
        font-size: 1.25rem;
    }

    .walkin-confirmation .walkin-ticket-number {
        font-size: clamp(2.5rem, 8vw, 4rem);
        line-height: 1;
    }

    .walkin-confirmation .walkin-ticket-label,
    .walkin-confirmation #local-printing-message {
        font-size: 1.15rem;
    }

    .walkin-confirmation .btn {
        min-height: 3.5rem;
        font-size: 1.25rem;
        font-weight: 600;
    }

    @media (max-width: 575.98px) {
        .walkin-confirmation {
            font-size: 1.1rem;
        }

        .walkin-confirmation .walkin-confirmation-text,
        .walkin-confirmation .btn {
            font-size: 1.1rem;
        }
    }
</style>
<?php end_section('styles'); ?>
<?php endif; ?>

<?php section('content'); ?>

<div class="d-flex align-items-center justify-content-center min-vh-100-<?= !empty(vars('queue_number')) ? ' walkin-confirmation' : '' ?>">
    <div class="text-center py-4 px-3">
        <div class="<?= !empty(vars('queue_number')) ? 'walkin-confirmation-icon ' : '' ?>d-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 mx-auto mb-4"<?= empty(vars('queue_number')) ? ' style="width: 100px; height: 100px;"' : '' ?>>
            <i class="fas fa-calendar-check fa-3x text-success"></i>
        </div>

        <h3 class="text-success fw-semibold mb-4"><?= lang('appointment_registered') ?></h3>

        <p class="walkin-confirmation-text fs-5 text-muted mb-1">
            <?= lang('appointment_details_was_sent_to_you') ?>
        </p>

        <p class="text-muted small mb-4">
            <?= lang('check_spam_folder') ?>
        </p>

        <?php if (!empty(vars('queue_number'))): ?>
        <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary bg-opacity-10 text-primary px-4 py-3 mb-4">
            <div>
                <div class="walkin-ticket-label small text-uppercase fw-semibold mb-2"><?= lang('walkin_number_generated') ?></div>
                <div class="walkin-ticket-number display-6 fw-bold mb-0">#<?= e((string) vars('queue_number')) ?></div>
            </div>
        </div>
        <div id="local-printing-message" class="alert d-none mt-2" role="alert"></div>
        <button id="reprint-walkin-ticket" type="button" class="btn btn-outline-primary">
            <i class="fas fa-print me-2"></i>
            <?= lang('reprint_ticket') ?>
        </button>
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
