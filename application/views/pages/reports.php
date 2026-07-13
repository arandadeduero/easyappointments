<?php extend('layouts/backend_layout'); ?>

<?php
$filters = vars('filters');
$period = vars('period');
$report = vars('report');
$summary = $report['summary'];
?>

<?php section('content'); ?>

<div class="container backend-page py-3" id="reports-page">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-1 fw-light mt-5">
                <i class="fas fa-chart-bar me-2"></i>
                <?= lang('reports') ?>
            </h4>
            <div class="text-muted">
                <?= e($period['start_date']) ?> - <?= e($period['end_date']) ?>
            </div>
        </div>

        <a href="<?= site_url('calendar') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-calendar-alt me-2"></i>
            <?= lang('calendar') ?>
        </a>
    </div>

    <form method="get" action="<?= site_url('reports') ?>" class="row g-3 align-items-end mb-4">
        <div class="col-12 col-md-3 col-xl-2">
            <label for="period" class="form-label"><?= lang('report_period') ?></label>
            <select id="period" name="period" class="form-select">
                <option value="day" <?= $filters['period'] === 'day' ? 'selected' : '' ?>>
                    <?= lang('day') ?>
                </option>
                <option value="week" <?= $filters['period'] === 'week' ? 'selected' : '' ?>>
                    <?= lang('week') ?>
                </option>
                <option value="month" <?= $filters['period'] === 'month' ? 'selected' : '' ?>>
                    <?= lang('month') ?>
                </option>
            </select>
        </div>

        <div class="col-12 col-md-3 col-xl-2">
            <label for="date" class="form-label"><?= lang('date') ?></label>
            <input id="date" name="date" type="date" class="form-control" value="<?= e($filters['date']) ?>">
        </div>

        <div class="col-12 col-md-3 col-xl-3">
            <label for="provider-id" class="form-label"><?= lang('provider') ?></label>
            <select id="provider-id" name="provider_id" class="form-select">
                <option value="0"><?= lang('all') ?></option>
                <?php foreach (vars('available_providers') as $provider): ?>
                    <option value="<?= (int) $provider['id'] ?>" <?= (int) $filters['provider_id'] === (int) $provider['id'] ? 'selected' : '' ?>>
                        <?= e(trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-3 col-xl-3">
            <label for="service-id" class="form-label"><?= lang('service') ?></label>
            <select id="service-id" name="service_id" class="form-select">
                <option value="0"><?= lang('all') ?></option>
                <?php foreach (vars('available_services') as $service): ?>
                    <option value="<?= (int) $service['id'] ?>" <?= (int) $filters['service_id'] === (int) $service['id'] ? 'selected' : '' ?>>
                        <?= e($service['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-xl-2 d-grid">
            <button class="btn btn-primary" type="submit">
                <i class="fas fa-search me-2"></i>
                <?= lang('generate_report') ?>
            </button>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="text-muted small"><?= lang('total_appointments') ?></div>
                <div class="display-6 fw-semibold"><?= (int) $summary['total'] ?></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="text-muted small"><?= lang('appointment_type_booking') ?></div>
                <div class="display-6 fw-semibold"><?= (int) $summary['booking'] ?></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="text-muted small"><?= lang('appointment_type_walkin') ?></div>
                <div class="display-6 fw-semibold"><?= (int) $summary['walkin'] ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <h5 class="fw-light mb-3"><?= lang('report_by_service') ?></h5>
            <?php component('report_counter_table', ['rows' => $report['services']]); ?>
        </div>

        <div class="col-12 col-lg-6">
            <h5 class="fw-light mb-3"><?= lang('report_by_status') ?></h5>
            <?php component('report_counter_table', ['rows' => $report['statuses']]); ?>
        </div>

        <div class="col-12 col-lg-6">
            <h5 class="fw-light mb-3"><?= lang('report_by_provider') ?></h5>
            <?php component('report_counter_table', ['rows' => $report['providers']]); ?>
        </div>

        <div class="col-12 col-lg-6">
            <h5 class="fw-light mb-3"><?= lang('report_by_day') ?></h5>
            <?php if (empty($report['days'])): ?>
                <div class="alert alert-light border"><?= lang('no_records_found') ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                        <tr>
                            <th><?= lang('date') ?></th>
                            <th class="text-end"><?= lang('total') ?></th>
                            <th class="text-end"><?= lang('appointment_type_booking') ?></th>
                            <th class="text-end"><?= lang('appointment_type_walkin') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($report['days'] as $day): ?>
                            <tr>
                                <td><?= e($day['date']) ?></td>
                                <td class="text-end"><?= (int) $day['total'] ?></td>
                                <td class="text-end"><?= (int) $day['booking'] ?></td>
                                <td class="text-end"><?= (int) $day['walkin'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php end_section('content'); ?>
