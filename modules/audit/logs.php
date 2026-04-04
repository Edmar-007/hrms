<?php
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/nav.php';
require_login();
require_role(['Admin', 'HR Officer']);

$cid = company_id() ?? 1;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');

$where = "WHERE a.company_id = ?";
$params = [$cid];
if ($q !== '') {
    $where .= " AND (u.email LIKE ? OR a.entity_type LIKE ? OR a.details LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($action !== '') {
    $where .= " AND a.action = ?";
    $params[] = $action;
}

$totalSt = $pdo->prepare("SELECT COUNT(*) c FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id $where");
$totalSt->execute($params);
$total = (int)$totalSt->fetch()['c'];

$st = $pdo->prepare("SELECT a.*, u.email FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id $where ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-journal-text me-2"></i>Audit Logs</h4>
</div>
<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-md-4"><input class="form-control" name="q" placeholder="Search user/entity/details" value="<?= e($q) ?>"></div>
        <div class="col-md-3"><input class="form-control" name="action" placeholder="Action (e.g. update)" value="<?= e($action) ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form>
</div></div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>IP</th><th>Details</th></tr></thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No logs found</td></tr>
            <?php else: foreach($rows as $r): ?>
                <tr>
                    <td><?= e($r['created_at']) ?></td>
                    <td><?= e($r['email'] ?? '-') ?></td>
                    <td><span class="badge bg-info"><?= e($r['action']) ?></span></td>
                    <td><?= e($r['entity_type'] ?? '-') ?></td>
                    <td><?= e($r['entity_id'] ?? '-') ?></td>
                    <td><?= e($r['ip_address'] ?? '-') ?></td>
                    <td><small><?= e(substr((string)$r['details'], 0, 120)) ?></small></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $pages = max(1, (int)ceil($total / $perPage)); if ($pages > 1): ?>
<nav class="mt-3"><ul class="pagination">
<?php for($i=1;$i<=$pages;$i++): ?>
    <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&action=<?= urlencode($action) ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>

