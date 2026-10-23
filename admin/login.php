<?php
/**
 * Pratfall Standalone - Yönetim Masası Giriş Ekranı (Login)
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

if ( Auth::check() ) {
    header( 'Location: ' . SITE_URL . '/admin' );
    exit;
}

$error = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ( Auth::login( $username, $password ) ) {
        header( 'Location: ' . SITE_URL . '/admin' );
        exit;
    } else {
        $error = 'Kullanıcı adı veya şifre hatalı. Lütfen tekrar deneyin.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Giriş Yap ✦ Pratfall Yönetim Masası</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@400;700&family=Newsreader:ital,wght@1,600&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/admin.css?v=<?= PRATFALL_APP_VERSION; ?>" />
</head>
<body class="admin-body" style="display:flex; min-height:100vh; align-items:center; justify-content:center; padding:1.5rem;">

  <div class="admin-card" style="width:100%; max-width:440px; padding:2.5rem; border-top:6px solid var(--admin-accent);">
    <div style="text-align:center; margin-bottom:2rem;">
      <div style="font-family:var(--admin-mono); font-size:0.75rem; letter-spacing:0.1em; color:var(--admin-muted); margin-bottom:0.25rem;">
        ✦ KUSURLU KALMA ÇABASI
      </div>
      <h1 style="margin:0; font-family:var(--admin-serif); font-size:2.2rem;">Yönetim Masası</h1>
      <p style="margin:0.5rem 0 0 0; font-size:0.85rem; color:var(--admin-muted);">
        Pratfall Mecmua yayın ve içerik kontrol merkezi
      </p>
    </div>

    <?php if ( ! empty( $error ) ) : ?>
      <div class="alert alert-error">
        <?= htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= SITE_URL; ?>/admin/login">
      <div class="form-group">
        <label class="form-label" for="username">Kullanıcı Adı</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="admin" required autofocus />
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Şifre</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required />
      </div>

      <div style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.75rem;">
          Yönetim Masasına Giriş Yap →
        </button>
      </div>
    </form>

    <div style="text-align:center; margin-top:2rem; border-top:1px solid #e7e5e4; padding-top:1rem;">
      <a href="<?= SITE_URL; ?>/" style="font-family:var(--admin-mono); font-size:0.8rem; color:var(--admin-text); text-decoration:none;">
        ← Mecmua Ana Sayfasına Dön
      </a>
    </div>
  </div>

</body>
</html>
