# Settings Rework + Graphs Tracker

## Information:
- Settings: Comprehensive (company/org/attendance/leaves/schedule/appearance/security).
- Reports: Stats tables, no interactive graphs (pie/bar progress only).
- Analytics: Cards only (_analytics-cards.php), _analytics-charts.php exists but empty? Charts.js used in setup/demo.

## Plan:
1. **Settings**: Add Email/SMS config, payroll settings, notification prefs, API keys tab.
2. **Graphs Reports**: Add Chart.js bar/pie for attendance, leaves, turnover.
3. **HR Analytics**: Pie department distribution, line monthly hires/attendance trend, doughnut leave types.

## Steps:
- [ ] 1. Create modules/settings/ subfiles (email.php, payroll.php, notifications.php, api.php)
- [ ] 2. Update modules/settings/index.php - add tabs/components.
- [ ] 3. Add modules/reports/_charts.php + Chart.js graphs (attendance pie, monthly line).
- [ ] 4. Update modules/analytics/_analytics-charts.php with department pie, hires line.
- [ ] 5. Test + push to blackboxai/settings-graphs.

Pending approval.
