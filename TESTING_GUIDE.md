# HRMS Phase 1 & 2 - Comprehensive Testing Guide

## Pre-Testing Checklist

### 1. Database Verification
- [ ] All 9 new tables exist
- [ ] Default data seeded
- [ ] Foreign key relationships intact

### 2. Server Environment
- [ ] Apache/PHP running on localhost
- [ ] MySQL on port 33060
- [ ] Database: hrms_db accessible
- [ ] BASE_URL configured correctly

### 3. Session Setup
- [ ] Admin account accessible: admin@hrms.local / admin123
- [ ] Browser cookies enabled
- [ ] XAMPP services running

---

## PART 1: PAYROLL FEATURES TESTING

### Test Case 1.1: Salary Structure Management

**Objective**: Create and manage salary structures

**Steps**:
1. Log in as admin (admin@hrms.local / admin123)
2. Navigate to **Payroll → Salary Structures**
3. Click **New Structure**
4. Enter:
   - Name: "Supervisor"
   - Description: "For team leads and supervisors"
5. Click **Create Structure**

**Expected Results** ✓
- [ ] Success message appears
- [ ] New structure appears in list
- [ ] "Configure" button available
- [ ] Employee count shows 0

**Pass/Fail**: ___________

---

### Test Case 1.2: Salary Component Configuration

**Objective**: Add earnings and deductions to a structure

**Steps**:
1. On Salary Structures page, click **Configure** on "Standard" structure
2. Add Earning Component:
   - Type: Earning
   - Name: "Housing Allowance"
   - Value Type: Fixed Amount
   - Value: 5000
   - Order: 2
   - Click **Add Component**

3. Add Deduction Component:
   - Type: Deduction
   - Name: "Employee Loan"
   - Value Type: Fixed Amount
   - Value: 500
   - Order: 6
   - Click **Add Component**

**Expected Results** ✓
- [ ] Components appear in respective sections
- [ ] Earnings show in green section
- [ ] Deductions show in red section
- [ ] Edit modal works for each component
- [ ] Delete button removes component

**Pass/Fail**: ___________

---

### Test Case 1.3: 13th Month Pay Computation

**Objective**: Calculate 13th month pay for employees

**Steps**:
1. Navigate to **Payroll → 13th Month Pay**
2. Default year should be 2026
3. In right panel, select employee "Maria Santos" from dropdown
4. Click **Compute**
5. View the computed record in the table

**Expected Results** ✓
- [ ] Computation completes without error
- [ ] Record appears in table with status "Draft"
- [ ] Shows: Basic Earned, 13th Month Amount, Deductions, Final Amount
- [ ] Numbers are positive and reasonable
- [ ] Can click eye icon to see popup with breakdown

**Verification**:
```
Formula Check:
- Total Basic Earned = Sum of basic salary × months worked
- 13th Month Amount = Total Basic Earned ÷ 12
- Final Amount = 13th Month - (Absences + Unpaid Leave)
```

**Pass/Fail**: ___________

---

### Test Case 1.4: 13th Month Pay Status Workflow

**Objective**: Move 13th month from Draft to Finalized

**Steps**:
1. In 13th Month Pay page, find a "Draft" record
2. Click lock icon (🔒) next to Final Amount
3. Confirm you want to finalize
4. Status should change to "Finalized"

**Expected Results** ✓
- [ ] Status badge changes from yellow "Draft" to green "Finalized"
- [ ] Lock icon disappears
- [ ] Record is now read-only

**Pass/Fail**: ___________

---

## PART 2: SHIFT MANAGEMENT TESTING

### Test Case 2.1: Create New Shift

**Objective**: Define a work shift schedule

**Steps**:
1. Navigate to **Attendance → Shifts**
2. Click **New Shift** (or card on right)
3. Enter:
   - Shift Name: "Afternoon"
   - Start Time: 12:00 PM (12:00)
   - End Time: 9:00 PM (21:00)
   - Lunch Start: 5:00 PM (17:00)
   - Lunch End: 6:00 PM (18:00)
   - Break Minutes: 60
4. Click **Create Shift**

**Expected Results** ✓
- [ ] Success message: "Shift created successfully!"
- [ ] New shift appears in list
- [ ] Time displayed in 12-hour format
- [ ] Break duration shows "60 min"
- [ ] Status shows "Active"

**Pass/Fail**: ___________

---

### Test Case 2.2: Shift Management CRUD

**Objective**: Edit and toggle shift status

