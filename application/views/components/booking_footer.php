<?php
/**
 * Local variables.
 *
 * @var bool $display_login_button
 * @var string $legal_notice_url
 * @var string $imprint_url
 */
?>

<div id="frame-footer" class="p-3 text-center border-top">
    <small class="d-block d-md-flex justify-content-between align-items-center">
        <span class="footer-powered-by small d-block w-100 w-md-50 text-center text-md-start p-1 pe-md-0">
            Desarrollado por
            <a href="https://sanchainformatica.com" target="_blank">San-Cha Informática</a>

            <?php if (!empty($legal_notice_url)): ?>
                <span>|</span>
                <a href="<?= e($legal_notice_url) ?>" target="_blank"><?= lang('legal_notice') ?></a>
            <?php endif; ?>

            <?php if (!empty($imprint_url)): ?>
                <span>|</span>
                <a href="<?= e($imprint_url) ?>" target="_blank"><?= lang('imprint') ?></a>
            <?php endif; ?>
        </span>

        <span class="footer-options d-block w-100 w-md-50 text-center text-md-end" style="font-size: 0.7rem;">
            Powered by Easy!Appointments
            <?php if ($display_login_button): ?>
                <a class="backend-link badge bg-primary text-decoration-none px-2 d-inline-block my-1 my-md-0 p-1"
                   href="<?= session('user_id') ? site_url('calendar') : site_url('login') ?>"
                   style="min-width: 120px;">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    <?= session('user_id') ? lang('backend_section') : lang('login') ?>
                </a>
            <?php endif; ?>
        </span>
    </small>
</div>
