<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-graph-up-arrow me-2"></i>Workforce Growth & Hiring Pace</span>
                <span class="table-count-badge">LAST 12 MONTHS</span>
            </div>
            <div class="card-body">
                <?php if (empty($growthLabels)): ?>
                    <div class="empty-state"><i class="bi bi-graph-up"></i><p>No hiring history is available yet.</p></div>
                <?php else: ?>
                    <div class="chart-stage chart-stage--xl">
                        <canvas id="growthHiringChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-pie-chart me-2"></i>Tenure Mix</span>
                <span class="table-count-badge">ACTIVE EMPLOYEES</span>
            </div>
            <div class="card-body">
                <?php if (empty($tenureLabels)): ?>
                    <div class="empty-state"><i class="bi bi-hourglass"></i><p>No tenure distribution is available yet.</p></div>
                <?php else: ?>
                    <div class="chart-stage chart-stage--lg">
                        <canvas id="tenureMixChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-diagram-3 me-2"></i>Department Footprint</span>
                <span class="table-count-badge">TOP 10 TEAMS</span>
            </div>
            <div class="card-body">
                <?php if (empty($deptLabels)): ?>
                    <div class="empty-state"><i class="bi bi-buildings"></i><p>No department footprint is available yet.</p></div>
                <?php else: ?>
                    <div class="chart-stage chart-stage--lg">
                        <canvas id="departmentFootprintChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-activity me-2"></i>Attendance Momentum</span>
                <span class="table-count-badge">LAST 6 MONTHS</span>
            </div>
            <div class="card-body">
                <?php if (empty($attendanceLabels)): ?>
                    <div class="empty-state"><i class="bi bi-activity"></i><p>No attendance momentum data is available yet.</p></div>
                <?php else: ?>
                    <div class="chart-stage chart-stage--lg">
                        <canvas id="attendanceMomentumChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-calendar-range me-2"></i>Leave Rhythm</span>
                <span class="table-count-badge">LAST 6 MONTHS</span>
            </div>
            <div class="card-body">
                <?php if (empty($leaveLabels)): ?>
                    <div class="empty-state"><i class="bi bi-calendar3"></i><p>No leave rhythm data is available yet.</p></div>
                <?php else: ?>
                    <div class="chart-stage chart-stage--lg">
                        <canvas id="leaveRhythmChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Analytics Summary</div>
            <div class="card-body">
                <div class="metric-strip">
                    <div class="metric-tile">
                        <span class="metric-tile__label">Workforce Mix</span>
                        <strong class="metric-tile__value"><?= number_format($activeEmployees) ?></strong>
                        <span class="metric-tile__meta">Active employees, <?= number_format($inactiveEmployees) ?> inactive profiles</span>
                    </div>
                    <div class="metric-tile">
                        <span class="metric-tile__label">Hiring Pace</span>
                        <strong class="metric-tile__value"><?= number_format($newHiresYtd) ?></strong>
                        <span class="metric-tile__meta">New hires added in <?= date('Y') ?></span>
                    </div>
                    <div class="metric-tile">
                        <span class="metric-tile__label">Attendance Stability</span>
                        <strong class="metric-tile__value"><?= $attendanceRate ?>%</strong>
                        <span class="metric-tile__meta">Active employees seen during <?= date('F') ?></span>
                    </div>
                    <div class="metric-tile">
                        <span class="metric-tile__label">Leave Load</span>
                        <strong class="metric-tile__value"><?= number_format($pendingLeaves) ?></strong>
                        <span class="metric-tile__meta">Open approvals still in queue</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '_analytics-graphs.php'; ?>
