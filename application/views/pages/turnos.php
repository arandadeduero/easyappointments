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
        <section id="queue-current-call" class="aad-queue-current-call aad-queue-current-call-empty">
            <div class="aad-queue-current-desk">
                <span><?= lang('queue_desk') ?></span>
                <strong id="queue-current-desk">--</strong>
            </div>
            <div class="aad-queue-current-code">
                <p class="aad-queue-current-label"><?= lang('queue_current_call') ?></p>
                <strong id="queue-current-code">--</strong>
            </div>
        </section>
        <div class="aad-queue-clock">
            <span id="queue-date"></span>
            <strong id="queue-time"></strong>
        </div>
    </header>

    <section class="aad-queue-board">
        <div class="aad-queue-brand-panel">
            <img
                id="queue-brand-image-a"
                class="aad-queue-brand-image is-active"
                src="https://www.arandadeduero.es/wp-content/uploads/2025/12/Diseno-sin-titulo-1.gif"
                alt="Aranda de Duero"
                decoding="async"
            >

            <img
                id="queue-brand-image-b"
                class="aad-queue-brand-image"
                src="https://www.arandadeduero.es/wp-content/uploads/2025/12/GIF-Aranda-es.gif"
                alt=""
                decoding="async"
            >
        </div>

        <aside class="aad-queue-last-calls">
            <div class="aad-queue-last-header">
                <div>
                    <span><?= lang('queue_turn') ?></span>
                </div>
            </div>
            <div id="queue-called" class="aad-queue-called-list"></div>
        </aside>
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
<script src="<?= asset_url('assets/js/pages/turnos-slider.js') ?>"></script>
</body>
</html>
