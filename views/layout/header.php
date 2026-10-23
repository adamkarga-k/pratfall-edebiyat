<?php
/**
 * Pratfall Standalone - Ortak Üstbilgi (Header)
 */

$site_name     = trim( str_ireplace( 'Mecmua', '', Database::get_setting( 'site_name', 'PRATFALL' ) ) );
if ( empty( $site_name ) ) {
    $site_name = 'PRATFALL';
}
$site_slogan   = trim( str_ireplace( 'Mecmua', 'Edebiyat Dergisi & Platformu', Database::get_setting( 'site_slogan', 'Kusurlu Kalma Çabası' ) ) );
$location_badge= Database::get_setting( 'site_location_badge', 'Edebiyat & Fikir & Kültür & Sanat' );
if ( empty( $location_badge ) || strpos( $location_badge, 'İSTANBUL' ) !== false ) {
    $location_badge = 'Edebiyat & Fikir & Kültür & Sanat';
}
$instagram_url = Database::get_setting( 'site_instagram_url', 'https://www.instagram.com/pratfalldergi/' );

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

  <!-- Stiller (Kusurlu-Estetik & Neo-Brutalist) -->
  <link rel="stylesheet" href="<?= SITE_URL; ?>/style.css?v=<?= PRATFALL_APP_VERSION; ?>.<?= time(); ?>" />
  <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/theme.css?v=<?= PRATFALL_APP_VERSION; ?>.<?= time(); ?>" />
  <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/dergiler-vitrin.css?v=<?= PRATFALL_APP_VERSION; ?>.<?= time(); ?>" />
</head>
<body>

<?php if ( ! empty( $is_single ) ) : ?>
  <div class="reading-progress-bar" aria-hidden="true"></div>
<?php endif; ?>

<!-- 1. En Üst Canlı Marquee Duyuru Bandı: "KUSURLU KALMA ÇABASI" (7 Farklı Edebi Tipografik Stil) -->
<div class="pratfall-ticker-wrapper" aria-label="Kusurlu Kalma Çabası">
  <div class="pratfall-ticker-track">
    <?php for ( $loop = 0; $loop < 2; $loop++ ) : ?>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">✦</span>
        <span class="kusurlu-style-1">KUSURLU KALMA ÇABASI</span>
      </div>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">·</span>
        <span class="kusurlu-style-2">KUSURLU KALMA ÇABASI</span>
      </div>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">✦</span>
        <span class="kusurlu-style-3">KUSURLU KALMA ÇABASI</span>
      </div>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">·</span>
        <span class="kusurlu-style-4">KUSURLU KALMA ÇABASI</span>
      </div>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">✦</span>
        <span class="kusurlu-style-5">KUSURLU KALMA ÇABASI</span>
      </div>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">·</span>
        <span class="kusurlu-style-6">KUSURLU KALMA ÇABASI</span>
      </div>
      <div class="pratfall-ticker-item">
        <span class="ticker-dot">✦</span>
        <span class="kusurlu-style-7">KUSURLU KALMA ÇABASI</span>
      </div>
    <?php endfor; ?>
  </div>
</div>

<!-- 2. Editoryal Masthead & Navigasyon -->
<header class="pratfall-header">
  <div class="pratfall-container">
    
    <!-- Gazete Üst Künye Çubuğu (Tarih ve Instagram Yan Yana) -->
    <div class="masthead-top-bar">
      <div class="masthead-info-left">
        <span class="masthead-badge"><?= Helpers::format_date( date('Y-m-d') ); ?></span>
      </div>
      <div class="masthead-info-right">
        <a href="<?= htmlspecialchars( $instagram_url, ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="tape-badge rot-p1" style="font-size:0.75rem; padding:0.22rem 0.7rem; text-decoration:none; color:inherit; display:inline-flex; align-items:center;" title="Pratfall Instagram">
          Instagram
        </a>
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
          <li><a href="<?= SITE_URL; ?>/">Ana Sayfa</a></li>
          <li><a href="<?= SITE_URL; ?>/#dergiler">Dergilerimiz</a></li>
          <li><a href="<?= SITE_URL; ?>/kitaplik">Kitaplık</a></li>
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
