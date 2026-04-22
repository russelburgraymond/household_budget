<?php
require_once __DIR__ . '/functions.php';
ensure_schema();
$conn = db();
$q = trim(isset($_GET['q']) ? (string)$_GET['q'] : '');
$results = array();
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT p.id AS paycheck_id, p.paycheck_date, p.paycheck_amount,
                                   pp.bill_name, pp.due_date, pp.amount, pp.notes
                            FROM paycheck_payments pp
                            INNER JOIN paychecks p ON p.id = pp.paycheck_id
                            WHERE pp.bill_name LIKE ?
                               OR pp.notes LIKE ?
                               OR CAST(pp.amount AS CHAR) LIKE ?
                               OR pp.due_date LIKE ?
                            ORDER BY p.paycheck_date DESC, pp.due_date DESC");
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $results = stmt_fetch_all_assoc($stmt);
}
$paychecks = array();
$result = $conn->query("SELECT id, paycheck_date, paycheck_amount FROM paychecks ORDER BY paycheck_date DESC, id DESC LIMIT 100");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $paychecks[] = $row;
    }
}
require __DIR__ . '/header.php';
?>
<h1>Search / Paycheck History</h1>
<div class="grid">
    <div class="card">
        <h2>Search Payments</h2>
        <form method="get">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by bill, date, amount, or notes">
            <button class="button" type="submit">Search</button>
        </form>
        <table style="margin-top:14px;">
            <thead>
                <tr>
                    <th>Paycheck</th>
                    <th>Bill</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td><a href="paycheck_view.php?id=<?= (int)$row['paycheck_id'] ?>"><?= h($row['paycheck_date']) ?></a></td>
                    <td><?= h($row['bill_name']) ?></td>
                    <td><?= h($row['due_date']) ?></td>
                    <td>$<?= number_format((float)$row['amount'], 2) ?></td>
                    <td><?= h($row['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($q !== '' && !$results): ?>
                <tr><td colspan="5" class="muted">No matches found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Recent Paychecks</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Paid</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paychecks as $p): ?>
                <tr>
                    <td><a href="paycheck_view.php?id=<?= (int)$p['id'] ?>"><?= h($p['paycheck_date']) ?></a></td>
                    <td>$<?= number_format((float)$p['paycheck_amount'], 2) ?></td>
                    <td>$<?= number_format(paycheck_total_paid((int)$p['id']), 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$paychecks): ?>
                <tr><td colspan="3" class="muted">No paychecks saved yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
