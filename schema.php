<?php
require_once __DIR__ . '/db.php';

function exec_sql($conn, $sql) {
    if (!$conn->query($sql)) {
        throw new RuntimeException('Schema error: ' . $conn->error . ' SQL: ' . $sql);
    }
}

function ensure_schema() {
    $conn = db();

    exec_sql($conn, "CREATE TABLE IF NOT EXISTS recurring_bills (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        bill_name VARCHAR(255) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        recurrence_type ENUM('single','weekly','biweekly','monthly','quarterly') NOT NULL DEFAULT 'monthly',
        first_due_date DATE NOT NULL,
        notes TEXT NULL,
        do_not_repeat TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    exec_sql($conn, "ALTER TABLE recurring_bills MODIFY recurrence_type ENUM('single','weekly','biweekly','monthly','quarterly') NOT NULL DEFAULT 'monthly'");

    exec_sql($conn, "CREATE TABLE IF NOT EXISTS paychecks (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        paycheck_date DATE NOT NULL,
        paycheck_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    exec_sql($conn, "CREATE TABLE IF NOT EXISTS paycheck_payments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        paycheck_id INT UNSIGNED NOT NULL,
        recurring_bill_id INT UNSIGNED NULL,
        due_date DATE NOT NULL,
        bill_name VARCHAR(255) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        notes TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_manual TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_paycheck_payments_paycheck FOREIGN KEY (paycheck_id) REFERENCES paychecks(id) ON DELETE CASCADE,
        CONSTRAINT fk_paycheck_payments_bill FOREIGN KEY (recurring_bill_id) REFERENCES recurring_bills(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    exec_sql($conn, "CREATE TABLE IF NOT EXISTS bill_paid_instances (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        recurring_bill_id INT UNSIGNED NOT NULL,
        due_date DATE NOT NULL,
        paycheck_payment_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_bill_due (recurring_bill_id, due_date),
        CONSTRAINT fk_bill_paid_bill FOREIGN KEY (recurring_bill_id) REFERENCES recurring_bills(id) ON DELETE CASCADE,
        CONSTRAINT fk_bill_paid_payment FOREIGN KEY (paycheck_payment_id) REFERENCES paycheck_payments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
