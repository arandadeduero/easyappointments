<?php extend('layouts/message_layout'); ?>

<?php section('content'); ?>

<div class="text-start">
    <h3 class="fw-light mb-3 text-center"><?= lang('walkin_create') ?></h3>

    <div class="alert alert-info mb-4 text-center">
        <strong><?= lang('provider') ?>:</strong>
        <span id="department-name"></span>
    </div>

    <div id="form-message" class="alert d-none"></div>

    <div class="mb-3">
        <label for="service" class="form-label"><?= lang('service') ?> <span class="text-danger">*</span></label>
        <select id="service" class="form-select required"></select>
    </div>

    <div id="service-documentation" class="alert alert-warning d-none mb-3">
        <strong><?= lang('documentation_to_present') ?>:</strong>
        <div id="service-documentation-text" class="mt-2"></div>
    </div>

    <button id="create-walkin" class="btn btn-primary w-100">
        <i class="fas fa-check me-2"></i>
        <?= lang('walkin_create') ?>
    </button>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/http/in_person_appointments_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/in_person_appointments.js') ?>"></script>
<?php end_section('scripts'); ?>
