<?php
require_once __DIR__ . '/../config/config.php';
$theme = $_SESSION['user']['theme'] ?? 'light';
$company = $_SESSION['company'] ?? [];
$navSettings = json_decode($company['nav_settings'] ?? '{}', true);
$uiAccent = $navSettings['accent_theme'] ?? 'teal';
$uiSurface = $navSettings['surface_style'] ?? 'glass';
$sidebarDefaultState = $navSettings['sidebar_default_state'] ?? 'expanded';
$themeAttr = htmlspecialchars((string) $theme, ENT_QUOTES, 'UTF-8');
$uiAccentAttr = htmlspecialchars((string) $uiAccent, ENT_QUOTES, 'UTF-8');
$uiSurfaceAttr = htmlspecialchars((string) $uiSurface, ENT_QUOTES, 'UTF-8');
$sidebarDefaultAttr = htmlspecialchars((string) $sidebarDefaultState, ENT_QUOTES, 'UTF-8');
?>
<!doctype html><html data-bs-theme="<?= $themeAttr ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/public/assets/css/style.css?v=20260406c" rel="stylesheet">
<link href="<?= BASE_URL ?>/public/assets/css/modern-ui.css?v=20260406g" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head><body class="theme-<?= $themeAttr ?>" data-ui-accent="<?= $uiAccentAttr ?>" data-ui-surface="<?= $uiSurfaceAttr ?>" data-sidebar-default="<?= $sidebarDefaultAttr ?>">
<div class="app-wrapper">
