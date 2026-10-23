<?php
/**
 * Pratfall Standalone - Yönetim Masası Giriş Ekranı (Login)
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

if ( Auth::check() ) {
    header( 'Location: ' . SITE_URL . '/admin/' );
    exit;
}

$error = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ( Auth::login( $username, $password ) ) {
        header( 'Location: ' . SITE_URL . '/admin/' );
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
<body style="background-color:var(--adm-bg); color:var(--adm-text); font-family:var(--adm-font-sans); display:flex; min-height:100vh; align-items:center; justify-content:center; padding:1.5rem; margin:0;">

  <div class="card" style="width:100%; max-width:400px; padding:2.5rem; border:1px solid var(--adm-border); box-shadow:var(--adm-shadow-md);">
    <div style="text-align:center; margin-bottom:2rem;">
      <div style="width:36px; height:36px; background:var(--adm-primary); color:#fff; border-radius:var(--adm-radius-sm); display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; margin-bottom:0.75rem;">
        ✦
      </div>
      <h1 style="margin:0; font-size:1.4rem; font-weight:800; letter-spacing:-0.02em;">PRATFALL</h1>
      <p style="margin:0.25rem 0 0 0; font-size:0.82rem; color:var(--adm-muted);">
        Yönetim Masası & İçerik Kontrol Merkezi
      </p>
    </div>

    <?php if ( ! empty( $error ) ) : ?>
      <div class="alert alert-danger" style="font-size:0.82rem; padding:0.65rem 0.85rem;">
        <?= htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= SITE_URL; ?>/admin/login.php">
      <div class="form-group">
        <label class="form-label" for="username">Kullanıcı Adı</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="Kullanıcı adınız" required autofocus />
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Şifre</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required />
      </div>

      <div style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.65rem;">
          Giriş Yap →
        </button>
      </div>
    </form>

    <div style="text-align:center; margin-top:1.75rem; border-top:1px solid var(--adm-border-light); padding-top:1rem;">
      <a href="<?= SITE_URL; ?>/" style="font-size:0.8rem; color:var(--adm-muted); text-decoration:none;">
        ← Mecmua Ana Sayfasına Dön
      </a>
    </div>
  </div>

</body>
</html>