**Steps**:
1. On Shifts page, find "Afternoon" shift
2. Click pencil icon (✏️) to edit
3. Change Break Minutes: 75
4. Click **Save Changes**
5. Toggle status by clicking toggle icon

**Expected Results** ✓
- [ ] Edit modal opens with filled fields
- [ ] Changes save successfully
- [ ] Break minutes updates to 75
- [ ] Toggle button switches Active/Inactive
- [ ] Status badge changes color (green/gray)

**Pass/Fail**: ___________

---

### Test Case 2.3: Assign Employees to Shift

**Objective**: Bulk assign employees to a shift

**Steps**:
1. On Shifts page, click people icon (👥) for "Standard" shift
2. In right panel, select "Maria Santos" from dropdown
3. Set Effective From: Today's date
4. Click **Add Employee**
5. Verify she appears in "Currently Assigned Employees"

**Steps continued**:
6. Select another employee and assign
7. Try to end assignment: Click lock icon for Maria
8. Set Effective To date to today
9. Click **End Assignment**

**Expected Results** ✓
- [ ] Employee added to assigned list
- [ ] Appears with effective from date
- [ ] Can add multiple employees
- [ ] Effective To shows "Active" until set
- [ ] Lock icon ends assignment successfully
- [ ] Effective To date is set correctly

**Pass/Fail**: ___________

---

## PART 3: HOLIDAY MANAGEMENT TESTING

### Test Case 3.1: Add Holiday to Calendar

**Objective**: Add holidays to the calendar

**Steps**:
1. Navigate to **Settings → Holiday Calendar**
2. Current month displayed
3. Click **Add Holiday** button
4. Enter:
   - Holiday Date: April 10 (or next available day)
   - Holiday Name: "Bataan Day"
   - Check "Public Holiday"
5. Click **Add Holiday**

**Expected Results** ✓
- [ ] Success message appears
- [ ] Calendar refreshes
- [ ] Date shows orange highlighted box on calendar
- [ ] Holiday name visible in small text on calendar
- [ ] Holiday appears in right panel list

**Pass/Fail**: ___________

---

### Test Case 3.2: Holiday Calendar Navigation

**Objective**: Navigate months and view holidays

**Steps**:
1. On Holiday Calendar page
2. Click <- arrow to go to previous month
3. Click -> arrow to go to next month
4. Click "Today" button
5. Navigate to different year (select in left navigation if available)

**Expected Results** ✓
- [ ] Calendar updates for each month
- [ ] Dates on calendar are correct
- [ ] Weekends (Sat/Sun) highlighted in light red
- [ ] Holidays highlighted in orange
- [ ] Month/year header updates

**Pass/Fail**: ___________

---

### Test Case 3.3: Delete Holiday

**Objective**: Remove a holiday from calendar

**Steps**:
1. In Holiday Calendar page, find holiday in right panel list
2. Click trash icon (🗑️)
3. Confirm deletion if prompted

**Expected Results** ✓
- [ ] Holiday removed from list
- [ ] Calendar updates
- [ ] Orange highlighting removed from date

**Pass/Fail**: ___________

---

## PART 4: LEAVE BALANCE TESTING

### Test Case 4.1: View Personal Leave Balance (Employee)

**Objective**: Employee views their available leave days

**Steps**:
1. Log out if admin
2. Log in as employee: "maria.santos@demo.com" / admin123
3. Navigate to **My Leave → Leave Balance**
   (or **Leaves → Balance** from main menu)

**Expected Results** ✓
- [ ] Page shows employee name and ID
- [ ] Displays all leave types (Vacation, Sick, Maternity, etc.)
- [ ] For each type shows:
  - [ ] Leave type name
  - [ ] Opening Balance badge
  - [ ] Used badge (yellow)
  - [ ] Remaining badge (green)
  - [ ] Progress bar showing usage %
- [ ] Total row at bottom shows totals
- [ ] Paid/Unpaid badge visible

**Sample Data Check**:
```
Vacation Leave:     Opening: 15, Used: 0, Remaining: 15 (0%)
Sick Leave:         Opening: 15, Used: 0, Remaining: 15 (0%)
Maternity Leave:    Opening: 60, Used: 0, Remaining: 60 (0%)
```

**Pass/Fail**: ___________

---

### Test Case 4.2: View Another Employee's Balance (Admin)

**Objective**: Admin views any employee's leave balance

**Steps**:
1. Log back in as admin
2. Navigate to **Leaves → Balance**
3. Add URL parameter: `?employee_id=4` (for Ana Garcia)
4. Page should update to show Ana's details

