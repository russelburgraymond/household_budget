<?php
require_once __DIR__ . '/schema.php';

function recurrence_interval_spec($type) {
    switch ($type) {
        case 'weekly':
            return 'P7D';
        case 'biweekly':
            return 'P14D';
        case 'monthly':
            return 'P1M';
        case 'quarterly':
            return 'P3M';
        default:
            return null;
    }
}

function recurrence_label($type) {
    switch ($type) {
        case 'single':
            return 'Single';
        case 'weekly':
            return 'Weekly';
        case 'biweekly':
            return 'Bi-Weekly';
        case 'monthly':
            return 'Monthly';
        case 'quarterly':
            return 'Every 3 Months';
        default:
            return ucfirst($type);
    }
}

function generate_due_dates($bill, $rangeStart, $rangeEnd, $limit = 60) {
    $dates = array();
    $current = new DateTimeImmutable($bill['first_due_date']);

    if (($bill['recurrence_type'] ?? '') === 'single') {
        $dueDate = $current->format('Y-m-d');
        $startDate = $rangeStart instanceof DateTimeInterface ? $rangeStart->format('Y-m-d') : (string)$rangeStart;
        $endDate = $rangeEnd instanceof DateTimeInterface ? $rangeEnd->format('Y-m-d') : (string)$rangeEnd;

        if ($dueDate >= $startDate && $dueDate <= $endDate) {
            $dates[] = $dueDate;
        }
        return $dates;
    }

    $intervalSpec = recurrence_interval_spec($bill['recurrence_type']);
    if (!$intervalSpec) {
        return $dates;
    }

    $interval = new DateInterval($intervalSpec);
    $safety = 0;

    while ($current < $rangeStart && $safety < 1000) {
        $current = $current->add($interval);
        $safety++;
    }

    while ($current <= $rangeEnd && count($dates) < $limit && $safety < 2000) {
        $dates[] = $current->format('Y-m-d');
        $current = $current->add($interval);
        $safety++;
    }

    return $dates;
}

function get_paid_due_dates_map() {
    $conn = db();
    $rows = array();
    $result = $conn->query("SELECT recurring_bill_id, due_date FROM bill_paid_instances");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $map = array();
    foreach ($rows as $row) {
        $map[(int)$row['recurring_bill_id'] . '|' . $row['due_date']] = true;
    }
    return $map;
}

function upcoming_bills($rangeStart = null, $rangeEnd = null) {
    ensure_schema();
    if ($rangeStart === null) {
        $rangeStart = new DateTimeImmutable(date('Y-m-01'));
    }
    if ($rangeEnd === null) {
        $rangeEnd = $rangeStart->modify('first day of next month')->modify('last day of this month');
    }

    $conn = db();
    $result = $conn->query("SELECT * FROM recurring_bills WHERE is_active = 1 ORDER BY bill_name ASC");
    $bills = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bills[] = $row;
        }
    }
    $paidMap = get_paid_due_dates_map();

    $items = array();
    foreach ($bills as $bill) {
        $doNotRepeat = (int)($bill['do_not_repeat'] ?? 0) === 1;

        if ($doNotRepeat) {
            $maxUnpaidInstances = 5;
            $current = new DateTimeImmutable($bill['first_due_date']);
            $intervalSpec = recurrence_interval_spec($bill['recurrence_type']);
            $interval = $intervalSpec ? new DateInterval($intervalSpec) : null;
            $safety = 0;
            $unpaidFound = 0;

            while ($current < $rangeStart && $interval && $safety < 1000) {
                $current = $current->add($interval);
                $safety++;
            }

            while ($unpaidFound < $maxUnpaidInstances && $safety < 3000) {
                $dueDate = $current->format('Y-m-d');

                if (($bill['recurrence_type'] ?? '') === 'single') {
                    if ($dueDate >= $rangeStart->format('Y-m-d') && !isset($paidMap[$bill['id'] . '|' . $dueDate])) {
                        $items[] = array(
                            'recurring_bill_id' => (int)$bill['id'],
                            'due_date' => $dueDate,
                            'bill_name' => $bill['bill_name'],
                            'amount' => $bill['amount'],
                            'notes' => $bill['notes'],
                            'recurrence_type' => $bill['recurrence_type'],
                            'do_not_repeat' => 1,
                        );
                    }
                    break;
                }

                if (!isset($paidMap[$bill['id'] . '|' . $dueDate])) {
                    $items[] = array(
                        'recurring_bill_id' => (int)$bill['id'],
                        'due_date' => $dueDate,
                        'bill_name' => $bill['bill_name'],
                        'amount' => $bill['amount'],
                        'notes' => $bill['notes'],
                        'recurrence_type' => $bill['recurrence_type'],
                        'do_not_repeat' => 1,
                    );
                    $unpaidFound++;
                }

                if (!$interval) {
                    break;
                }

                $current = $current->add($interval);
                $safety++;
            }

            continue;
        }

        $dueDates = generate_due_dates($bill, $rangeStart, $rangeEnd);
        foreach ($dueDates as $dueDate) {
            if (isset($paidMap[$bill['id'] . '|' . $dueDate])) {
                continue;
            }
            $items[] = array(
                'recurring_bill_id' => (int)$bill['id'],
                'due_date' => $dueDate,
                'bill_name' => $bill['bill_name'],
                'amount' => $bill['amount'],
                'notes' => $bill['notes'],
                'recurrence_type' => $bill['recurrence_type'],
                'do_not_repeat' => 0,
            );
        }
    }

    usort($items, function ($a, $b) {
        $cmp = strcmp($a['due_date'], $b['due_date']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($a['bill_name'], $b['bill_name']);
    });

    return $items;
}

