<?php
/**
 * Pratfall Standalone - Modern & Sakin Yönetim Masası (Header)
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( dirname( __DIR__ ) ) . '/config.php';
}

Auth::require_login();
$active_tab = $active_tab ?? 'dashboard';
$current_user = Auth::user();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars( $page_title ?? 'Kontrol Paneli', ENT_QUOTES, 'UTF-8' ); ?> ✦ Pratfall Yönetim</title>

  <!-- Google Fonts: Inter, JetBrains Mono, Newsreader -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,600;1,6..72,400&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/admin.css?v=<?= PRATFALL_APP_VERSION; ?>" />
</head>
<body>

<div class="admin-app-wrapper">
  
  <!-- Mobil Menü Karartma Katmanı (Backdrop) -->
  <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleAdminSidebar(false)"></div>

  <!-- 1. SOL MENÜ (SIDEBAR) -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
      <a href="<?= SITE_URL; ?>/admin/index.php" class="sidebar-brand">
        <div class="sidebar-brand-icon">✦</div>
        <div>
          <div class="sidebar-brand-name">PRATFALL</div>
          <div style="font-size:0.72rem; color:var(--adm-subtle); font-family:var(--adm-font-mono);">Edebiyat Platformu</div>
        </div>
      </a>
      <button type="button" class="sidebar-close-btn" onclick="toggleAdminSidebar(false)" aria-label="Menüyü Kapat" title="Menüyü Kapat">
        ✕
      </button>
    </div>

    <div class="sidebar-nav-scroll">
      
      <!-- GENEL -->
      <div class="sidebar-group-title">Genel</div>
      <ul class="sidebar-menu">
        <li>
          <a href="<?= SITE_URL; ?>/admin/index.php" class="sidebar-link <?= $active_tab === 'dashboard' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">📊</span>
              <span>Kontrol Paneli</span>
            </span>
          </a>
        </li>
      </ul>

      <!-- İÇERİK -->
      <div class="sidebar-group-title">İçerik</div>
      <ul class="sidebar-menu">
        <li>
          <a href="<?= SITE_URL; ?>/admin/posts.php" class="sidebar-link <?= $active_tab === 'posts' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">✍️</span>
              <span>Yazılar & Eserler</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/categories.php" class="sidebar-link <?= $active_tab === 'categories' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">🏷️</span>
              <span>Kategoriler</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/media.php" class="sidebar-link <?= $active_tab === 'media' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">🖼️</span>
              <span>Medya Kütüphanesi</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/import-docx.php" class="sidebar-link <?= $active_tab === 'import' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">📥</span>
              <span>Word'den Toplu Aktar</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/magazines.php" class="sidebar-link <?= $active_tab === 'magazines' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">📖</span>
              <span>Dergilerimiz</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/books.php" class="sidebar-link <?= $active_tab === 'books' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">📚</span>
              <span>Pratfall Kitaplığı</span>
            </span>
          </a>
        </li>
      </ul>

      <!-- YÖNET -->
      <div class="sidebar-group-title">Bülten & Dağıtım</div>
      <ul class="sidebar-menu">
        <li>
          <a href="<?= SITE_URL; ?>/admin/subscribers.php" class="sidebar-link <?= ( $active_tab === 'subscribers' && strpos($_SERVER['REQUEST_URI'] ?? '', 'newsletter-send') === false ) ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">📬</span>
              <span>Aboneler (Şifreli Kasa)</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/newsletter-send.php" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'] ?? '', 'newsletter-send') !== false ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">✍️</span>
              <span>Yeni Bülten Gönder</span>
            </span>
          </a>
        </li>
      </ul>

      <div class="sidebar-group-title">Yönetim</div>
      <ul class="sidebar-menu">
        <li>
          <a href="<?= SITE_URL; ?>/admin/authors.php" class="sidebar-link <?= $active_tab === 'authors' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">👥</span>
              <span>Kimler Yazmış? (Yazarlar)</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/backup.php" class="sidebar-link <?= $active_tab === 'backup' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">💾</span>
              <span>Veritabanı Yedeği İndir</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/settings.php" class="sidebar-link <?= $active_tab === 'settings' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">⚙️</span>
              <span>Site Ayarları & Manifesto</span>
            </span>
          </a>
        </li>
        <li>
          <a href="<?= SITE_URL; ?>/admin/git-sync.php" class="sidebar-link <?= $active_tab === 'git-sync' ? 'active' : ''; ?>">
            <span class="sidebar-link-content">
              <span class="sidebar-link-icon">🔄</span>
              <span>cPanel Git Dağıtım</span>
            </span>
          </a>
        </li>
      </ul>

    </div>

    <div class="sidebar-footer">
      <span>Pratfall CMS v<?= PRATFALL_APP_VERSION; ?></span>
      <span style="color:var(--adm-green); font-size:0.7rem;">● Çevrimiçi</span>
    </div>
  </aside>

  <!-- 2. SAĞ ANA ALAN -->
  <div class="admin-main-area">
    
    <!-- Üst Gezinme Çubuğu (Top Bar) -->
    <header class="admin-topbar">
      <div class="topbar-left">
        <button type="button" class="sidebar-toggle-btn" onclick="toggleAdminSidebar(true)" aria-label="Menüyü Aç" title="Menüyü Aç">
          <span class="hamburger-bar"></span>
          <span class="hamburger-bar"></span>
          <span class="hamburger-bar"></span>
        </button>
        <h1 class="topbar-page-title"><?= htmlspecialchars( $page_title ?? 'Kontrol Paneli', ENT_QUOTES, 'UTF-8' ); ?></h1>
      </div>

      <div class="topbar-right">
        <!-- Hızlı Eylem Butonları -->
        <a href="<?= SITE_URL; ?>/admin/posts.php?action=new" class="btn btn-sm btn-primary" title="Yeni Eser Ekle">
          <span>+</span> <span class="btn-text-responsive">Yazı Ekle</span>
        </a>

        <div class="topbar-actions-desktop">
          <a href="<?= SITE_URL; ?>/admin/import-docx.php" class="btn btn-sm btn-accent" title="Word'den Eser Aktar">
            <span>📥</span> Word Aktar
          </a>
          <a href="<?= SITE_URL; ?>/admin/magazines.php?action=new" class="btn btn-sm" title="Yeni Dergi Sayısı Tanımla">
            <span>📖</span> Dergi Ekle
          </a>
          <a href="<?= SITE_URL; ?>/admin/books.php?action=new" class="btn btn-sm" title="Yeni Kitap Tanımla">
            <span>📚</span> Kitap Ekle
          </a>
          <div style="height:20px; width:1px; background:var(--adm-border); margin:0 0.25rem;"></div>
        </div>

        <a href="<?= SITE_URL; ?>/" target="_blank" class="btn btn-sm topbar-btn-site" title="Siteyi yeni sekmede aç">
          <span>↗</span> <span class="btn-text-responsive">Siteyi Gör</span>
        </a>

        <div class="user-profile-badge">
          <div class="user-avatar-dot"><?= strtoupper( substr( $current_user['username'] ?? 'A', 0, 1 ) ); ?></div>
          <span class="user-name-text"><?= htmlspecialchars( $current_user['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8' ); ?></span>
        </div>

        <a href="<?= SITE_URL; ?>/admin/logout.php" class="btn btn-sm btn-logout" style="color:#ef4444;" title="Oturumu Kapat">
          Çıkış
        </a>
      </div>
    </header>

    <!-- Sayfa İçeriği -->
    <main class="admin-content-container">
