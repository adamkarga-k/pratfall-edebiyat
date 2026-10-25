<?php
/**
 * Pratfall Standalone - Ana Giriş Noktası (Front Controller)
 */

define( 'PRATFALL_ROOT', __DIR__ );
require_once PRATFALL_ROOT . '/config.php';

// Veritabanı ve Şemayı Hazırla
Database::get();

// 1. Arama Motoru ve Sistem Rotaları
Router::get( '/sitemap.xml', function() {
    Seo::generate_sitemap();
} );

Router::get( '/robots.txt', function() {
    Seo::generate_robots();
} );

// 2. Ön Yüz (Front-End) Rotaları
Router::get( '/', PATH_VIEWS . '/front.php' );
Router::get( '/dergiler', PATH_VIEWS . '/dergiler.php' );
Router::get( '/kitaplik', PATH_VIEWS . '/kitaplik.php' );
Router::get( '/kitaplik.php', PATH_VIEWS . '/kitaplik.php' );
Router::get( '/pratfall-kitap', PATH_VIEWS . '/kitaplik.php' );
Router::get( '/kitaplar', PATH_VIEWS . '/kitaplik.php' );
Router::get( '/yazarlar', PATH_VIEWS . '/yazarlar.php' );
Router::get( '/yazarlar.php', PATH_VIEWS . '/yazarlar.php' );
Router::get( '/eser/{slug}', PATH_VIEWS . '/single.php' );
Router::get( '/yazar/{slug}', PATH_VIEWS . '/author.php' );
Router::get( '/sair/{slug}', PATH_VIEWS . '/author.php' );
Router::get( '/kategori/{slug}', PATH_VIEWS . '/category.php' );

// 3. Yönetim Paneli (Admin) Rotaları (Hem .php hem uzantısız destekler)
Router::any( '/admin', PATH_ADMIN . '/index.php' );
Router::any( '/admin/', PATH_ADMIN . '/index.php' );
Router::any( '/admin/index.php', PATH_ADMIN . '/index.php' );

Router::any( '/admin/login', PATH_ADMIN . '/login.php' );
Router::any( '/admin/login.php', PATH_ADMIN . '/login.php' );

Router::get( '/admin/logout', function() {
    Auth::logout();
    header( 'Location: ' . SITE_URL . '/admin/login.php' );
    exit;
} );
Router::get( '/admin/logout.php', function() {
    Auth::logout();
    header( 'Location: ' . SITE_URL . '/admin/login.php' );
    exit;
} );

Router::any( '/admin/posts', PATH_ADMIN . '/posts.php' );
Router::any( '/admin/posts.php', PATH_ADMIN . '/posts.php' );

Router::any( '/admin/categories', PATH_ADMIN . '/categories.php' );
Router::any( '/admin/categories.php', PATH_ADMIN . '/categories.php' );

Router::any( '/admin/media', PATH_ADMIN . '/media.php' );
Router::any( '/admin/media.php', PATH_ADMIN . '/media.php' );

Router::any( '/admin/backup', PATH_ADMIN . '/backup.php' );
Router::any( '/admin/backup.php', PATH_ADMIN . '/backup.php' );

Router::any( '/admin/authors', PATH_ADMIN . '/authors.php' );
Router::any( '/admin/authors.php', PATH_ADMIN . '/authors.php' );

Router::any( '/admin/magazines', PATH_ADMIN . '/magazines.php' );
Router::any( '/admin/magazines.php', PATH_ADMIN . '/magazines.php' );

Router::any( '/admin/books', PATH_ADMIN . '/books.php' );
Router::any( '/admin/books.php', PATH_ADMIN . '/books.php' );

Router::any( '/admin/subscribers', PATH_ADMIN . '/subscribers.php' );
Router::any( '/admin/subscribers.php', PATH_ADMIN . '/subscribers.php' );

Router::any( '/admin/newsletter-send', PATH_ADMIN . '/newsletter-send.php' );
Router::any( '/admin/newsletter-send.php', PATH_ADMIN . '/newsletter-send.php' );

Router::any( '/admin/import-docx', PATH_ADMIN . '/import-docx.php' );
Router::any( '/admin/import-docx.php', PATH_ADMIN . '/import-docx.php' );

Router::any( '/admin/settings', PATH_ADMIN . '/settings.php' );
Router::any( '/admin/settings.php', PATH_ADMIN . '/settings.php' );

Router::any( '/admin/git-sync', PATH_ADMIN . '/git-sync.php' );
Router::any( '/admin/git-sync.php', PATH_ADMIN . '/git-sync.php' );

