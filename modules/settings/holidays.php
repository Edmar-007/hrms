<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/csrf.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';

require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$month = $_GET['month'] ?? date('Y-m');

// Handle actions
if (is_post()) {
    $action = $_POST['action'] ?? null;

    if ($action === 'add' && verify_csrf($_POST['csrf'] ?? '')) {
        $holidayDate = $_POST['holiday_date'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if (!empty($holidayDate) && !empty($name)) {
            try {
                $st = $pdo->prepare("
                    INSERT INTO holidays (company_id, holiday_date, name, is_public)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE name = VALUES(name), is_public = VALUES(is_public)
                ");
                $st->execute([$cid, $holidayDate, $name, $isPublic]);
                log_activity('create', 'holidays', $pdo->lastInsertId(), ['date' => $holidayDate, 'name' => $name]);
                redirect("?month=$month&msg=added");
            } catch (PDOException $e) {
                $error = 'Error adding holiday';
            }
        }
    }

    if ($action === 'delete' && verify_csrf($_POST['csrf'] ?? '')) {
        $holidayId = (int)$_POST['holiday_id'];
        $st = $pdo->prepare("DELETE FROM holidays WHERE id = ? AND company_id = ?");
        $st->execute([$holidayId, $cid]);
        redirect("?month=$month&msg=deleted");
    }
}

// Parse month for calendar display
$parts = explode('-', $month);
$year = (int)$parts[0];
$monthNum = (int)$parts[1];

// Get holidays for the month
$holidays = $pdo->prepare("
    SELECT * FROM holidays
    WHERE company_id = ? AND YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?
    ORDER BY holiday_date ASC
");
$holidays->execute([$cid, $year, $monthNum]);
$monthHolidays = $holidays->fetchAll();

// Get all holidays for the year
$yearHolidays = $pdo->prepare("
    SELECT * FROM holidays
    WHERE company_id = ? AND YEAR(holiday_date) = ?
    ORDER BY holiday_date ASC
");
$yearHolidays->execute([$cid, $year]);
$allHolidays = $yearHolidays->fetchAll();

// Calendar generation
$firstDay = mktime(0, 0, 0, $monthNum, 1, $year);
$lastDay = date('t', $firstDay);
$daysInWeek = date('N', $firstDay) - 1; // 0 = Monday, not 0-indexed for Sundays

$holidaysByDate = [];
foreach ($monthHolidays as $h) {
    $holidaysByDate[substr($h['holiday_date'], 8)] = $h;
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-calendar-event me-2"></i>Holiday Calendar</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-2"></i>Add Holiday
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
<?php $msg = $_GET['msg'] ?? ''; ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?= $msg === 'added' ? 'Holiday added successfully!' : ($msg === 'deleted' ? 'Holiday deleted!' : '') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-9">
        <!-- Calendar View -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <a href="?month=<?= date('Y-m', strtotime('-1 month', mktime(0,0,0,$monthNum,1,$year))) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    &nbsp;<strong><?= date('F Y', mktime(0, 0, 0, $monthNum, 1, $year)) ?></strong>&nbsp;
                    <a href="?month=<?= date('Y-m', strtotime('+1 month', mktime(0,0,0,$monthNum,1,$year))) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </h6>
                <a href="?month=<?= date('Y-m') ?>" class="btn btn-sm btn-outline-secondary">Today</a>
            </div>

            <div class="card-body">
                <div style="overflow-x:auto">
                    <table class="calendar-table" style="width:100%; border-collapse:collapse">
                        <thead>
                            <tr style="background:#f8f9fa">
                                <th style="padding:10px; border:1px solid #dee2e6">Mon</th>
                                <th style="padding:10px; border:1px solid #dee2e6">Tue</th>
                                <th style="padding:10px; border:1px solid #dee2e6">Wed</th>
                                <th style="padding:10px; border:1px solid #dee2e6">Thu</th>
                                <th style="padding:10px; border:1px solid #dee2e6">Fri</th>
                                <th style="padding:10px; border:1px solid #dee2e6;background:#ffe0e0">Sat</th>
                                <th style="padding:10px; border:1px solid #dee2e6;background:#ffe0e0">Sun</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $day = 1;
                            $weekRow = 0;
                            ?>
                            <tr>
                                <?php for ($i = 0; $i < $daysInWeek; $i++): ?>
                                <td style="padding:10px; border:1px solid #dee2e6; background:#f8f9fa; height:80px">&nbsp;</td>
                                <?php endfor; ?>

                                <?php
                                $cellsInFirstWeek = 7 - $daysInWeek;
                                for ($i = 0; $i < $cellsInFirstWeek && $day <= $lastDay; $i++):
                                    $dateStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                                    $hasHoliday = isset($holidaysByDate[$dateStr]);
                                    $dow = ($daysInWeek + $i) % 7; // 5=Saturday, 6=Sunday
                                    $isWeekend = ($dow >= 5);
                                    $isBgColor = $hasHoliday ? '#fff3cd' : ($isWeekend ? '#ffe0e0' : '#ffffff');
                                ?>
                                <td style="padding:10px; border:1px solid #dee2e6; height:80px; background:<?= $isBgColor ?>; vertical-align:top">
                                    <div style="font-weight:bold; margin-bottom:5px"><?= $day ?></div>
                                    <?php if ($hasHoliday): ?>
                                    <div style="font-size:0.75rem; background:#ff9f1c; color:white; padding:2px 4px; border-radius:3px; display:inline-block">
                                        <?= e(substr($holidaysByDate[$dateStr]['name'], 0, 10)) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <?php $day++; endfor; ?>
                            </tr>

                            <?php
                            while ($day <= $lastDay):
                            ?>
                            <tr>
                                <?php for ($i = 0; $i < 7 && $day <= $lastDay; $i++):
                                    $dateStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                                    $hasHoliday = isset($holidaysByDate[$dateStr]);
                                    $isBgColor = $hasHoliday ? '#fff3cd' : ($i >= 5 ? '#ffe0e0' : '#ffffff');
                                ?>
                                <td style="padding:10px; border:1px solid #dee2e6; height:80px; background:<?= $isBgColor ?>; vertical-align:top">
                                    <div style="font-weight:bold; margin-bottom:5px"><?= $day ?></div>
                                    <?php if ($hasHoliday): ?>
                                    <div style="font-size:0.75rem; background:#ff9f1c; color:white; padding:2px 4px; border-radius:3px; display:inline-block">
                                        <?= e(substr($holidaysByDate[$dateStr]['name'], 0, 10)) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <?php $day++; endfor; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <!-- Holidays List -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Holidays in <?= date('Y', mktime(0, 0, 0, $monthNum, 1, $year)) ?></h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($allHolidays)): ?>
                <div class="empty-state p-3">
                    <i class="bi bi-inbox"></i>
                    <p>No holidays defined for this year</p>
                </div>
                <?php else: ?>
                <div style="max-height:600px; overflow-y:auto">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($allHolidays as $h): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div><strong><?= e($h['name']) ?></strong></div>
                                <small class="text-muted"><?= date('M d, Y', strtotime($h['holiday_date'])) ?></small>
                                <br>
                                <small class="badge <?= $h['is_public'] ? 'bg-info' : 'bg-light text-dark' ?>">
                                    <?= $h['is_public'] ? 'Public' : 'Company' ?>
                                </small>
                            </div>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this holiday?')">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Common Holidays Template -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Common Holidays (PH)</h6>
            </div>
            <div class="card-body small text-muted">
                <ul class="mb-0" style="font-size:0.85rem">
                    <li>Jan 1 - New Year's Day</li>
                    <li>Feb 10-12 - EDSA Revolution</li>
                    <li>Mar/Apr - Maundy Thursday</li>
                    <li>Mar/Apr - Good Friday</li>
                    <li>Apr 9 - Day of Valor</li>
                    <li>Jun 12 - Independence Day</li>
                    <li>Aug 21 - Ninoy Aquino Day</li>
                    <li>Nov 1 - All Saints Day</li>
                    <li>Nov 30 - Bonifacio Day</li>
                    <li>Dec 8 - Feast of the Immaculate Conception</li>
                    <li>Dec 25 - Christmas Day</li>
                    <li>Dec 26 - Additional Special Day</li>
                    <li>Dec 30 - Rizal Day</li>
                    <li>Dec 31 - New Year's Eve</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Add Holiday Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label">Holiday Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="holiday_date" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Holiday Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., New Year's Day" required>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_public" id="isPublic" checked>
                            <label class="form-check-label" for="isPublic">
                                Public Holiday (affects all employees)
                            </label>
                            <small class="d-block text-muted mt-1">
                                If unchecked, only applies to this company
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
