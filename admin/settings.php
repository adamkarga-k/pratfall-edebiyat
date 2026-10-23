<?php
/**
 * Pratfall Standalone - Platform ve Sistem Ayarları
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Site & Platform Ayarları';
$active_tab = 'settings';

$msg = '';
$err = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $action_type = $_POST['action_type'] ?? 'settings';

        if ( $action_type === 'settings' ) {
            $settings_keys = [
                'site_name', 'site_slogan', 'site_location_badge', 'site_instagram_url',
                'ticker_1', 'ticker_2', 'ticker_3', 'ticker_4',
                'authors_badge', 'authors_title', 'authors_desc',
                'feed_badge', 'feed_title',
                'manifesto_title', 'manifesto_badge', 'manifesto_tag', 'manifesto_p1', 'manifesto_p2',
                'footer_slogan', 'footer_desc', 'footer_newsletter_title', 'footer_newsletter_desc', 'footer_copyright',
                'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_pass', 'smtp_from_email', 'smtp_from_name'
            ];

            foreach ( $settings_keys as $k ) {
                if ( isset( $_POST[$k] ) ) {
                    Database::set_setting( $k, trim( $_POST[$k] ) );
                }
            }
            $msg = 'Tüm ayarlar başarıyla kaydedildi.';
        } elseif ( $action_type === 'password' ) {
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass     = $_POST['new_password'] ?? '';
            $user         = Auth::user();

            $db = Database::get();
            $stmt = $db->prepare( "SELECT password_hash FROM users WHERE id = ?" );
            $stmt->execute( [ $user['id'] ] );
            $u = $stmt->fetch();

            if ( $u && password_verify( $current_pass, $u['password_hash'] ) ) {
                if ( strlen( $new_pass ) >= 6 ) {
                    Auth::change_password( $user['id'], $new_pass );
                    $msg = 'Yönetici şifreniz başarıyla güncellendi.';
                } else {
                    $err = 'Yeni şifre en az 6 karakter olmalıdır.';
                }
            } else {
                $err = 'Mevcut şifrenizi yanlış girdiniz.';
            }
        }
    }
}

require PATH_ADMIN . '/layout/header.php';
?>

<div class="admin-header-row">
  <div>
    <h1 class="admin-title">⚙️ Site & Platform Ayarları</h1>
    <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
      Sitedeki tüm editoryal metinleri, manifestoyu, kayan bant cümlelerini ve şifrenizi buradan yönetin.
    </p>
  </div>
</div>

<?php if ( ! empty( $msg ) ) : ?>
  <div class="alert alert-success">✦ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
<?php endif; ?>

<?php if ( ! empty( $err ) ) : ?>
  <div class="alert alert-error"><?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></div>
<?php endif; ?>

<form method="POST" action="<?= SITE_URL; ?>/admin/settings">
  <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
  <input type="hidden" name="action_type" value="settings" />

  <!-- 1. PRATFALL NE DEMEK? / MANİFESTO AYARLARI -->
  <div class="admin-card" style="border-left:6px solid var(--admin-accent);">
    <h2 style="margin-top:0; font-family:var(--admin-serif); font-size:1.4rem;">1. "Pratfall Ne Demek?" & Manifesto Ayarları</h2>
    
    <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">Manifesto Ana Başlığı</label>
        <input type="text" name="manifesto_title" class="form-control" value="<?= htmlspecialchars( Database::get_setting('manifesto_title', 'Pratfall Ne Demek?'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Sol Üst Bant</label>
        <input type="text" name="manifesto_badge" class="form-control" value="<?= htmlspecialchars( Database::get_setting('manifesto_badge', '✦ EDİTORYAL MANİFESTO'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Sağ Üst Pul</label>
        <input type="text" name="manifesto_tag" class="form-control" value="<?= htmlspecialchars( Database::get_setting('manifesto_tag', 'ELLIOT ARONSON · 1966'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">1. Paragraf (Elliot Aronson 1966 & Pratfall Etkisi Deneyi)</label>
      <textarea name="manifesto_p1" class="form-control" rows="3"><?= htmlspecialchars( Database::get_setting('manifesto_p1'), ENT_QUOTES, 'UTF-8' ); ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">2. Paragraf ("Kusurlu Kalma Çabası" Edebiyat Felsefesi)</label>
      <textarea name="manifesto_p2" class="form-control" rows="3"><?= htmlspecialchars( Database::get_setting('manifesto_p2'), ENT_QUOTES, 'UTF-8' ); ?></textarea>
    </div>
  </div>

  <!-- 2. KİMLER YAZMIŞ? & YOKLAMA LİSTESİ -->
  <div class="admin-card">
    <h2 style="margin-top:0; font-family:var(--admin-serif); font-size:1.4rem;">2. Kimler Yazmış? (Yazarlar Masası) Ayarları</h2>
    <div style="display:grid; grid-template-columns: 1fr 1fr 2fr; gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">Üst Rozet</label>
        <input type="text" name="authors_badge" class="form-control" value="<?= htmlspecialchars( Database::get_setting('authors_badge', '✦ KİMLER YAZMIŞ?'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Başlık</label>
        <input type="text" name="authors_title" class="form-control" value="<?= htmlspecialchars( Database::get_setting('authors_title', 'Yazarlar Masası'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Açıklama</label>
        <input type="text" name="authors_desc" class="form-control" value="<?= htmlspecialchars( Database::get_setting('authors_desc', 'Burası sadece yoklama listesi.'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
    </div>
  </div>

  <!-- 3. KAYAN DUYURU BANDI (TICKER) -->
  <div class="admin-card">
    <h2 style="margin-top:0; font-family:var(--admin-serif); font-size:1.4rem;">3. Canlı Kayan Duyuru Bandı (Ticker)</h2>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">1. Duyuru</label>
        <input type="text" name="ticker_1" class="form-control" value="<?= htmlspecialchars( Database::get_setting('ticker_1'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">2. Duyuru</label>
        <input type="text" name="ticker_2" class="form-control" value="<?= htmlspecialchars( Database::get_setting('ticker_2'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">3. Duyuru</label>
        <input type="text" name="ticker_3" class="form-control" value="<?= htmlspecialchars( Database::get_setting('ticker_3'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">4. Duyuru</label>
        <input type="text" name="ticker_4" class="form-control" value="<?= htmlspecialchars( Database::get_setting('ticker_4'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
    </div>
  </div>

  <!-- 4. ÜSTBİLGİ (MASTHEAD) & SLOGAN -->
  <div class="admin-card">
    <h2 style="margin-top:0; font-family:var(--admin-serif); font-size:1.4rem;">4. Platform Kimliği & Gazete Künyesi</h2>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">Platform Adı</label>
        <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars( Database::get_setting('site_name', 'PRATFALL'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Slogan</label>
        <input type="text" name="site_slogan" class="form-control" value="<?= htmlspecialchars( Database::get_setting('site_slogan', 'Kusurlu Kalma Çabası'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Tarih Yanı Künye / Rozet</label>
        <input type="text" name="site_location_badge" class="form-control" value="<?= htmlspecialchars( Database::get_setting('site_location_badge', 'Edebiyat & Fikir & Kültür & Sanat'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Sağ Üst Instagram Bağlantısı</label>
        <input type="text" name="site_instagram_url" class="form-control" value="<?= htmlspecialchars( Database::get_setting('site_instagram_url', 'https://www.instagram.com/pratfalldergi/'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
    </div>
  </div>

  <!-- 5. ESERLER AKIŞI & ALTBİLGİ (FOOTER) -->
  <div class="admin-card">
    <h2 style="margin-top:0; font-family:var(--admin-serif); font-size:1.4rem;">5. Eserler Akışı ve Footer</h2>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">Yazılar Bölüm Başlığı</label>
        <input type="text" name="feed_title" class="form-control" value="<?= htmlspecialchars( Database::get_setting('feed_title', 'Son Eserler & Yazılar'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Yazılar Bölüm Rozeti</label>
        <input type="text" name="feed_badge" class="form-control" value="<?= htmlspecialchars( Database::get_setting('feed_badge', 'YENİ METİNLER'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Footer Tanıtım Metni</label>
        <textarea name="footer_desc" class="form-control" rows="2"><?= htmlspecialchars( Database::get_setting('footer_desc'), ENT_QUOTES, 'UTF-8' ); ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Telif / Haklar Metni</label>
        <input type="text" name="footer_copyright" class="form-control" value="<?= htmlspecialchars( Database::get_setting('footer_copyright', 'Tüm hakları yazarlara ve platforma aittir.'), ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
    </div>
  </div>

  <!-- 6. E-POSTA DAĞITIMI & SMTP AYARLARI -->
  <div class="admin-card" style="border-left:6px solid #8b1e0f;">
    <h2 style="margin-top:0; font-family:var(--admin-serif); font-size:1.4rem;">6. Kurumsal E-Posta & SMTP Ayarları (Bülten Dağıtımı)</h2>
    <p style="font-size:0.85rem; color:var(--admin-muted); margin-top:-0.5rem; margin-bottom:1.25rem;">
      Abonelerinize gönderdiğiniz bültenlerin spam kutusuna düşmeden doğrudan gelen kutusuna ulaşması için cPanel veya kurumsal mail bilgilerinizi girin.
    </p>

    <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">SMTP Sunucu Adresi (Host)</label>
        <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars( Database::get_setting('smtp_host', 'mail.pratfall.com.tr'), ENT_QUOTES, 'UTF-8' ); ?>" placeholder="mail.pratfall.com.tr" />
      </div>
      <div class="form-group">
        <label class="form-label">Port</label>
        <input type="text" name="smtp_port" class="form-control" value="<?= htmlspecialchars( Database::get_setting('smtp_port', '465'), ENT_QUOTES, 'UTF-8' ); ?>" placeholder="465 veya 587" />
      </div>
      <div class="form-group">
        <label class="form-label">Şifreleme Türü</label>
        <select name="smtp_secure" class="form-control">
          <option value="ssl" <?= Database::get_setting('smtp_secure', 'ssl') === 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
          <option value="tls" <?= Database::get_setting('smtp_secure') === 'tls' ? 'selected' : ''; ?>>TLS (Port 587)</option>
          <option value="none" <?= Database::get_setting('smtp_secure') === 'none' ? 'selected' : ''; ?>>Yok / Düz</option>
        </select>
      </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">SMTP Kullanıcı Adı / E-Posta</label>
        <input type="email" name="smtp_user" class="form-control" value="<?= htmlspecialchars( Database::get_setting('smtp_user', ''), ENT_QUOTES, 'UTF-8' ); ?>" placeholder="bulten@pratfall.com.tr" />
      </div>
      <div class="form-group">
        <label class="form-label">SMTP Şifresi</label>
        <input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars( Database::get_setting('smtp_pass', ''), ENT_QUOTES, 'UTF-8' ); ?>" placeholder="••••••••••••" />
      </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">Gönderen E-Posta (From)</label>
        <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars( Database::get_setting('smtp_from_email', ''), ENT_QUOTES, 'UTF-8' ); ?>" placeholder="bulten@pratfall.com.tr" />
      </div>
      <div class="form-group">
        <label class="form-label">Gönderen Adı</label>
        <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars( Database::get_setting('smtp_from_name', 'Pratfall Edebiyat & Matbaa Bülteni'), ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Pratfall Edebiyat Bülteni" />
      </div>
    </div>

    <div style="margin-top:1.5rem;">
      <button type="submit" class="btn btn-primary" style="padding:0.75rem 2rem; font-size:1rem;">
        ✦ Tüm Ayarları Kaydet
      </button>
    </div>
  </div>
</form>

<!-- 6. YÖNETİCİ ŞİFRESİ DEĞİŞTİRME -->
<div class="admin-card" style="max-width:600px; border-top:4px solid var(--admin-border);">
  <h2 style="margin-top:0; font-family:var(--admin-serif); font-size:1.3rem;">Yönetici Şifresini Değiştir</h2>
  
  <form method="POST" action="<?= SITE_URL; ?>/admin/settings">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
    <input type="hidden" name="action_type" value="password" />

    <div class="form-group">
      <label class="form-label" for="current_password">Mevcut Şifre</label>
      <input type="password" name="current_password" id="current_password" class="form-control" required placeholder="••••••••" />
    </div>

    <div class="form-group">
      <label class="form-label" for="new_password">Yeni Şifre (En az 6 karakter)</label>
      <input type="password" name="new_password" id="new_password" class="form-control" required placeholder="Yeni güçlü şifreniz" />
    </div>

    <div style="margin-top:1rem;">
      <button type="submit" class="btn btn-success" style="padding:0.6rem 1.25rem;">
        Şifreyi Güncelle ↗
      </button>
    </div>
  </form>
</div>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
