# Changelog

## 1.2.1

### Fixes
- Fixed issue where "Add More Bills To This Paycheck" did not preload existing paid bills into the Bills to Pay tile
- Prevented duplicate entries when saving updates to an existing paycheck

### Improvements
- Editing an existing paycheck now correctly reflects previously paid bills in the Home screen
- Improved paycheck editing workflow consistency


## 1.2.0

### Features
- Do Not Repeat bills now maintain up to 5 unpaid instances in Upcoming Bills
- Allows multiple past-due instances of a bill to be paid at once

### Improvements
- Improved Upcoming Bills behavior for overdue and catch-up scenarios
- Ensures at least one unpaid instance is always visible for Do Not Repeat bills

### Fixes
- Fixed issue where Do Not Repeat bills would disappear after being added to Bills to Pay

## 1.1.0

### Features
- Added "Single (One-Time)" option for recurring bills

### Fixes
- Fixed issue where single bills due earlier in the current month did not appear in the Upcoming Bills section

### Improvements
- Improved date handling for one-time bill generation to ensure consistent inclusion within month range

- Removed auto-scroll behavior from the Recurring Bills admin lists while keeping manual scrolling and fixed-height lists.
