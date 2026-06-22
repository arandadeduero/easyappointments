<?php extend('layouts/message_layout'); ?>

<?php section('content'); ?>

<div id="form-message" class="alert d-none"></div>

<div id="walkin-service-step" class="text-start">
    <h3 class="fw-light mb-3 text-center"><?= lang('walkin_create') ?></h3>

    <div class="alert alert-info mb-4 text-center">
        <strong><?= lang('provider') ?>:</strong>
        <span id="department-name"></span>
    </div>

    <div class="mb-3">
        <label for="service" class="form-label"><?= lang('service') ?> <span class="text-danger">*</span></label>
        <select id="service" class="form-select required"></select>
    </div>

    <button id="continue-walkin" class="btn btn-primary w-100">
        <?= lang('next') ?>
        <i class="fas fa-chevron-right ms-2"></i>
    </button>
</div>

<div id="walkin-documentation-step" class="text-start d-none">
    <h3 class="fw-light mb-4 text-center"><?= lang('documentation_to_present') ?></h3>

    <div id="service-documentation-text" class="alert alert-warning mb-4"></div>

    <div class="d-flex justify-content-between gap-2">
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

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/http/in_person_appointments_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/local_printing.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/in_person_appointments.js') ?>"></script>
<?php end_section('scripts'); ?>
