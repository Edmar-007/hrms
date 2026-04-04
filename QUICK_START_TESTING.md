# HRMS Phase 1 & 2 - Quick Start Testing Guide

## 🚀 START HERE - 5 Minutes to Get Running

### Step 1: Verify System Health (2 minutes)

1. **Open your browser** and go to:
   ```
   http://localhost/hrms/health-check.php
   ```

2. **Check the status**:
   - ✓ Database Connection: **Green/Success**
   - ✓ All Tables Present: **100%**
   - ✓ Admin Account: **Ready**
   - ✓ Seed Data: **Has data**

3. **If any issues**, fix them before proceeding

---

### Step 2: Login (1 minute)

1. Go to: `http://localhost/hrms/`
2. Login with:
   - **Email**: admin@hrms.local
   - **Password**: admin123
3. You should see the Dashboard with quick action cards

---

### Step 3: Run Quick Verification (2 minutes)

Navigate to each new feature and confirm it loads:

#### **Payroll Features** ✓
- [ ] **Payroll** → **Salary Structures**
  - Should show "Standard" structure
  - Click **Configure** - should see Basic, SSS, PhilHealth, Pag-IBIG, Tax components

- [ ] **Payroll** → **13th Month Pay**
  - Should show current year (2026)
  - Should have dropdown to select employees

#### **Shift Features** ✓
- [ ] **Attendance** → **Shifts**
  - Should show "Standard (8AM-5PM)" shift
  - Click people icon - should allow assigning employees

#### **Settings Features** ✓
- [ ] **Settings** → **Holiday Calendar**
  - Should show current month calendar
  - Should allow adding holidays

---

## 📋 DETAILED TEST SCENARIOS (Choose One to Start)

### Scenario A: Test Salary Structure (10 minutes)

**Goal**: Create a new salary structure and add components

1. **Navigate**: Payroll → **Salary Structures**

2. **Create New Structure**:
   - Click **New Structure** button
   - Enter Name: `Supervisor`
   - Enter Description: `For team leaders`
   - Click **Create Structure**
   - ✓ Should see success message
   - ✓ "Supervisor" should appear in list

3. **Configure Components**:
   - Click **Configure** on "Supervisor" structure
   - Add Earning:
     - Type: **Earning**
     - Name: **Housing Allowance**
     - Type: **Fixed Amount**
     - Value: **8000**
     - Click **Add Component**
   - ✓ Should appear in green "Earnings" section

   - Add Deduction:
     - Type: **Deduction**
     - Name: **Employee Loan**
     - Type: **Fixed Amount**
     - Value: **1000**
     - Click **Add Component**
   - ✓ Should appear in red "Deductions" section

4. **Edit Component**:
   - Click pencil icon on Housing Allowance
   - Change value to 10000
   - Click **Save Changes**
   - ✓ Value should update

5. **Delete Component**:
   - Click trash icon on Employee Loan
   - Confirm deletion
   - ✓ Should be removed

---

### Scenario B: Test 13th Month Pay (15 minutes)

**Goal**: Calculate and finalize 13th month pay

1. **Navigate**: Payroll → **13th Month Pay**
   - Year should be **2026**

2. **Select Employee to Compute**:
   - In right panel, select **Maria Santos** from dropdown
   - Click **Compute**
   - ✓ Should complete without error
   - ✓ New row should appear in table with status "Draft"

3. **View Computation Breakdown**:
   - Click eye icon on the row
   - Modal should show:
     - **Total Basic Earned**: (total salary for year)
     - **13th Month Amount**: (basic earned ÷ 12)
     - **Less: Absences**: (absence days × daily rate)
     - **Less: Unpaid Leave**: (unpaid days × daily rate)
     - **Final Payable Amount**: (amount after deductions)
   - Close modal

4. **Verify Calculation**:
   - Click row to see details
   - Final amount should be positive and reasonable
   - Formula: `(Basic ÷ 12) - Absences - UnpaidLeave`

5. **Finalize Record**:
   - Find the "Draft" record
   - Click lock icon (🔒)
   - ✓ Status should change to **"Finalized"** (green badge)
   - ✓ Lock icon should disappear

