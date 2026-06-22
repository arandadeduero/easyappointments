<?php extend('layouts/message_layout'); ?>

<?php section('content'); ?>

<div class="d-flex align-items-center justify-content-center min-vh-100-">
    <div class="text-center py-4 px-3">
        <div class="d-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 mx-auto mb-4" style="width: 100px; height: 100px;">
            <i class="fas fa-calendar-check fa-3x text-success"></i>
        </div>

        <h3 class="text-success fw-semibold mb-4"><?= lang('appointment_registered') ?></h3>

        <p class="fs-5 text-muted mb-1">
            <?= lang('appointment_details_was_sent_to_you') ?>
        </p>

        <p class="text-muted small mb-4">
            <?= lang('check_spam_folder') ?>
        </p>

        <?php if (!empty(vars('queue_number'))): ?>
        <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary bg-opacity-10 text-primary px-4 py-3 mb-4">
            <div>
                <div class="small text-uppercase fw-semibold mb-1"><?= lang('walkin_number_generated') ?></div>
                <div class="display-6 fw-bold mb-0">#<?= e((string) vars('queue_number')) ?></div>
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
