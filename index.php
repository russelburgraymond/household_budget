<?php
require_once __DIR__ . '/functions.php';
ensure_schema();
$conn = db();
$error = '';
$prefillPaycheckDate = isset($_GET['paycheck_date']) ? (string)$_GET['paycheck_date'] : date('Y-m-d');
$prefillPaycheckAmount = isset($_GET['paycheck_amount']) ? (float)$_GET['paycheck_amount'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_paycheck') {
    $paycheckDate = isset($_POST['paycheck_date']) ? $_POST['paycheck_date'] : '';
    $paycheckAmount = isset($_POST['paycheck_amount']) ? (float)$_POST['paycheck_amount'] : 0;
    $itemsJson = isset($_POST['pay_items_json']) ? $_POST['pay_items_json'] : '[]';
    $items = json_decode($itemsJson, true);
    if (!is_array($items)) {
        $items = array();
    }

    $conn->begin_transaction();
    try {
        $existingPaycheck = get_paycheck_by_date($paycheckDate);
        if ($existingPaycheck) {
            $paycheckId = (int)$existingPaycheck['id'];
            $stmt = $conn->prepare("UPDATE paychecks SET paycheck_amount = ? WHERE id = ?");
            $stmt->bind_param('di', $paycheckAmount, $paycheckId);
            $stmt->execute();
            $sortOrder = next_paycheck_sort_order($paycheckId);
        } else {
            $stmt = $conn->prepare("INSERT INTO paychecks (paycheck_date, paycheck_amount) VALUES (?, ?)");
            $stmt->bind_param('sd', $paycheckDate, $paycheckAmount);
            $stmt->execute();
            $paycheckId = (int)$conn->insert_id;
            $sortOrder = 1;
        }

        foreach ($items as $item) {
            $recurringBillId = !empty($item['recurring_bill_id']) ? (int)$item['recurring_bill_id'] : null;
            $dueDate = !empty($item['due_date']) ? $item['due_date'] : $paycheckDate;
            $billName = trim(isset($item['bill_name']) ? (string)$item['bill_name'] : '');
            $amount = isset($item['amount']) ? (float)$item['amount'] : 0;
            $notes = trim(isset($item['notes']) ? (string)$item['notes'] : '');
            $isManual = !empty($item['is_manual']) ? 1 : 0;
            if ($billName === '') {
                continue;
            }

            $stmt = $conn->prepare("INSERT INTO paycheck_payments (paycheck_id, recurring_bill_id, due_date, bill_name, amount, notes, sort_order, is_manual)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissdsii', $paycheckId, $recurringBillId, $dueDate, $billName, $amount, $notes, $sortOrder, $isManual);
            $stmt->execute();
            $paymentId = (int)$conn->insert_id;

            if ($recurringBillId) {
                $stmt2 = $conn->prepare("INSERT INTO bill_paid_instances (recurring_bill_id, due_date, paycheck_payment_id) VALUES (?, ?, ?)");
                $stmt2->bind_param('isi', $recurringBillId, $dueDate, $paymentId);
                $stmt2->execute();
            }
            $sortOrder++;
        }

        $conn->commit();
        redirect('paycheck_view.php?id=' . $paycheckId . '&saved=1');
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

$upcoming = upcoming_bills();
require __DIR__ . '/header.php';
?>
<h1>Home</h1>
<?php if (!empty($error)): ?>
    <div class="flash error"><?= h($error) ?></div>
<?php endif; ?>
<div class="grid">
    <div class="card" id="current-paycheck-card">
        <div class="card-header">
            <div>
                <h2>Current Paycheck</h2>
            </div>
        </div>
        <form method="post" id="paycheck-form" class="form-stack">
            <input type="hidden" name="action" value="save_paycheck">
            <input type="hidden" name="pay_items_json" id="pay_items_json" value="[]">

            <div class="grid-3">
                <div>
                    <label for="paycheck_date">Paycheck Date</label>
                    <input type="date" id="paycheck_date" name="paycheck_date" value="<?= h($prefillPaycheckDate) ?>" required>
                </div>
                <div>
                    <label for="paycheck_amount">Amount</label>
                    <input type="number" step="0.01" id="paycheck_amount" name="paycheck_amount" value="<?= h(number_format((float)$prefillPaycheckAmount, 2, '.', '')) ?>" required>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button class="button" type="submit">Pay Bills</button>
                </div>
            </div>

            <div class="section-title">
                <h3>Bills to Pay</h3>
            </div>
            <div class="dropzone" id="pay-dropzone">
                <div class="dropzone-empty">Drop bills here or double-click a bill on the right</div>
            </div>
            <p class="totals">Selected Bills Total: $<span id="selected-total">0.00</span><br>Remaining: $<span id="remaining-total">0.00</span></p>

            <div class="section-title">
                <h3>Manual Bill Entry</h3>
            </div>
            <div class="grid-3">
                <div>
                    <label for="manual_due_date">Date</label>
                    <input type="date" id="manual_due_date" value="<?= h($prefillPaycheckDate) ?>">
                </div>
                <div>
                    <label for="manual_bill_name">Bill</label>
                    <input type="text" id="manual_bill_name" placeholder="Bill">
                </div>
                <div>
                    <label for="manual_amount">Amount</label>
                    <input type="number" step="0.01" id="manual_amount" placeholder="Amount">
                </div>
            </div>
            <div>
                <label for="manual_notes">Notes</label>
                <textarea id="manual_notes" placeholder="Notes"></textarea>
            </div>
            <div>
                <button class="button secondary" type="button" id="add-manual-payment">Add Payment</button>
            </div>
        </form>
    </div>

    <div class="card" id="upcoming-bills-card">
        <div class="card-header">
            <div>
                <h2>Upcoming Bills</h2>
                <p class="helper-text">Current month and following month. Bills marked Do Not Repeat only show the next unpaid due instance.</p>
            </div>
        </div>
        <div id="upcoming-list" class="upcoming-list-scroll">
            <?php foreach ($upcoming as $item): ?>
                <?php $billKey = (!empty($item['recurring_bill_id']) ? (int)$item['recurring_bill_id'] : 0) . '|' . $item['due_date'] . '|' . $item['bill_name']; ?>
                <div class="draggable-row"
                    draggable="true"
                    data-bill-key="<?= h($billKey) ?>"
                    data-bill='<?= h(json_encode($item, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT)) ?>'>
                    <div class="bill-name"><?= h($item['bill_name']) ?></div>
                    <div class="bill-meta">Due: <?= h($item['due_date']) ?> | $<?= number_format((float)$item['amount'], 2) ?> | <?= h(recurrence_label($item['recurrence_type'])) ?></div>
                    <?php if (!empty($item['notes'])): ?><div class="bill-note"><?= h($item['notes']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($upcoming)): ?>
                <div class="summary-box muted">No upcoming unpaid bills found in the current/following month window.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function(){
    var payItems = [];
    var dropzone = document.getElementById('pay-dropzone');
    var hidden = document.getElementById('pay_items_json');
    var totalEl = document.getElementById('selected-total');
    var remainingEl = document.getElementById('remaining-total');
    var paycheckAmountInput = document.getElementById('paycheck_amount');
    var upcomingList = document.getElementById('upcoming-list');
    var currentPaycheckCard = document.getElementById('current-paycheck-card');
    var upcomingBillsCard = document.getElementById('upcoming-bills-card');

    function syncPanelHeights() {
        if (!currentPaycheckCard || !upcomingBillsCard || !upcomingList) {
            return;
        }

        upcomingBillsCard.style.height = '';
        upcomingList.style.maxHeight = '';

        var targetHeight = currentPaycheckCard.offsetHeight;
        if (!targetHeight) {
            return;
        }

        upcomingBillsCard.style.height = targetHeight + 'px';

        var cardStyle = window.getComputedStyle(upcomingBillsCard);
        var cardPaddingTop = parseFloat(cardStyle.paddingTop) || 0;
        var cardPaddingBottom = parseFloat(cardStyle.paddingBottom) || 0;
        var header = upcomingBillsCard.querySelector('.card-header');
        var helper = upcomingBillsCard.querySelector('.helper-text');
        var headerHeight = header ? header.offsetHeight : 0;
        var helperHeight = helper ? helper.offsetHeight : 0;
        var helperMarginBottom = helper ? (parseFloat(window.getComputedStyle(helper).marginBottom) || 0) : 0;
        var listMaxHeight = targetHeight - cardPaddingTop - cardPaddingBottom - headerHeight - helperHeight - helperMarginBottom;

        if (listMaxHeight > 120) {
            upcomingList.style.maxHeight = listMaxHeight + 'px';
        }
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getItemKey(item) {
        var recurringBillId = item && item.recurring_bill_id ? item.recurring_bill_id : 0;
        return String(recurringBillId) + '|' + String(item && item.due_date ? item.due_date : '') + '|' + String(item && item.bill_name ? item.bill_name : '');
    }

    function markUpcomingVisibility() {
        var selectedMap = {};
        for (var i = 0; i < payItems.length; i++) {
            selectedMap[getItemKey(payItems[i])] = true;
        }

        var shownDoNotRepeatMap = {};
        var rows = upcomingList.querySelectorAll('.draggable-row');
        for (var r = 0; r < rows.length; r++) {
            var row = rows[r];
            var key = row.getAttribute('data-bill-key') || '';
            var data = null;
            try {
                data = JSON.parse(row.getAttribute('data-bill') || '{}');
            } catch (e) {
                data = {};
            }

            if (selectedMap[key]) {
                row.classList.add('is-hidden');
                continue;
            }

            var isDoNotRepeat = parseInt(data && data.do_not_repeat ? data.do_not_repeat : 0, 10) === 1;
            var recurringBillId = parseInt(data && data.recurring_bill_id ? data.recurring_bill_id : 0, 10);
            if (isDoNotRepeat && recurringBillId > 0) {
                if (shownDoNotRepeatMap[recurringBillId]) {
                    row.classList.add('is-hidden');
                    continue;
                }
                shownDoNotRepeatMap[recurringBillId] = true;
            }

            row.classList.remove('is-hidden');
        }
    }

    function renderPayItem(item, index) {
        var amount = parseFloat(item.amount || 0);
        var editing = !!item._editing;
        var html = '<div class="pay-item">';
        if (editing) {
            html += '<div class="grid-3">'
                + '<div><label>Due Date</label><input type="date" class="edit-due-date" data-index="' + index + '" value="' + escapeHtml(item.due_date) + '"></div>'
                + '<div><label>Bill</label><input type="text" class="edit-bill-name" data-index="' + index + '" value="' + escapeHtml(item.bill_name) + '"></div>'
                + '<div><label>Amount</label><input type="number" step="0.01" class="edit-amount" data-index="' + index + '" value="' + amount.toFixed(2) + '"></div>'
                + '</div>'
                + '<div><label>Notes</label><textarea class="edit-notes" data-index="' + index + '">' + escapeHtml(item.notes || '') + '</textarea></div>'
                + '<div class="pay-item-actions">'
                + '<button type="button" class="button secondary save-pay-item" data-index="' + index + '">Save</button> '
                + '<button type="button" class="button ghost cancel-pay-item" data-index="' + index + '">Cancel</button> '
                + '<button type="button" class="button ghost remove-pay-item" data-index="' + index + '">Remove</button>'
                + '</div>';
        } else {
            html += '<div class="pay-item-top">'
                + '<div>'
                + '<div class="bill-name">' + escapeHtml(item.bill_name) + '</div>'
                + '<div class="bill-meta">Due: ' + escapeHtml(item.due_date) + ' | $' + amount.toFixed(2) + '</div>'
                + (item.notes ? '<div class="bill-note">' + escapeHtml(item.notes) + '</div>' : '')
                + '</div>'
                + '</div>'
                + '<div class="pay-item-actions">'
                + '<button type="button" class="button secondary edit-pay-item" data-index="' + index + '">Edit</button> '
                + '<button type="button" class="button ghost remove-pay-item" data-index="' + index + '">Remove</button>'
                + '</div>';
        }
        html += '</div>';
        return html;
    }

    function updateRemaining(total) {
        if (!remainingEl) {
            return;
        }
        var paycheckAmount = paycheckAmountInput ? parseFloat(paycheckAmountInput.value || 0) : 0;
        remainingEl.textContent = (paycheckAmount - total).toFixed(2);
    }

    function sync() {
        var serializedItems = [];
        var total = 0;
        var html = '';
        for (var i = 0; i < payItems.length; i++) {
            var item = payItems[i];
            total += parseFloat(item.amount || 0);
            var cleanItem = {};
            for (var key in item) {
                if (Object.prototype.hasOwnProperty.call(item, key) && key !== '_editing' && key !== '_original') {
                    cleanItem[key] = item[key];
                }
            }
            serializedItems.push(cleanItem);
            html += renderPayItem(item, i);
        }

        hidden.value = JSON.stringify(serializedItems);

        if (!html) {
            html = '<div class="dropzone-empty">Drop bills here or double-click a bill on the right</div>';
        }

        dropzone.innerHTML = html;
        totalEl.textContent = total.toFixed(2);
        updateRemaining(total);
        markUpcomingVisibility();
        syncPanelHeights();
    }


    dropzone.addEventListener('click', function(e){
        var target = e.target;
        if (target.classList.contains('remove-pay-item')) {
            var removeIndex = parseInt(target.getAttribute('data-index'), 10);
            if (!isNaN(removeIndex)) {
                payItems.splice(removeIndex, 1);
                sync();
            }
            return;
        }

        if (target.classList.contains('edit-pay-item')) {
            var editIndex = parseInt(target.getAttribute('data-index'), 10);
            if (!isNaN(editIndex) && payItems[editIndex]) {
                payItems[editIndex]._original = {
                    due_date: payItems[editIndex].due_date,
                    bill_name: payItems[editIndex].bill_name,
                    amount: payItems[editIndex].amount,
                    notes: payItems[editIndex].notes
                };
                payItems[editIndex]._editing = true;
                sync();
            }
            return;
        }

        if (target.classList.contains('cancel-pay-item')) {
            var cancelIndex = parseInt(target.getAttribute('data-index'), 10);
            if (!isNaN(cancelIndex) && payItems[cancelIndex]) {
                if (payItems[cancelIndex]._original) {
                    payItems[cancelIndex].due_date = payItems[cancelIndex]._original.due_date;
                    payItems[cancelIndex].bill_name = payItems[cancelIndex]._original.bill_name;
                    payItems[cancelIndex].amount = payItems[cancelIndex]._original.amount;
                    payItems[cancelIndex].notes = payItems[cancelIndex]._original.notes;
                }
                delete payItems[cancelIndex]._original;
                delete payItems[cancelIndex]._editing;
                sync();
            }
            return;
        }

        if (target.classList.contains('save-pay-item')) {
            var saveIndex = parseInt(target.getAttribute('data-index'), 10);
            if (!isNaN(saveIndex) && payItems[saveIndex]) {
                var itemWrap = target.closest('.pay-item');
                var dueInput = itemWrap.querySelector('.edit-due-date');
                var nameInput = itemWrap.querySelector('.edit-bill-name');
                var amountInput = itemWrap.querySelector('.edit-amount');
                var notesInput = itemWrap.querySelector('.edit-notes');
                payItems[saveIndex].due_date = dueInput ? dueInput.value : payItems[saveIndex].due_date;
                payItems[saveIndex].bill_name = nameInput ? nameInput.value : payItems[saveIndex].bill_name;
                payItems[saveIndex].amount = amountInput ? amountInput.value : payItems[saveIndex].amount;
                payItems[saveIndex].notes = notesInput ? notesInput.value : payItems[saveIndex].notes;
                delete payItems[saveIndex]._original;
                delete payItems[saveIndex]._editing;
                sync();
            }
        }
    });

    function addBill(item) {
        var key = getItemKey(item);
        for (var i = 0; i < payItems.length; i++) {
            if (getItemKey(payItems[i]) === key) {
                return;
            }
        }
        payItems.push(item);
        sync();
    }

    var draggables = document.querySelectorAll('.draggable-row');
    for (var i = 0; i < draggables.length; i++) {
        draggables[i].addEventListener('dragstart', function(e){
            e.dataTransfer.setData('text/plain', this.getAttribute('data-bill'));
        });
        draggables[i].addEventListener('dblclick', function(){
            addBill(JSON.parse(this.getAttribute('data-bill')));
        });
    }

    dropzone.addEventListener('dragover', function(e){
        e.preventDefault();
        dropzone.classList.add('is-over');
    });
    dropzone.addEventListener('dragleave', function(){
        dropzone.classList.remove('is-over');
    });
    dropzone.addEventListener('drop', function(e){
        e.preventDefault();
        dropzone.classList.remove('is-over');
        var data = e.dataTransfer.getData('text/plain');
        if (data) {
            addBill(JSON.parse(data));
        }
    });

    document.getElementById('add-manual-payment').addEventListener('click', function(){
        var dueDate = document.getElementById('manual_due_date').value;
        var billName = document.getElementById('manual_bill_name').value;
        var amount = document.getElementById('manual_amount').value;
        var notes = document.getElementById('manual_notes').value;
        if (!billName || !amount) {
            alert('Please enter a bill name and amount.');
            return;
        }
        addBill({
            recurring_bill_id: null,
            due_date: dueDate,
            bill_name: billName,
            amount: amount,
            notes: notes,
            recurrence_type: '',
            do_not_repeat: 0,
            is_manual: 1
        });
        document.getElementById('manual_bill_name').value = '';
        document.getElementById('manual_amount').value = '';
        document.getElementById('manual_notes').value = '';
    });

    if (paycheckAmountInput) {
        paycheckAmountInput.addEventListener('input', function(){
            var total = parseFloat(totalEl.textContent || 0);
            updateRemaining(total);
        });
    }

    sync();
    syncPanelHeights();
    window.addEventListener('resize', syncPanelHeights);
})();
</script>
<?php require __DIR__ . '/footer.php'; ?>
