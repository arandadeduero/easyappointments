<?php
/**
 * Report counter table.
 *
 * @var array $rows
 */
?>

<?php if (empty($rows)): ?>
    <div class="alert alert-light border"><?= lang('no_records_found') ?></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead>
            <tr>
                <th><?= lang('name') ?></th>
                <th class="text-end"><?= lang('total') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $label => $count): ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <td class="text-end"><?= (int) $count ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
