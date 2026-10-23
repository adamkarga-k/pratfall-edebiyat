<?php
/**
 * Pratfall Standalone - Yönetim Masası Ortak Üstbilgi
 */

Auth::require_login();
$active_tab = $active_tab ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars( $page_title ?? 'Yönetim Masası', ENT_QUOTES, 'UTF-8' ); ?> ✦ Pratfall Admin</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&family=Newsreader:wght@500;700&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/admin.css?v=<?= PRATFALL_APP_VERSION; ?>" />
</head>
<body class="admin-body">

<header class="admin-navbar">
  <a href="<?= SITE_URL; ?>/admin" class="admin-logo">
    <span>✦ PRATFALL</span>
    <span style="font-size:0.7rem; background:var(--admin-accent); color:#fff; padding:0.15rem 0.4rem;">YÖNETİM MASASI</span>
  </a>

  <ul class="admin-nav-links">
    <li><a href="<?= SITE_URL; ?>/admin" class="<?= $active_tab === 'dashboard' ? 'active' : ''; ?>">Gösterge</a></li>
    <li><a href="<?= SITE_URL; ?>/admin/posts" class="<?= $active_tab === 'posts' ? 'active' : ''; ?>">✍️ Yazılar</a></li>
    <li><a href="<?= SITE_URL; ?>/admin/import-docx" class="<?= $active_tab === 'import' ? 'active' : ''; ?>" style="color:#fdba74; font-weight:700;">📥 Word İçe Aktar</a></li>
    <li><a href="<?= SITE_URL; ?>/admin/authors" class="<?= $active_tab === 'authors' ? 'active' : ''; ?>">👥 Kimler Yazmış?</a></li>
    <li><a href="<?= SITE_URL; ?>/admin/magazines" class="<?= $active_tab === 'magazines' ? 'active' : ''; ?>">📖 Dergilerimiz</a></li>
    <li><a href="<?= SITE_URL; ?>/admin/settings" class="<?= $active_tab === 'settings' ? 'active' : ''; ?>">⚙️ Ayarlar</a></li>
    <li><a href="<?= SITE_URL; ?>/admin/git-sync" class="<?= $active_tab === 'git-sync' ? 'active' : ''; ?>">🔄 Git Senkron</a></li>
    <li><a href="<?= SITE_URL; ?>/" target="_blank" style="opacity:0.8;">↗ Siteme Git</a></li>
    <li><a href="<?= SITE_URL; ?>/admin/logout" style="color:#f87171;">Çıkış Yap ✕</a></li>
  </ul>
</header>

<div class="admin-container">