6. **Compute for Another Employee**:
   - Select different employee (e.g., **Juan Dela Cruz**)
   - Click **Compute**
   - ✓ Should create new record

---

### Scenario C: Test Shift Management (15 minutes)

**Goal**: Create and assign work shifts

1. **Navigate**: Attendance → **Shifts**
   - Should see "Standard (8AM-5PM)" shift

2. **Create New Shift**:
   - Click **New Shift** (or use card on right)
   - Fill form:
     - Shift Name: **Evening Shift**
     - Start Time: **14:00** (2:00 PM)
     - End Time: **23:00** (11:00 PM)
     - Lunch Start: **18:00** (6:00 PM)
     - Lunch End: **19:00** (7:00 PM)
     - Break Minutes: **60**
   - Click **Create Shift**
   - ✓ Success message should appear
   - ✓ "Evening Shift" should be in list

3. **View Shift Details**:
   - Row should show:
     - **Time**: 2:00 PM - 11:00 PM ✓
     - **Break**: 6:00 - 7:00, 60 min badge ✓
     - **Employees**: 0 (badge) ✓

4. **Edit Shift**:
   - Click pencil icon (✏️)
   - Change Break Minutes: **90**
   - Click **Save Changes**
   - ✓ Shift should update
   - ✓ Badge should show "90 min"

5. **Toggle Active Status**:
   - Click toggle icon on Evening Shift
   - Status badge should toggle Inactive/Active
   - Click again to activate

6. **Assign Employees**:
   - Click people icon (👥) on "Standard (8AM-5PM)" shift
   - In right panel dropdown, select **Maria Santos**
   - Set Effective From: **Today's date**
   - Click **Add Employee**
   - ✓ Maria should appear in "Currently Assigned" list
   - ✓ Should show "Active" (no end date)

7. **Assign Multiple Employees**:
   - Select **Juan Dela Cruz**
   - Click **Add Employee**
   - ✓ Both should be listed

8. **End Assignment**:
   - Click lock icon (🔒) on Maria's assignment
   - Set Effective To: **Today's date**
   - Click **End Assignment**
   - ✓ Status should change from "Active" to a date

9. **Remove Assignment**:
   - Click trash icon on Juan's assignment
   - Confirm deletion
   - ✓ Should be removed from list

---

### Scenario D: Test Holiday Calendar (10 minutes)

**Goal**: Add and manage company holidays

1. **Navigate**: Settings → **Holiday Calendar**
   - Month calendar should display
   - Weekends (Sat/Sun) highlighted in light red

2. **Add First Holiday**:
   - Click **Add Holiday**
   - Fill modal:
     - Holiday Date: **April 9** (or any upcoming date)
     - Holiday Name: **Day of Valor**
     - Check **Public Holiday**
   - Click **Add Holiday**
   - ✓ Success message
   - ✓ Calendar updates
   - ✓ Date shows orange highlight with "Day of Valor" text

3. **Add Company-Specific Holiday**:
   - Click **Add Holiday**
   - Fill:
     - Holiday Date: **April 15**
     - Holiday Name: **Company Foundation Day**
     - UNCHECK **Public Holiday**
   - ✓ Should be added
   - ✓ Shows "Company" badge instead of "Public"

4. **Navigate Months**:
   - Click **← arrow** previous month
   - Calendar should update to March
   - Click **→ arrow** next month
   - Calendar should update to May
   - Click **Today** button to return to current month

5. **View Holidays List**:
   - Right panel shows all holidays for the year
   - Should include both holidays you added

6. **Delete Holiday**:
   - In right panel, find a holiday
   - Click trash icon (🗑️)
   - ✓ Holiday removed
   - ✓ Calendar updates

7. **Add More Holidays** (Optional):
   - Add May 1 - Labor Day
   - Add June 12 - Independence Day
   - Add December 25 - Christmas Day
   - ✓ All should appear on calendar

---

### Scenario E: Test Leave Balance (10 minutes)

**Goal**: View employee leave balances

#### **As Admin**:
1. Navigate: **Leaves** → **Leave Balance** (or **My Leave** → **Balance**)