**Expected Results** ✓
- [ ] Page title shows "My Leave Balances" (context-dependent)
- [ ] Employee name shows: "Ana Garcia"
- [ ] Leave balances displayed for Ana
- [ ] If URL parameter invalid, redirects to dashboard

**Pass/Fail**: ___________

---

### Test Case 4.3: Leave Balance Year Selector

**Objective**: View leave balances for different years

**Steps**:
1. On Leave Balance page (as employee)
2. In top right, there's a year dropdown (or link)
3. Try to select different years if available
4. For 2026 specifically, verify records exist

**Expected Results** ✓
- [ ] Can select years: 2026, 2025, 2024
- [ ] Balance data updates for selected year
- [ ] Current year (2026) has data
- [ ] Previous years show 0 (no historical data yet)

**Pass/Fail**: ___________

---

## PART 5: DATABASE & API TESTING

### Test Case 5.1: Database Tables Verification

**Objective**: Verify all 9 new tables exist with correct structure

**Steps**:
1. Open phpMyAdmin or database client
2. Connect to hrms_db
3. Check each table exists:

```
Expected Tables:
✓ shifts                       (13 columns)
✓ shift_assignments            (7 columns)
✓ holidays                     (6 columns)
✓ break_records                (8 columns)
✓ employee_leave_balance       (9 columns + 1 generated)
✓ salary_structures            (6 columns)
✓ salary_components            (10 columns)
✓ employee_salary_components   (10 columns)
✓ payroll_records              (12 columns)
✓ payroll_items                (6 columns)
✓ thirteenth_month_records     (12 columns)
✓ attendance_settings          (6 columns)
✓ attendance_exceptions        (9 columns)
```

**Expected Results** ✓
- [ ] All 13 tables exist
- [ ] Foreign key relationships intact
- [ ] Indexes created on company_id, employee_id, date fields
- [ ] No errors in structure

**Pass/Fail**: ___________

---

### Test Case 5.2: Seeded Data Verification

**Objective**: Verify default data was created

**Steps**:
1. In phpMyAdmin, run queries:

```sql
-- Check shifts
SELECT * FROM shifts WHERE company_id = 1;

-- Check salary structure
SELECT * FROM salary_structures WHERE company_id = 1;

-- Check salary components
SELECT * FROM salary_components WHERE company_id = 1;

-- Check leave balances
SELECT elb.*, lt.name FROM employee_leave_balance elb
JOIN leave_types lt ON lt.id = elb.leave_type_id
WHERE elb.company_id = 1 LIMIT 10;
```

**Expected Results** ✓
- [ ] shifts: 1 record ("Standard (8AM-5PM)")
- [ ] salary_structures: 1 record ("Standard")
- [ ] salary_components: 5 records (Basic, SSS, PhilHealth, Pag-IBIG, Tax)
- [ ] employee_leave_balance: 20+ records (employees × leave types)
- [ ] No errors, proper data types

**Pass/Fail**: ___________

---

### Test Case 5.3: Payroll API Endpoint

**Objective**: Test payroll processing API

**Steps**:
1. Using Postman or curl, call API:
```
GET http://localhost/hrms/api/payroll-process.php?action=get_payroll_data&month=2026-04
```

2. Verify JSON response structure

**Expected Results** ✓
- [ ] HTTP 200 response
- [ ] JSON contains:
  - [ ] "success": true
  - [ ] "period": "2026-04"
  - [ ] "payrolls": [] array
  - [ ] "stats": { total_earnings, total_deductions, total_net_pay }
- [ ] No PHP errors or warnings

**Pass/Fail**: ___________

---

## PART 6: UI/UX TESTING

### Test Case 6.1: Responsive Design (Mobile)

**Objective**: Verify pages work on mobile devices

**Steps**:
1. Open any new page (Salary Structures, Shifts, Holiday Calendar)
2. Resize browser to 375px width (mobile size)
3. Or use DevTools → Toggle device toolbar
4. Test on iPhone/Android dimensions

**Expected Results** ✓
- [ ] Layout doesn't break
- [ ] Navigation accessible
- [ ] Forms remain usable
- [ ] Tables scroll horizontally if needed
- [ ] Buttons properly sized for touch
- [ ] No horizontal scroll on page

**Pass/Fail**: ___________

---

### Test Case 6.2: Form Validation

**Objective**: Verify form validation works