// 4. Bülten Rotaları (Askeri AES-256 Şifreli & Bot Korumalı)
Router::post( '/bulten/abone-ol', function() {
    header( 'Content-Type: application/json; charset=UTF-8' );

    // 1. Bot Tuzağı (Honeypot Kontrolü): Gizli b_hp_check alanı doluysa bot saldırısıdır
    if ( ! empty( $_POST['b_hp_check'] ?? '' ) ) {
        echo json_encode( [ 'success' => true, 'message' => 'Talebiniz işleme alındı.' ] );
        exit;
    }

    // 2. Rate Limiting (Dakikada aşırı istek engelleme)
    $now = time();
    $sub_attempts = $_SESSION['sub_attempts'] ?? [];
    if ( ! is_array( $sub_attempts ) ) {
        $sub_attempts = [];
    }
    $sub_attempts = array_filter( $sub_attempts, function( $t ) use ( $now ) {
        return ( $now - $t ) < 600;
    } );
    if ( count( $sub_attempts ) >= 6 ) {
        echo json_encode( [ 'success' => false, 'message' => 'Güvenlik nedeniyle kısa süre içinde çok fazla deneme yaptınız. Lütfen birkaç dakika bekleyin.' ] );
        exit;
    }
    $sub_attempts[] = $now;
    $_SESSION['sub_attempts'] = $sub_attempts;

    // 3. E-Posta Doğrulama
    $email = trim( (string) ( $_POST['email'] ?? '' ) );
    if ( empty( $email ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
        echo json_encode( [ 'success' => false, 'message' => 'Lütfen geçerli bir e-posta adresi yazın.' ] );
        exit;
    }

    $email = strtolower( $email );
    $db = Database::get();

    // 4. Kör İndeks (Blind Indexing) ile $O(1)$ Hızında ve Veritabanı Açılsa Bile Korumalı Arama
    $email_hash = Crypto::hash( $email );

    $stmt = $db->prepare( "SELECT id, status, unsubscribe_token FROM subscribers WHERE email_hash = ?" );
    $stmt->execute( [ $email_hash ] );
    $existing = $stmt->fetch();

    if ( $existing ) {
        if ( $existing['status'] === 'active' ) {
            echo json_encode( [ 'success' => true, 'message' => '✦ E-posta adresiniz bülten listemizde zaten kayıtlı. İlginiz için teşekkür ederiz!' ] );
            exit;
        } else {
            // Daha önce ayrılmışsa yeniden aktifleştir
            $upd = $db->prepare( "UPDATE subscribers SET status = 'active', unsubscribed_at = NULL WHERE id = ?" );
            $upd->execute( [ $existing['id'] ] );
            echo json_encode( [ 'success' => true, 'message' => '✦ Harika! Bülten aboneliğiniz tekrar aktif edildi.' ] );
            exit;
        }
    }

    // 5. Askeri Seviye AES-256-CBC ile Şifrele ve Güvenle Sakla
    try {
        $encrypted_email = Crypto::encrypt( $email );
        $token = Crypto::generateToken( 24 );
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $insert = $db->prepare( "
            INSERT INTO subscribers (email_encrypted, email_hash, status, source, unsubscribe_token, ip_address)
            VALUES (?, ?, 'active', 'site_footer', ?, ?)
        " );
        $insert->execute( [ $encrypted_email, $email_hash, $token, $ip ] );

        echo json_encode( [
            'success' => true,
            'message' => '✦ Tebrikler! Edebiyat ve matbaa bültenimize başarıyla kaydoldunuz.'
        ] );
        exit;
    } catch ( Throwable $e ) {
        echo json_encode( [ 'success' => false, 'message' => 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.' ] );
        exit;
    }
} );

Router::get( '/bulten/ayril', function() {
    $token = trim( (string) ( $_GET['token'] ?? '' ) );
    $message = '';
    $success = false;

    if ( ! empty( $token ) ) {
        $db = Database::get();
        $stmt = $db->prepare( "SELECT id, status FROM subscribers WHERE unsubscribe_token = ?" );
        $stmt->execute( [ $token ] );
        $sub = $stmt->fetch();

        if ( $sub ) {
            $upd = $db->prepare( "UPDATE subscribers SET status = 'unsubscribed', unsubscribed_at = CURRENT_TIMESTAMP WHERE id = ?" );
            $upd->execute( [ $sub['id'] ] );
            $success = true;
            $message = 'E-posta bülteni aboneliğiniz başarıyla sonlandırılmıştır. Dilediğiniz zaman sitemizden tekrar bültene kaydolabilirsiniz.';
        } else {
            $message = 'Geçersiz veya süresi dolmuş abonelik bağlantısı.';
        }
    } else {
        $message = 'Abonelik bağlantı kodu bulunamadı.';
    }
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Bülten Aboneliği ✦ Pratfall</title>
      <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/style.css" />
    </head>
    <body style="min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg-main, #fbf7ee); padding:2rem; font-family:var(--font-body, serif);">
      <div style="max-width:540px; width:100%; background:var(--bg-card, #fff); border:2px solid var(--border-color, #1a1a1a); padding:2.5rem; box-shadow:8px 8px 0px #1a1a1a; text-align:center;">
        <span class="tape-badge" style="display:inline-block; margin-bottom:1.5rem; background:#1a1a1a; color:#fbf7ee; padding:0.4rem 0.8rem; font-family:monospace; font-size:0.8rem;">✦ PRATFALL EDEBİYAT BÜLTENİ</span>
        <h1 style="font-family:var(--font-serif, serif); font-size:1.8rem; margin:0 0 1rem 0;"><?= $success ? 'Abonelikten Ayrıldınız' : 'İşlem Başarısız'; ?></h1>
        <p style="font-size:1rem; line-height:1.6; color:#4a4a4a; margin-bottom:2rem;"><?= htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); ?></p>
        <a href="<?= SITE_URL; ?>/" style="display:inline-block; padding:0.75rem 1.5rem; background:#1a1a1a; color:#fff; text-decoration:none; font-family:monospace; font-size:0.85rem; font-weight:600;">← Ana Sayfaya Dön</a>
      </div>
    </body>
    </html>
    <?php
    exit;
} );

// Yönlendiriciyi Çalıştır
Router::dispatch();
