# Budgeter II

A clean, practical, and real-world-focused budgeting app built with PHP and MySQL.

Designed to make tracking bills, paychecks, and cash flow simple—especially for users managing recurring bills, one-time expenses, and catching up on overdue payments.

---

## ✨ Features

### 💰 Paycheck-Based Budgeting
- Assign bills to a specific paycheck
- See:
  - Total bills selected
  - Paycheck amount
  - Remaining balance after bills

---

### 📅 Calendar-Driven Interface
- Monthly calendar view of all bills
- Visual indicators for:
  - Paid bills
  - Unpaid bills
  - Paychecks
- Click any day to manage bills and payments

---

### 🔁 Recurring Bills
- Supports:
  - Weekly
  - Bi-weekly
  - Monthly
  - Every 3 months
  - **Single (one-time) bills**
- “Do Not Repeat” option for controlled visibility

---

### 🧾 Smart “Do Not Repeat” Logic
- Maintains up to **5 unpaid instances** in Upcoming Bills
- Allows paying multiple overdue instances at once
- Prevents bills from disappearing when catching up

---

### 📌 Upcoming Bills Panel
- Shows current + next month bills
- Guarantees:
  - At least one unpaid instance is always visible
  - Minimum number of bills displayed
- Includes:
  - “Confirm Due Date” flow for future bills

---

### 🧠 Manual One-Time Bills
- Add custom bills on the fly
- Inline editing before saving
- Stored per month for tracking

---

### ⚙️ Recurring Bills Management
- Create, edit, and deactivate bills
- Separate Active / Inactive lists
- Scrollable admin panels for large lists

---

### 🧾 Inline Editing (Paycheck View)
- Edit bill details directly from paycheck screen
- Update:
  - Name
  - Amount
  - Notes
  - Due date

---

## 🚀 Getting Started

### Requirements
- PHP 7.4+
- MySQL / MariaDB
- Local server (XAMPP, Laragon, UniServer, etc.)

---

### Installation

1. Clone or download the repository

2. Place in your web server directory: