<?php require_once __DIR__.'/csrf.php'; $u=$_SESSION['user']??null; ?>
<?php if($u): ?></main><?php endif; ?>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/app.clean.js?v=20260406e"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
const USER_ID = <?= $u['id'] ?? 'null' ?>;
const CSRF_TOKEN = '<?= csrf_token() ?>';
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-bs-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', newTheme);
    fetch(BASE_URL + '/api/preferences.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: newTheme, csrf_token: CSRF_TOKEN })
    });
}
</script>
</body></html>
