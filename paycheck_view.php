<?php
require_once __DIR__ . '/functions.php';
ensure_schema();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    if ($action === 'update_paycheck_payment') {
        $paymentId = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
        $dueDate = isset($_POST['due_date']) ? trim((string)$_POST['due_date']) : '';
        $billName = trim((string)($_POST['bill_name'] ?? ''));
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
        $notes = trim((string)($_POST['notes'] ?? ''));
        $syncRecurringDefaults = !empty($_POST['sync_recurring_defaults']);

        if ($paymentId > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) && $billName !== '') {
            update_paycheck_payment($paymentId, $dueDate, $billName, $amount, $notes, $syncRecurringDefaults);
            redirect('paycheck_view.php?id=' . $id . '&updated=1');
        }

        redirect('paycheck_view.php?id=' . $id . '&update_error=1');
    }
}

$paycheck = get_paycheck($id);
$items = get_paycheck_payments($id);
require __DIR__ . '/header.php';
?>
<style>
.paycheck-table{table-layout:fixed}
.paycheck-table th:nth-child(1), .paycheck-table td:nth-child(1){width:120px}
.paycheck-table th:nth-child(2), .paycheck-table td:nth-child(2){width:32%}
.paycheck-table th:nth-child(3), .paycheck-table td:nth-child(3){width:110px}
.paycheck-table th:nth-child(5), .paycheck-table td:nth-child(5){width:100px}
.paycheck-bill-cell{display:flex;align-items:center;gap:8px;min-width:0}
.paycheck-bill-cell .bill-name-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.paycheck-edit-icon{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;color:#cbd5e1;background:rgba(255,255,255,.04);border:1px solid rgba(148,163,184,.16);cursor:pointer;transition:background .15s ease,color .15s ease,border-color .15s ease,transform .15s ease;flex:0 0 auto}
.paycheck-edit-icon:hover{color:#93c5fd;background:rgba(59,130,246,.10);border-color:rgba(96,165,250,.35);transform:translateY(-1px)}
.paycheck-edit-icon svg{width:15px;height:15px;display:block}
.paycheck-inline-editor-row{display:none;background:rgba(15,23,42,.48)}
.paycheck-inline-editor-row.is-open{display:table-row}
.paycheck-inline-editor-wrap{padding:14px 12px 16px;background:linear-gradient(180deg, rgba(17,24,39,.94) 0%, rgba(15,23,42,.94) 100%);border:1px solid rgba(96,165,250,.18);border-radius:12px;box-shadow:var(--shadow-soft)}
.paycheck-inline-editor-grid{display:grid;grid-template-columns:140px minmax(180px,1.4fr) 120px minmax(220px,1.5fr);gap:12px;align-items:end}
.paycheck-inline-editor-grid label{margin-bottom:0}
.paycheck-inline-editor-grid .field{display:flex;flex-direction:column;gap:6px}
.paycheck-inline-editor-grid input[type=checkbox]{width:auto}
.paycheck-inline-checkbox{display:flex;align-items:center;gap:8px;padding-top:8px;color:#dbe4f0}
.paycheck-inline-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.paycheck-inline-note{margin-top:8px;font-size:.88rem;color:var(--text-soft)}
@media (max-width: 980px){.paycheck-inline-editor-grid{grid-template-columns:1fr 1fr}.paycheck-table{table-layout:auto}}
@media (max-width: 640px){.paycheck-inline-editor-grid{grid-template-columns:1fr}}
</style>
<h1>Paycheck View</h1>
<?php if (!$paycheck): ?>
    <div class="flash error">Paycheck not found.</div>
<?php else: ?>
    <?php if (!empty($_GET['saved'])): ?><div class="flash">Paycheck and bill payments saved.</div><?php endif; ?>
    <?php if (!empty($_GET['updated'])): ?><div class="flash">Bill payment updated.</div><?php endif; ?>
    <?php if (!empty($_GET['update_error'])): ?><div class="flash error">Could not update that bill payment.</div><?php endif; ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2>Paycheck Summary</h2>
            </div>
        </div>
        <div class="grid-3">
            <div class="mini-stat">
                <div class="mini-stat-label">Paycheck Date</div>
                <div class="mini-stat-value"><?= h($paycheck['paycheck_date']) ?></div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Paycheck Amount</div>
                <div class="mini-stat-value">$<?= number_format((float)$paycheck['paycheck_amount'], 2) ?></div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Total Paid</div>
                <div class="mini-stat-value">$<?= number_format(paycheck_total_paid($id), 2) ?></div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <div>
                <h2>Bill Payments</h2>
                <p class="helper-text">This paycheck includes both recurring bills and any manual one-time entries you added.</p>
            </div>
            <div>
                <a class="button secondary" href="index.php?paycheck_date=<?= urlencode($paycheck['paycheck_date']) ?>&paycheck_amount=<?= urlencode(number_format((float)$paycheck['paycheck_amount'], 2, '.', '')) ?>">Add More Bills To This Paycheck</a>
            </div>
        </div>
        <table class="paycheck-table">
            <thead>
                <tr>
                    <th>Due Date</th>
                    <th>Bill</th>
                    <th>Amount</th>
                    <th>Notes</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($item['due_date']) ?></td>
                    <td>
                        <div class="paycheck-bill-cell">
                            <span class="bill-name-text"><?= h($item['bill_name']) ?></span>
                            <button
                                type="button"
                                class="paycheck-edit-icon paycheck-inline-edit-toggle"
                                data-target="paycheck-inline-editor-<?= (int)$item['id'] ?>"
                                title="Edit bill"
                                aria-label="Edit bill"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 20h9"/>
                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                    <td>$<?= number_format((float)$item['amount'], 2) ?></td>
                    <td><?= h($item['notes']) ?></td>
                    <td><?= !empty($item['is_manual']) ? 'Manual' : 'Recurring' ?></td>
                </tr>
                <tr class="paycheck-inline-editor-row" id="paycheck-inline-editor-<?= (int)$item['id'] ?>">
                    <td colspan="5">
                        <div class="paycheck-inline-editor-wrap">
                            <form method="post">
                                <input type="hidden" name="action" value="update_paycheck_payment">
                                <input type="hidden" name="payment_id" value="<?= (int)$item['id'] ?>">
                                <div class="paycheck-inline-editor-grid">
                                    <div class="field">
                                        <label for="edit_due_date_<?= (int)$item['id'] ?>">Due Date</label>
                                        <input type="date" id="edit_due_date_<?= (int)$item['id'] ?>" name="due_date" value="<?= h($item['due_date']) ?>" required>
                                    </div>
                                    <div class="field">
                                        <label for="edit_bill_name_<?= (int)$item['id'] ?>">Bill Name</label>
                                        <input type="text" id="edit_bill_name_<?= (int)$item['id'] ?>" name="bill_name" value="<?= h($item['bill_name']) ?>" required>
                                    </div>
                                    <div class="field">
                                        <label for="edit_amount_<?= (int)$item['id'] ?>">Amount</label>
                                        <input type="number" step="0.01" id="edit_amount_<?= (int)$item['id'] ?>" name="amount" value="<?= h(number_format((float)$item['amount'], 2, '.', '')) ?>" required>
                                    </div>
                                    <div class="field">
                                        <label for="edit_notes_<?= (int)$item['id'] ?>">Notes</label>
                                        <input type="text" id="edit_notes_<?= (int)$item['id'] ?>" name="notes" value="<?= h($item['notes']) ?>">
                                    </div>
                                </div>
                                <?php if (empty($item['is_manual']) && !empty($item['recurring_bill_id'])): ?>
                                    <label class="paycheck-inline-checkbox">
                                        <input type="checkbox" name="sync_recurring_defaults" value="1" checked>
                                        Also update this recurring bill’s default name, amount, and notes for future use
                                    </label>
                                <?php else: ?>
                                    <div class="paycheck-inline-note">This is a manual paycheck entry, so only this saved payment will be changed.</div>
                                <?php endif; ?>
                                <div class="paycheck-inline-actions">
                                    <button class="button" type="submit">Save Changes</button>
                                    <button class="button secondary paycheck-inline-cancel" type="button" data-target="paycheck-inline-editor-<?= (int)$item['id'] ?>">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="5" class="muted">No bill payments saved on this paycheck yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<script>
(function(){
    var toggleButtons = document.querySelectorAll('.paycheck-inline-edit-toggle');
    var cancelButtons = document.querySelectorAll('.paycheck-inline-cancel');

    function closeAllEditors(exceptId) {
        var rows = document.querySelectorAll('.paycheck-inline-editor-row');
        for (var i = 0; i < rows.length; i++) {
            if (exceptId && rows[i].id === exceptId) {
                continue;
            }
            rows[i].classList.remove('is-open');
        }
    }

    for (var i = 0; i < toggleButtons.length; i++) {
        toggleButtons[i].addEventListener('click', function(){
            var targetId = this.getAttribute('data-target');
            if (!targetId) {
                return;
            }
            var row = document.getElementById(targetId);
            if (!row) {
                return;
            }
            var willOpen = !row.classList.contains('is-open');
            closeAllEditors(targetId);
            if (willOpen) {
                row.classList.add('is-open');
                var firstInput = row.querySelector('input:not([type=hidden]), textarea, select');
                if (firstInput) {
                    firstInput.focus();
                }
            } else {
                row.classList.remove('is-open');
            }
        });
    }

    for (var j = 0; j < cancelButtons.length; j++) {
        cancelButtons[j].addEventListener('click', function(){
            var targetId = this.getAttribute('data-target');
            var row = targetId ? document.getElementById(targetId) : null;
            if (row) {
                row.classList.remove('is-open');
            }
        });
    }
})();
</script>
<?php require __DIR__ . '/footer.php'; ?>