**Steps**:
1. On Salary Structures page, click **New Structure**
2. Try submitting without filling Name
3. Try submitting with valid Name
4. Try adding component without selecting Type
5. Try adding component with all fields

**Expected Results** ✓
- [ ] Required fields show validation errors
- [ ] Cannot submit without required fields
- [ ] Valid data submits successfully
- [ ] Success messages appear
- [ ] Error messages are clear

**Pass/Fail**: ___________

---

### Test Case 6.3: Empty State Handling

**Objective**: Verify pages handle empty data gracefully

**Steps**:
1. For new company with no shifts, visit Shifts page
2. For new company with no holidays, visit Holiday Calendar
3. For employee with no leave balance (if possible), visit Balance page

**Expected Results** ✓
- [ ] Empty state message shows (e.g., "No shifts defined yet")
- [ ] "Create/Add" button still accessible
- [ ] No error messages
- [ ] Proper placeholder/icon shown
- [ ] Page is usable

**Pass/Fail**: ___________

---

## PART 7: SECURITY TESTING

### Test Case 7.1: CSRF Token Protection

**Objective**: Verify CSRF protection on forms

**Steps**:
1. On any create/edit form, inspect HTML
2. Should see `<input type="hidden" name="csrf" value="...">`
3. Update salary structure without valid CSRF token
   - Copy form value, modify it, submit
4. Should be rejected

**Expected Results** ✓
- [ ] CSRF token field present on all forms
- [ ] Invalid tokens rejected
- [ ] Valid tokens accepted
- [ ] Error message if token invalid

**Pass/Fail**: ___________

---

### Test Case 7.2: Access Control

**Objective**: Verify role-based access

**Steps**:
1. Log in as Employee account
2. Try to access Salary Structures page directly:
   `http://localhost/hrms/modules/payroll/salary-structure.php`
3. Should be redirected or denied
4. Try accessing as Admin - should work

**Expected Results** ✓
- [ ] Employee cannot access admin pages
- [ ] Redirects to dashboard or shows 403
- [ ] No error messages expose system info
- [ ] Admin can access all pages

**Pass/Fail**: ___________

---

### Test Case 7.3: SQL Injection Prevention

**Objective**: Verify prepared statements protect against injection

**Steps**:
1. Add Holiday with name: `'; DROP TABLE holidays; --`
2. Submit form
3. Check if table still exists and data is safe

**Expected Results** ✓
- [ ] Holiday created with SQL code as literal name
- [ ] No actual SQL execution
- [ ] Table remains intact
- [ ] Data is properly escaped

**Pass/Fail**: ___________

---

## PART 8: ERROR HANDLING TESTING

### Test Case 8.1: Database Error Handling

**Objective**: Verify graceful error handling

**Steps**:
1. Try to access broken query endpoint
2. Intentionally cause error (e.g., invalid employee_id)
3. Submit form with invalid data

**Expected Results** ✓
- [ ] Error messages are user-friendly
- [ ] No raw SQL or PHP errors shown
- [ ] No stack traces exposed
- [ ] Proper HTTP status codes (4xx, 5xx)
- [ ] Alternative actions suggested

**Pass/Fail**: ___________

---

### Test Case 8.2: Missing Data Handling

**Objective**: Verify system handles missing relationships

**Steps**:
1. View employee with no salary structure assigned
2. View leave balance for employee with no leave balance records
3. View shift for employee not assigned to any shift

**Expected Results** ✓
- [ ] Shows "N/A" or "-" for missing data
- [ ] No error messages
- [ ] Page remains functional
- [ ] Can still take action (assign structure, create balance, etc.)

**Pass/Fail**: ___________

---

## ISSUES FOUND & FIXES

| Issue | Severity | Status | Notes |
|-------|----------|--------|-------|
| | | | |
| | | | |
| | | | |

---

## TESTING SUMMARY

### Test Results
- **Total Test Cases**: 30
- **Passed**: ___
- **Failed**: ___
- **Blocked**: ___
- **Pass Rate**: ___%

### Critical Issues
- None required for release: ___________

### Nice-to-Have Improvements
1. ___________
2. ___________
3. ___________

### Recommendation
- [ ] **APPROVED FOR PHASE 3** - Proceed with break tracking, leave auto-deduction, QR validation
- [ ] **NEEDS FIXES** - Fix issues, then proceed to Phase 3
- [ ] **NEEDS REDESIGN** - Major issues found, halt Phase 3

---

## Sign-Off

**Tester Name**: ________________
**Date**: ________________
**Overall Assessment**: ________________

