<!doctype html>
<html lang="<?= config('language_code') ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f2937">
    <meta name="google" content="notranslate">

    <title><?= vars('page_title') ?> | Easy!Appointments</title>

    <link rel="icon" type="image/x-icon" href="<?= asset_url('assets/img/favicon.ico') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/themes/minty.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/general.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/turnos.css') ?>">

    <?php component('company_color_style', ['company_color' => vars('company_color')]); ?>
</head>
<body class="aad-queue-display">
<main class="aad-queue-shell">
    <header class="aad-queue-header">
        <div>
            <p class="aad-queue-kicker"><?= lang('provider') ?></p>
            <h1 id="queue-provider-name">
                <?= e(trim((vars('provider')['first_name'] ?? '') . ' ' . (vars('provider')['last_name'] ?? ''))) ?>
            </h1>
        </div>
        <div class="aad-queue-clock">
            <span id="queue-date"></span>
            <strong id="queue-time"></strong>
        </div>
    </header>

    <section class="aad-queue-section aad-queue-section-active">
        <div class="aad-queue-section-header">
            <h2><?= lang('queue_in_process') ?></h2>
        </div>
        <div id="queue-in-process" class="aad-queue-grid aad-queue-grid-active"></div>
    </section>

    <section class="aad-queue-section aad-queue-section-waiting">
        <div class="aad-queue-section-header">
            <h2><?= lang('queue_reserved') ?></h2>
        </div>
        <div id="queue-reserved" class="aad-queue-grid"></div>
    </section>
</main>

<script src="<?= asset_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment/moment.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/moment-timezone/moment-timezone-with-data.min.js') ?>"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/url.js') ?>"></script>
<script src="<?= asset_url('assets/js/utils/string.js') ?>"></script>
<script src="<?= asset_url('assets/js/http/localization_http_client.js') ?>"></script>

<?php component('js_vars_script'); ?>
<?php component('js_lang_script'); ?>

<script src="<?= asset_url('assets/js/http/turnos_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/turnos.js') ?>"></script>
</body>
</html>
