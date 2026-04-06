<div class="card reporting-toolbar mb-4">
    <div class="card-body">
        <div class="reporting-toolbar__grid">
            <div>
                <h5 class="reporting-toolbar__title">People health at a glance</h5>
                <p class="reporting-toolbar__text">These analytics focus on workforce growth, tenure maturity, attendance stability, and leave load over the last year.</p>
                <div class="page-header-meta mt-3">
                    <span class="page-chip page-chip--accent"><i class="bi bi-check2-circle me-1"></i><?= $activeRate ?>% active roster</span>
                    <span class="page-chip"><i class="bi bi-clock-history me-1"></i><?= $avgHoursThisMonth ?> avg hours this month</span>
                </div>
            </div>
            <div class="metric-strip">
                <div class="metric-tile">
                    <span class="metric-tile__label">New Hires YTD</span>
                    <strong class="metric-tile__value"><?= number_format($newHiresYtd) ?></strong>
                    <span class="metric-tile__meta">Fresh additions this calendar year</span>
                </div>
                <div class="metric-tile">
                    <span class="metric-tile__label">Largest Team</span>
                    <strong class="metric-tile__value metric-tile__value--sm"><?= e($largestDepartment) ?></strong>
                    <span class="metric-tile__meta">Highest current active footprint</span>
                </div>
                <div class="metric-tile">
                    <span class="metric-tile__label">Pending Leaves</span>
                    <strong class="metric-tile__value"><?= number_format($pendingLeaves) ?></strong>
                    <span class="metric-tile__meta">Requests waiting for a decision</span>
                </div>
                <div class="metric-tile">
                    <span class="metric-tile__label">Attendance Coverage</span>
                    <strong class="metric-tile__value"><?= $attendanceRate ?>%</strong>
                    <span class="metric-tile__meta">Active employees seen this month</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card analytics-kpi-card h-100">
            <div class="card-body">
                <span class="analytics-kpi-card__icon text-primary"><i class="bi bi-people-fill"></i></span>
                <span class="analytics-kpi-card__eyebrow">Total Headcount</span>
                <strong class="analytics-kpi-card__value"><?= number_format($totalEmployees) ?></strong>
                <p class="analytics-kpi-card__meta mb-0"><?= $activeRate ?>% currently active</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card analytics-kpi-card h-100">
            <div class="card-body">
                <span class="analytics-kpi-card__icon text-success"><i class="bi bi-person-check-fill"></i></span>
                <span class="analytics-kpi-card__eyebrow">Active Workforce</span>
                <strong class="analytics-kpi-card__value"><?= number_format($activeEmployees) ?></strong>
                <p class="analytics-kpi-card__meta mb-0"><?= number_format($inactiveEmployees) ?> inactive profiles</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card analytics-kpi-card h-100">
            <div class="card-body">
                <span class="analytics-kpi-card__icon text-info"><i class="bi bi-hourglass-split"></i></span>
                <span class="analytics-kpi-card__eyebrow">Average Tenure</span>
                <strong class="analytics-kpi-card__value"><?= number_format($avgTenure, 1) ?></strong>
                <p class="analytics-kpi-card__meta mb-0">Years across active employees</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card analytics-kpi-card h-100">
            <div class="card-body">
                <span class="analytics-kpi-card__icon text-warning"><i class="bi bi-calendar2-week-fill"></i></span>
                <span class="analytics-kpi-card__eyebrow">Seen This Month</span>
                <strong class="analytics-kpi-card__value"><?= number_format($attendanceThisMonth) ?></strong>
                <p class="analytics-kpi-card__meta mb-0"><?= $avgHoursThisMonth ?> average hours logged</p>
            </div>
        </div>
    </div>
</div>
