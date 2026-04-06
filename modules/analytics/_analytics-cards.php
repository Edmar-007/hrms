<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-people-fill fs-1 text-primary"></i>
                <h2 class="mt-2 mb-0"><?= number_format($totalEmployees) ?></h2>
                <p class="text-muted mb-0">Total Employees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-person-check-fill fs-1 text-success"></i>
                <h2 class="mt-2 mb-0"><?= number_format($activeEmployees) ?></h2>
                <p class="text-muted mb-0">Active Employees</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-calendar-check fs-1 text-info"></i>
                <h2 class="mt-2 mb-0"><?= $avgTenure ?></h2>
                <p class="text-muted mb-0">Avg Tenure (Years)</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-arrow-repeat fs-1 text-warning"></i>
                <h2 class="mt-2 mb-0"><?= $turnoverRate ?>%</h2>
                <p class="text-muted mb-0">Turnover Rate</p>
            </div>
        </div>
    </div>
</div>
