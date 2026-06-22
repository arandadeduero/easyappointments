<?php extend('layouts/message_layout'); ?>

<?php section('content'); ?>

<h3 class="fw-light mb-3"><?= lang('kiosk_authorization') ?></h3>

<p class="text-muted mb-4">
    <?= lang('kiosk_authorization_hint') ?>
</p>

<div class="alert alert-info mb-4">
    <strong><?= lang('provider') ?>:</strong>
    <?= e(trim(vars('provider')['first_name'] . ' ' . vars('provider')['last_name'])) ?>
</div>

<?php if (vars('pairing_error')): ?>
    <div class="alert alert-danger"><?= e(vars('pairing_error')) ?></div>
<?php endif; ?>

<form method="post" action="<?= site_url('in_person_appointments/authorize_device') ?>">
    <input type="hidden" name="csrf_token" value="<?= e(vars('csrf_token')) ?>">
    <input type="hidden" name="departamento" value="<?= e((string) vars('provider')['id']) ?>">

    <div class="mb-3 text-start">
        <label for="pairing-code" class="form-label"><?= lang('kiosk_pairing_code') ?></label>
        <input id="pairing-code" name="pairing_code" type="text" class="form-control form-control-lg text-center"
               inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
    </div>

    <button type="submit" class="btn btn-primary w-100">
        <?= lang('authorize') ?>
    </button>
</form>

<?php end_section('content'); ?>