2. **View Maria Santos' Balance**:
   - Should see employee info at top
   - Should see table with leave types:
     - Vacation Leave
     - Sick Leave
     - Maternity Leave
     - etc.

3. **Check Balance Details**:
   - For Vacation Leave:
     - ✓ Opening Balance badge: **15**
     - ✓ Used badge: **0**
     - ✓ Remaining badge: **15**
     - ✓ Progress bar: **0%**
   - Repeat for other types

4. **View Totals**:
   - Bottom row shows "TOTAL"
   - Totals should sum all leave types

5. **Change Year**:
   - Look for year selector
   - Try 2025, 2026
   - Should show data for selected year

#### **As Employee**:
1. Log out, Login as: **maria.santos@demo.com** / **admin123**

2. Navigate: **My Leave** → **Leave Balance**

3. **Verify Access**:
   - ✓ Can see own balance
   - ✓ Shows employee details
   - ✓ Shows all leave types
   - ✓ Can see remaining days

4. **Try Another Employee's Balance**:
   - Admin can add `?employee_id=4` to URL
   - Should show Ana Garcia's balance
   - Employee trying to add parameter - should still see own

---

## 🐛 ISSUES TRACKER

Use this section to log any issues you find:

### Issue Template:
```
**Issue #**: [1, 2, 3, ...]
**Feature**: [Salary Structures, 13th Month, Shifts, Holidays, Leaves]
**Severity**: [Critical, High, Medium, Low]
**Title**: [Brief description]
**Steps to Reproduce**:
1. ...
2. ...

**Expected Result**:
[What should happen]

**Actual Result**:
[What actually happens]

**Screenshot**: [If applicable]
```

---

### Issue #1
**Feature**:
**Severity**:
**Title**:
**Steps to Reproduce**:

**Expected Result**:

**Actual Result**:

---

### Issue #2
**Feature**:
**Severity**:
**Title**:
**Steps to Reproduce**:

**Expected Result**:

**Actual Result**:

---

### Issue #3
**Feature**:
**Severity**:
**Title**:
**Steps to Reproduce**:

**Expected Result**:

**Actual Result**:

---

## ✅ TESTING CHECKLIST

### Payroll Features
- [ ] Can create salary structures
- [ ] Can add/edit/delete components
- [ ] 13th month computation works
- [ ] Can finalize 13th month records
- [ ] Calculations are correct

### Shift Features
- [ ] Can create shifts
- [ ] Can edit shift times/breaks
- [ ] Can toggle shift active status
- [ ] Can assign employees to shifts
- [ ] Can end assignments
- [ ] Can remove assignments
- [ ] Multiple employees can be assigned

### Holiday Features
- [ ] Can add holidays
- [ ] Can delete holidays
- [ ] Calendar displays correctly
- [ ] Can navigate months
- [ ] Can toggle public/company holidays

### Leave Features
- [ ] Can view own leave balance (employee)
- [ ] Admin can view any employee's balance
- [ ] Balances show correct opening/used/remaining
- [ ] Progress bars display usage percentage
- [ ] Can select different years
- [ ] Button to submit leave request visible

---

## 📞 SUPPORT

If you encounter issues:

1. **Check health-check.php** - Verify all systems are green
2. **Check browser console** - F12 → Console tab for JavaScript errors
3. **Check server logs** - Look for PHP errors
4. **Cross-browser test** - Try Chrome, Firefox, Safari
5. **Clear cache** - Ctrl+Shift+Delete, clear all

---

## 📝 TESTING NOTES

Document your testing experience here:

```
Date: _______________
Tester: ______________
Environment: Windows/Mac/Linux
Browser: ______________

Overall Experience:
[Great/Good/Fair/Poor]

What Worked Well:
-
-
-

What Needs Improvement:
-
-
-

Ready for Phase 3:
[Yes/No]
```

---

## NEXT STEPS AFTER TESTING

Once you complete testing:

1. **Report any issues** found
2. **Rate overall quality** (1-5 stars)
3. **Approve or request fixes**
4. **Decision**: Proceed to Phase 3 or iterate

---

**Good luck with testing! Let me know what you find.** 🎯
