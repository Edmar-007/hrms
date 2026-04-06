<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-building me-2"></i>Employees by Department</h6></div>
            <div class="card-body">
                <?php if(empty($departmentStats)): ?>
                    <p class="text-muted text-center py-4">No department data available.</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead><tr><th>Department</th><th>Count</th><th>%</th></tr></thead>
                        <tbody>
                        <?php foreach($departmentStats as $d): 
                            $pct = $activeEmployees > 0 ? round(($d['cnt'] / $activeEmployees) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= e($d['name']) ?></td>
                            <td><?= $d['cnt'] ?></td>
                            <td><div class="progress" style="height:20px;"><div class="progress-bar" style="width:<?= $pct ?>%"><?= $pct ?>%</div></div></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Monthly Hires (Last 12 Months)</h6></div>
            <div class="card-body">
                <?php if(empty($monthlyHires)): ?>
                    <p class="text-muted text-center py-4">No hiring data available.</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead><tr><th>Month</th><th>Hires</th></tr></thead>
                        <tbody>
                        <?php foreach($monthlyHires as $m): ?>
                        <tr>
                            <td><?= date('M Y', strtotime($m['month'].'-01')) ?></td>
                            <td><span class="badge bg-primary"><?= $m['cnt'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Analytics Summary</h6></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Workforce Overview</h6>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success me-2"></i>Active: <?= $activeEmployees ?> (<?= $totalEmployees > 0 ? round(($activeEmployees/$totalEmployees)*100, 1) : 0 ?>%)</li>
                            <li><i class="bi bi-x-circle text-danger me-2"></i>Inactive: <?= $totalEmployees - $activeEmployees ?></li>
                            <li><i class="bi bi-clock text-info me-2"></i>Avg tenure: <?= $avgTenure ?> years</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Insights</h6>
                        <ul class="list-unstyled">
                            <?php if($turnoverRate > 20): ?>
                            <li><i class="bi bi-exclamation-triangle text-warning me-2"></i>High turnover rate</li>
                            <?php else: ?>
                            <li><i class="bi bi-check-circle text-success me-2"></i>Healthy turnover</li>
                            <?php endif; ?>
                            <?php if(!empty($departmentStats)): ?>
                            <li><i class="bi bi-building text-primary me-2"></i>Largest: <?= e($departmentStats[0]['name'] ?? 'N/A') ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