function stmt_fetch_all_assoc($stmt) {
    $rows = array();
    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    }
    return $rows;
}


function get_paycheck_by_date($paycheckDate) {
    $conn = db();
    $stmt = $conn->prepare("SELECT * FROM paychecks WHERE paycheck_date = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('s', $paycheckDate);
    $stmt->execute();
    $rows = stmt_fetch_all_assoc($stmt);
    return !empty($rows) ? $rows[0] : null;
}

function next_paycheck_sort_order($paycheckId) {
    $conn = db();
    $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) AS max_sort_order FROM paycheck_payments WHERE paycheck_id = ?");
    $stmt->bind_param('i', $paycheckId);
    $stmt->execute();
    $rows = stmt_fetch_all_assoc($stmt);
    $maxSortOrder = !empty($rows) ? (int)$rows[0]['max_sort_order'] : 0;
    return $maxSortOrder + 1;
}

function get_paycheck($id) {
    $conn = db();
    $stmt = $conn->prepare("SELECT * FROM paychecks WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rows = stmt_fetch_all_assoc($stmt);
    return !empty($rows) ? $rows[0] : null;
}

function get_paycheck_payments($paycheckId) {
    $conn = db();
    $stmt = $conn->prepare("SELECT * FROM paycheck_payments WHERE paycheck_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->bind_param('i', $paycheckId);
    $stmt->execute();
    return stmt_fetch_all_assoc($stmt);
}

function get_paycheck_payment($paymentId) {
    $conn = db();
    $stmt = $conn->prepare("SELECT * FROM paycheck_payments WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $rows = stmt_fetch_all_assoc($stmt);
    return !empty($rows) ? $rows[0] : null;
}

function update_paycheck_payment($paymentId, $dueDate, $billName, $amount, $notes, $syncRecurringDefaults = false) {
    $conn = db();
    $payment = get_paycheck_payment($paymentId);
    if (!$payment) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE paycheck_payments SET due_date = ?, bill_name = ?, amount = ?, notes = ? WHERE id = ?");
    $stmt->bind_param('ssdsi', $dueDate, $billName, $amount, $notes, $paymentId);
    $stmt->execute();

    if ($syncRecurringDefaults && !empty($payment['recurring_bill_id']) && empty($payment['is_manual'])) {
        $recurringBillId = (int)$payment['recurring_bill_id'];
        $syncStmt = $conn->prepare("UPDATE recurring_bills SET bill_name = ?, amount = ?, notes = ? WHERE id = ?");
        $syncStmt->bind_param('sdsi', $billName, $amount, $notes, $recurringBillId);
        $syncStmt->execute();
    }

    return true;
}

function paycheck_total_paid($paycheckId) {
    $items = get_paycheck_payments($paycheckId);
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float)$item['amount'];
    }
    return $total;
}
