<?php
require_once __DIR__ . '/functions.php';
ensure_schema();
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : 'save_bill';

    if ($action === 'move_bill_status') {
        $billId = isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;
        $targetStatus = isset($_POST['target_status']) ? (int)$_POST['target_status'] : 0;
        if ($billId > 0) {
            $stmt = $conn->prepare("UPDATE recurring_bills SET is_active=? WHERE id=?");
            $stmt->bind_param('ii', $targetStatus, $billId);
            $stmt->execute();
        }
        redirect('admin_bills.php?status_moved=1');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $billName = trim(isset($_POST['bill_name']) ? (string)$_POST['bill_name'] : '');
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $allowedRecurrenceTypes = array('single', 'weekly', 'biweekly', 'monthly', 'quarterly');
    $recurrenceType = isset($_POST['recurrence_type']) ? (string)$_POST['recurrence_type'] : 'monthly';
    if (!in_array($recurrenceType, $allowedRecurrenceTypes, true)) {
        $recurrenceType = 'monthly';
    }
    $firstDueDate = isset($_POST['first_due_date']) ? $_POST['first_due_date'] : date('Y-m-d');
    $notes = trim(isset($_POST['notes']) ? (string)$_POST['notes'] : '');
    $doNotRepeat = !empty($_POST['do_not_repeat']) ? 1 : 0;
    $isActive = !empty($_POST['is_active']) ? 1 : 0;

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE recurring_bills SET bill_name=?, amount=?, recurrence_type=?, first_due_date=?, notes=?, do_not_repeat=?, is_active=? WHERE id=?");
        $stmt->bind_param('sdsssiii', $billName, $amount, $recurrenceType, $firstDueDate, $notes, $doNotRepeat, $isActive, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO recurring_bills (bill_name, amount, recurrence_type, first_due_date, notes, do_not_repeat, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sdsssii', $billName, $amount, $recurrenceType, $firstDueDate, $notes, $doNotRepeat, $isActive);
        $stmt->execute();
    }
    redirect('admin_bills.php?saved=1');
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = array(
    'id' => 0,
    'bill_name' => '',
    'amount' => '0.00',
    'recurrence_type' => 'monthly',
    'first_due_date' => date('Y-m-d'),
    'notes' => '',
    'do_not_repeat' => 0,
    'is_active' => 1,
);
if ($editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM recurring_bills WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $rows = stmt_fetch_all_assoc($stmt);
    if (!empty($rows)) {
        $edit = $rows[0];
    }
}

$bills = array();
$result = $conn->query("SELECT * FROM recurring_bills ORDER BY is_active DESC, bill_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bills[] = $row;
    }
}

$activeBills = array();
$inactiveBills = array();
foreach ($bills as $bill) {
    if (!empty($bill['is_active'])) {
        $activeBills[] = $bill;
    } else {
        $inactiveBills[] = $bill;
    }
}

require __DIR__ . '/header.php';
?>
<style>
.bill-status-grid{display:grid;grid-template-columns:1fr;gap:16px}
.bill-list-zone{
    min-height:170px;
    border:1px dashed var(--border-strong);
    border-radius:14px;
    padding:14px;
    background:linear-gradient(180deg, rgba(9,16,30,.92) 0%, rgba(10,18,34,.98) 100%);
    transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
.bill-list-zone.is-over{
    border-color:var(--primary);
    box-shadow:0 0 0 4px rgba(59,130,246,.10);
    background:linear-gradient(180deg, rgba(14,27,52,.98) 0%, rgba(12,22,42,.98) 100%);
}
.bill-zone-header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
.bill-zone-title{font-size:1.15rem;font-weight:700;color:#f8fafc}
.bill-zone-count{font-size:.92rem;color:var(--text-soft);padding:4px 10px;border-radius:999px;background:#0f172a;border:1px solid var(--border)}
.bill-zone-helper{margin:0 0 12px;color:var(--text-soft)}
.status-bill-card{
    position:relative;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:10px 12px;
}
.status-bill-card .bill-body{
    min-width:0;
    flex:1 1 auto;
}
.status-bill-card .bill-name{
    line-height:1.2;
    margin:0 0 4px;
}
.status-bill-card .bill-meta{
    margin:0;
    line-height:1.25;
}
.status-bill-card[data-status="0"]{opacity:.92}
.status-bill-card.dragging{opacity:.45;transform:scale(.98)}
.bill-chip-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:7px}
.bill-chip{font-size:.78rem;line-height:1;padding:5px 8px;border-radius:999px;background:#0f172a;border:1px solid var(--border);color:#cbd5e1}
.bill-card-actions{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    justify-content:flex-start;
    gap:6px;
    margin-top:0;
    flex:0 0 auto;
}
.bill-card-actions .button{padding:8px 12px}
.empty-zone-note{min-height:92px;display:flex;align-items:center;justify-content:center;text-align:center;color:var(--text-soft);border-radius:12px;background:rgba(15,23,42,.38)}
.drag-instruction{margin-top:10px;font-size:.9rem;color:var(--text-soft)}
@media (max-width: 980px){
    .bill-zone-header{align-items:flex-start;flex-direction:column}
}

/* ===== Compact Bills Admin Tiles ===== */
.bill-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    align-items: start;
}
.bill-column {
    background: rgba(15, 23, 42, 0.55);
    border: 1px solid rgba(96, 165, 250, 0.16);
    border-radius: 14px;
    padding: 14px;
}
.bill-column h2,
.bill-column h3 {
    margin: 0 0 6px 0;
}
.bill-column .column-help {
    margin: 0 0 12px 0;
    color: #9db0ca;
    font-size: 13px;
}
.bill-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 72px;
}
.status-bill-list{max-height:420px;overflow-y:auto;padding-right:6px;}
.status-bill-list::-webkit-scrollbar{width:10px}
.status-bill-list::-webkit-scrollbar-track{background:#0f172a;border-radius:999px}
.status-bill-list::-webkit-scrollbar-thumb{background:#334155;border-radius:999px}
.status-bill-list::-webkit-scrollbar-thumb:hover{background:#475569}
.bill-item,
.bill-card {
    background: #16243a;
    border: 1px solid #31507a;
    border-radius: 12px;
    padding: 10px 12px;
    margin: 0;
    min-height: 0 !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.bill-item .bill-main,
.bill-card .bill-main,
.bill-left {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    flex: 1 1 auto;
}
.bill-item .bill-title,
.bill-card .bill-title,
.bill-title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
    margin: 0;
}
.bill-item .bill-meta,
.bill-card .bill-meta,
.bill-meta {
    font-size: 12px;
    line-height: 1.3;
    color: #aac0dd;
    margin: 0;
}
.bill-right,
.bill-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}
.bill-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}
.bill-status.active {
    background: rgba(34, 197, 94, 0.16);
    color: #86efac;
    border: 1px solid rgba(34, 197, 94, 0.24);
}
.bill-status.inactive {
    background: rgba(148, 163, 184, 0.16);
    color: #d1d5db;
    border: 1px solid rgba(148, 163, 184, 0.24);
}
.bill-item .btn,
.bill-card .btn,
.bill-item button,
.bill-card button {
    margin: 0;
}
@media (max-width: 980px) {
    .bill-columns {
        grid-template-columns: 1fr;
    }
}


/* Clean edit icon */
.edit-icon{
    background:none;
    border:none;
    color:#9ca3af;
    cursor:pointer;
    padding:4px;
    display:flex;
    align-items:center;
    justify-content:center;
}

.edit-icon:hover{
    color:#4a90e2;
    background:rgba(255,255,255,0.05);
    border-radius:6px;
}


/* Pencil edit link */
.edit-icon{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:34px;
    height:34px;
    border-radius:10px;
    color:#cbd5e1;
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(148,163,184,0.16);
    text-decoration:none;
    transition:background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease;
}
.edit-icon:hover{
    color:#93c5fd;
    background:rgba(59,130,246,0.10);
    border-color:rgba(96,165,250,0.35);
    transform:translateY(-1px);
}
.edit-icon svg{
    width:16px;
    height:16px;
    display:block;
}

</style>
<h1>Recurring Bills Admin</h1>
<?php if (!empty($_GET['saved'])): ?><div class="flash">Bill saved.</div><?php endif; ?>
<?php if (!empty($_GET['status_moved'])): ?><div class="flash">Bill status updated.</div><?php endif; ?>
<div class="grid">
    <div class="card">
        <div class="card-header">
            <div>
                <h2><?= $editId ? 'Edit Bill' : 'Add Bill' ?></h2>
                <p class="helper-text">Create recurring bills, or choose Single (One Time) to add a bill that should appear only once on its due date until it is paid.</p>
            </div>
        </div>
        <form method="post" class="form-stack">
            <input type="hidden" name="action" value="save_bill">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <div class="grid-3">
                <div>
                    <label for="bill_name">Bill</label>
                    <input type="text" id="bill_name" name="bill_name" value="<?= h($edit['bill_name']) ?>" required>
                </div>
                <div>
                    <label for="amount">Amount</label>
                    <input type="number" step="0.01" id="amount" name="amount" value="<?= h((string)$edit['amount']) ?>" required>
                </div>
                <div>
                    <label for="first_due_date">First Due Date</label>
                    <input type="date" id="first_due_date" name="first_due_date" value="<?= h($edit['first_due_date']) ?>" required>
                </div>
            </div>
            <div class="grid-3">
                <div>
                    <label for="recurrence_type">Repeats</label>
                    <select id="recurrence_type" name="recurrence_type">
                        <?php foreach (array('single'=>'Single (One Time)','weekly'=>'Weekly','biweekly'=>'Bi-Weekly','monthly'=>'Monthly','quarterly'=>'Every 3 Months') as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= $edit['recurrence_type'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="summary-box">
                    <label><input type="checkbox" name="do_not_repeat" value="1" <?= !empty($edit['do_not_repeat']) ? 'checked' : '' ?>> Do Not Repeat</label>
                    <div class="small muted">Only show the first unpaid upcoming instance on the home page.</div>
                </div>
                <div class="summary-box">
                    <label><input type="checkbox" name="is_active" value="1" <?= !empty($edit['is_active']) ? 'checked' : '' ?>> Active</label>
                    <div class="small muted">Inactive bills remain saved but no longer appear in upcoming bill lists.</div>
                </div>
            </div>
            <div>
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes"><?= h($edit['notes']) ?></textarea>
            </div>
            <div class="actions">
                <button class="button" type="submit">Save Bill</button>
                <a class="button secondary" href="admin_bills.php">Clear</a>
            </div>
        </form>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <div>
                <h2>Bill Lists</h2>
                <p class="helper-text">Drag bills between Active and Inactive to change their status. Active bills appear on the home page. Inactive bills stay saved but do not appear there.</p>
            </div>
        </div>

        <div class="bill-status-grid">
            <div class="bill-list-zone" data-target-status="1">
                <div class="bill-zone-header">
                    <div class="bill-zone-title">Active Bills</div>
                    <div class="bill-zone-count"><?= count($activeBills) ?> total</div>
                </div>
                <p class="bill-zone-helper">Bills in this list are available for upcoming due dates and paycheck selection.</p>
                <div class="status-bill-list" id="activeBillsList">
                    <?php foreach ($activeBills as $bill): ?>
                        <div class="draggable-row status-bill-card" draggable="true" data-bill-id="<?= (int)$bill['id'] ?>" data-status="1">
                            <div class="bill-body">
                                <div class="bill-name"><?= h($bill['bill_name']) ?></div>
                                <div class="bill-meta">$<?= number_format((float)$bill['amount'], 2) ?> | <?= h(recurrence_label($bill['recurrence_type'])) ?> | First Due: <?= h($bill['first_due_date']) ?></div>
                                <?php if (!empty($bill['notes'])): ?><div class="bill-note"><?= h($bill['notes']) ?></div><?php endif; ?>
                                <div class="bill-chip-row">
                                    <?php if (!empty($bill['do_not_repeat'])): ?><span class="bill-chip">Do Not Repeat</span><?php endif; ?>
                                    <span class="bill-chip">Active</span>
                                </div>
                            </div>
                            <div class="bill-card-actions">
                                <a class="edit-icon" href="admin_bills.php?edit=<?= (int)$bill['id'] ?>" title="Edit" aria-label="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$activeBills): ?>
                        <div class="empty-zone-note">No active bills. Drag an inactive bill here to reactivate it.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bill-list-zone" data-target-status="0">
                <div class="bill-zone-header">
                    <div class="bill-zone-title">Inactive Bills</div>
                    <div class="bill-zone-count"><?= count($inactiveBills) ?> total</div>
                </div>
                <p class="bill-zone-helper">Drag a bill here when it is paid off or temporarily not needed.</p>
                <div class="status-bill-list" id="inactiveBillsList">
                    <?php foreach ($inactiveBills as $bill): ?>
                        <div class="draggable-row status-bill-card" draggable="true" data-bill-id="<?= (int)$bill['id'] ?>" data-status="0">
                            <div class="bill-body">
                                <div class="bill-name"><?= h($bill['bill_name']) ?></div>
                                <div class="bill-meta">$<?= number_format((float)$bill['amount'], 2) ?> | <?= h(recurrence_label($bill['recurrence_type'])) ?> | First Due: <?= h($bill['first_due_date']) ?></div>
                                <?php if (!empty($bill['notes'])): ?><div class="bill-note"><?= h($bill['notes']) ?></div><?php endif; ?>
                                <div class="bill-chip-row">
                                    <?php if (!empty($bill['do_not_repeat'])): ?><span class="bill-chip">Do Not Repeat</span><?php endif; ?>
                                    <span class="bill-chip">Inactive</span>
                                </div>
                            </div>
                            <div class="bill-card-actions">
                                <a class="edit-icon" href="admin_bills.php?edit=<?= (int)$bill['id'] ?>" title="Edit" aria-label="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$inactiveBills): ?>
                        <div class="empty-zone-note">No inactive bills. Drag an active bill here to deactivate it.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="drag-instruction">Tip: drag a bill card and drop it into the other section to switch its status.</div>
    </div>
</div>

<form method="post" id="status-move-form" style="display:none;">
    <input type="hidden" name="action" value="move_bill_status">
    <input type="hidden" name="bill_id" id="status_move_bill_id" value="0">
    <input type="hidden" name="target_status" id="status_move_target_status" value="0">
</form>

<script>
(function(){
    var draggedCard = null;
    var cards = document.querySelectorAll('.status-bill-card');
    var zones = document.querySelectorAll('.bill-list-zone');
    var form = document.getElementById('status-move-form');
    var billIdInput = document.getElementById('status_move_bill_id');
    var statusInput = document.getElementById('status_move_target_status');

    for (var i = 0; i < cards.length; i++) {
        cards[i].addEventListener('dragstart', function(e){
            draggedCard = this;
            this.classList.add('dragging');
            e.dataTransfer.setData('text/plain', this.getAttribute('data-bill-id'));
        });
        cards[i].addEventListener('dragend', function(){
            this.classList.remove('dragging');
            draggedCard = null;
        });
    }

    for (var z = 0; z < zones.length; z++) {
        zones[z].addEventListener('dragover', function(e){
            e.preventDefault();
            this.classList.add('is-over');
        });
        zones[z].addEventListener('dragleave', function(){
            this.classList.remove('is-over');
        });
        zones[z].addEventListener('drop', function(e){
            e.preventDefault();
            this.classList.remove('is-over');
            var targetStatus = this.getAttribute('data-target-status');
            var billId = e.dataTransfer.getData('text/plain');
            if (!billId || !draggedCard) {
                return;
            }
            var currentStatus = draggedCard.getAttribute('data-status');
            if (String(currentStatus) === String(targetStatus)) {
                return;
            }
            billIdInput.value = billId;
            statusInput.value = targetStatus;
            form.submit();
        });
    }

})();
</script>
<?php require __DIR__ . '/footer.php'; ?>
