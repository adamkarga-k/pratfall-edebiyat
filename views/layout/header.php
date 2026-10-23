<?php
/**
 * Pratfall Standalone - Ortak Üstbilgi (Header)
 */

$site_name     = Database::get_setting( 'site_name', 'PRATFALL' );
$site_slogan   = Database::get_setting( 'site_slogan', 'Kusurlu Kalma Çabası' );
$location_badge= Database::get_setting( 'site_location_badge', '✦ İSTANBUL · KÜLTÜR & MATBAA' );
$issue_badge   = Database::get_setting( 'site_issue_badge', 'CİLT I · SAYI 18' );
$press_badge   = Database::get_setting( 'site_press_badge', 'BAĞIMSIZ BASIN' );

$ticker_items = [
    Database::get_setting( 'ticker_1' ),
    Database::get_setting( 'ticker_2' ),
    Database::get_setting( 'ticker_3' ),
    Database::get_setting( 'ticker_4' ),
];
$ticker_items = array_filter( $ticker_items );
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <!-- SEO & Meta Verileri (Schema.org, OpenGraph, Canonical) -->
  <?= Seo::render_head( $seo_data ?? [] ); ?>

  <!-- Google Fonts: Newsreader, Inter, JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;0,6..72,700;0,6..72,800;1,6..72,400;1,6..72,600&display=swap" rel="stylesheet" />

  <!-- Stiller -->
  <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/theme.css?v=<?= PRATFALL_APP_VERSION; ?>" />
  <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/dergiler-vitrin.css?v=<?= PRATFALL_APP_VERSION; ?>" />
</head>
<body>

<?php if ( ! empty( $is_single ) ) : ?>
  <div class="reading-progress-bar" aria-hidden="true"></div>
<?php endif; ?>

<!-- 1. En Üst Canlı Marquee Duyuru Bandı -->
<?php if ( ! empty( $ticker_items ) ) : ?>
<div class="pratfall-ticker-wrapper" aria-label="Canlı Edebi Akış">
  <div class="pratfall-ticker-track">
    <?php foreach ( $ticker_items as $item ) : ?>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">✦</span>
        <?= htmlspecialchars( $item, ENT_QUOTES, 'UTF-8' ); ?>
      </div>
    <?php endforeach; ?>
    <!-- Kesintisiz akış için kopyası -->
    <?php foreach ( $ticker_items as $item ) : ?>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">✦</span>
        <?= htmlspecialchars( $item, ENT_QUOTES, 'UTF-8' ); ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- 2. Editoryal Masthead & Navigasyon -->
<header class="pratfall-header">
  <div class="pratfall-container">
    
    <!-- Gazete Üst Künye Çubuğu (Tarih, Cilt, Lokasyon) -->
    <div class="masthead-top-bar">
      <div class="masthead-info-left">
        <span class="masthead-badge"><?= Helpers::format_date( date('Y-m-d') ); ?></span>
        <span><?= htmlspecialchars( $location_badge, ENT_QUOTES, 'UTF-8' ); ?></span>
      </div>
      <div class="masthead-info-right">
        <span><?= htmlspecialchars( $issue_badge, ENT_QUOTES, 'UTF-8' ); ?></span>
        <span class="tape-badge rot-p1" style="font-size:0.65rem; padding:0.15rem 0.5rem;"><?= htmlspecialchars( $press_badge, ENT_QUOTES, 'UTF-8' ); ?></span>
      </div>
    </div>

    <!-- Ana Başlık / Logo Alanı -->
    <div class="masthead-main">
      <h1 class="masthead-logo">
        <a href="<?= SITE_URL; ?>/"><?= htmlspecialchars( $site_name, ENT_QUOTES, 'UTF-8' ); ?></a>
      </h1>
      <div class="masthead-tagline">
        <?= htmlspecialchars( $site_slogan, ENT_QUOTES, 'UTF-8' ); ?>
      </div>
    </div>

    <!-- Navigasyon Menüsü ve Eylemler -->
    <div class="pratfall-nav-bar">
      <button type="button" class="mobile-menu-toggle stamp-tag" aria-label="Menüyü Aç / Kapat" aria-expanded="false">
        <span class="menu-icon">☰</span> <span class="menu-label">Menü</span>
      </button>

      <nav class="pratfall-nav" role="navigation" aria-label="Ana Menü">
        <ul class="pratfall-menu">
          <li><a href="<?= SITE_URL; ?>/">Mecmua</a></li>
          <li><a href="<?= SITE_URL; ?>/#dergiler">Dergilerimiz</a></li>
          <li><a href="<?= SITE_URL; ?>/#yazarlar">Kimler Yazmış?</a></li>
          <li><a href="<?= SITE_URL; ?>/#eserler">Son Eserler</a></li>
          <li><a href="<?= SITE_URL; ?>/#manifesto">Pratfall?</a></li>
        </ul>
      </nav>

      <div class="header-actions">
        <button type="button" class="theme-toggle-btn" aria-label="Gece Modunu Değiştir">
          🌙 Gece Modu
        </button>
      </div>
    </div>

  </div>
</header>

<main id="primary" class="site-main">
