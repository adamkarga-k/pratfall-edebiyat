<?php
/**
 * Pratfall Standalone - E-Posta Bülteni Gönderici (Toplu E-Posta Dağıtım Masası)
 * 
 * Şifreli kasadaki kayıtlı abonelere edebi bülten ve duyuru gönderir.
 * cPanel / Kurumsal SMTP veya yerel PHP mail() altyapısını kullanır.
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$db = Database::get();
$page_title = 'Yeni E-Posta Bülteni Gönder';
$active_tab = 'subscribers';

$msg = '';
$err = '';
$info = '';

// Aktif Abone Sayısı
$active_count = (int) $db->query( "SELECT COUNT(*) FROM subscribers WHERE status = 'active'" )->fetchColumn();

// SMTP Ayar Kontrolü
$smtp_host = Database::get_setting( 'smtp_host', '' );
$smtp_user = Database::get_setting( 'smtp_user', '' );
$has_smtp  = ( ! empty( $smtp_host ) && ! empty( $smtp_user ) );

// Form Değerleri
$subject     = trim( (string) ( $_POST['subject'] ?? '' ) );
$title       = trim( (string) ( $_POST['title'] ?? '' ) );
$content     = trim( (string) ( $_POST['content'] ?? '' ) );
$btn_text    = trim( (string) ( $_POST['btn_text'] ?? '' ) );
$btn_url     = trim( (string) ( $_POST['btn_url'] ?? '' ) );
$test_email  = trim( (string) ( $_POST['test_email'] ?? ( Auth::user()['email'] ?? '' ) ) );
$send_mode   = $_POST['send_mode'] ?? 'test';

// GÖNDERİM İŞLEMİ
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif ( empty( $subject ) || empty( $title ) || empty( $content ) ) {
        $err = 'Lütfen Konu, E-Posta Başlığı ve İçerik alanlarını eksiksiz doldurun.';
    } else {
        // İçeriği satır sonlarına göre paragraflara çevir
        $formatted_content = nl2br( $content );

        if ( $send_mode === 'test' ) {
            // SADECE TEST E-POSTASI GÖNDER
            if ( empty( $test_email ) || ! filter_var( $test_email, FILTER_VALIDATE_EMAIL ) ) {
                $err = 'Lütfen geçerli bir test e-posta adresi yazın.';
            } else {
                $unsub_url = SITE_URL . '/bulten/ayril?token=DEMO_TEST_TOKEN';
                $html = Mailer::createTemplate( $title, $formatted_content, $unsub_url, $btn_text, $btn_url );
                $res = Mailer::send( $test_email, '[TEST] ' . $subject, $html );

                if ( $res['success'] ) {
                    $msg = "Test bülteni <strong>{$test_email}</strong> adresine başarıyla iletildi! Gelen kutunuzu (ve gereksiz kutusunu) kontrol edin.";
                } else {
                    $err = 'Test gönderimi başarısız oldu: ' . htmlspecialchars( $res['message'] );
                }
            }
        } elseif ( $send_mode === 'all' ) {
            // TÜM AKTİF ABONELERE TOPLU GÖNDERİM
            if ( $active_count === 0 ) {
                $err = 'Gönderilecek aktif abone bulunmuyor.';
            } else {
                $stmt = $db->query( "SELECT id, email_encrypted, unsubscribe_token FROM subscribers WHERE status = 'active'" );
                $sent_count = 0;
                $fail_count = 0;

                while ( $sub = $stmt->fetch() ) {
                    $decrypted_email = Crypto::decrypt( $sub['email_encrypted'] );
                    if ( empty( $decrypted_email ) || ! filter_var( $decrypted_email, FILTER_VALIDATE_EMAIL ) ) {
                        $fail_count++;
                        continue;
                    }

                    $unsub_url = SITE_URL . '/bulten/ayril?token=' . $sub['unsubscribe_token'];
                    $html = Mailer::createTemplate( $title, $formatted_content, $unsub_url, $btn_text, $btn_url );
                    
                    $res = Mailer::send( $decrypted_email, $subject, $html );
                    if ( $res['success'] ) {
                        $sent_count++;
                    } else {
                        $fail_count++;
                    }

                    // Sunucu kaynaklarını korumak için mikro gecikme
                    usleep( 40000 ); // 0.04 saniye
                }

                $msg = "Bülten dağıtımı tamamlandı! <strong>{$sent_count}</strong> aboneye başarıyla iletildi." . ( $fail_count > 0 ? " ({$fail_count} adreste iletim hatası yaşandı)." : "" );
            }
        }
    }
}

require PATH_ADMIN . '/layout/header.php';
?>

<!-- ÜST BAŞLIK VE GEZİNME -->
<div class="content-header">
  <div>
    <h1 class="page-title">✍️ Yeni E-Posta Bülteni Gönder</h1>
    <p class="page-subtitle">Kayıtlı edebiyat severlere şık, brutalist matbaa şablonuyla e-posta mektubu gönderin.</p>
  </div>
  <div class="header-actions">
    <a href="<?= SITE_URL; ?>/admin/subscribers.php" class="btn btn-secondary">
      ← Abone Listesine Dön
    </a>
  </div>
</div>

<?php if ( ! $has_smtp ) : ?>
  <div class="card" style="margin-bottom:1.5rem; padding:1.25rem 1.5rem; background:rgba(234,179,8,0.08); border:1px solid rgba(234,179,8,0.3); border-radius:8px;">
    <div style="display:flex; align-items:flex-start; gap:0.75rem;">
      <div style="font-size:1.4rem; line-height:1;">⚠️</div>
      <div style="font-size:0.86rem; line-height:1.5;">
        <strong>cPanel SMTP Yapılandırılmamış:</strong> Şu an yerel sunucu <code>mail()</code> fonksiyonu devrededir. E-postalarınızın spam kutusuna düşmeden %100 gelen kutusuna ulaşması için <a href="<?= SITE_URL; ?>/admin/settings.php" style="font-weight:700; text-decoration:underline;">Site Ayarları</a> sayfasından kurumsal cPanel e-posta bilgilerinizi (SMTP Host, Port, Şifre) girmeniz tavsiye edilir.
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ( ! empty( $msg ) ) : ?>
  <div class="alert alert-success" style="margin-bottom:1.5rem; background:rgba(30,130,76,0.1); border-left:4px solid var(--adm-green); color:var(--adm-green); padding:1rem; border-radius:6px;">
    <?= $msg; ?>
  </div>
<?php endif; ?>

<?php if ( ! empty( $err ) ) : ?>
  <div class="alert alert-danger" style="margin-bottom:1.5rem; background:rgba(214,48,49,0.1); border-left:4px solid var(--adm-red); color:var(--adm-red); padding:1rem; border-radius:6px;">
    <?= $err; ?>
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 340px; gap:1.75rem; align-items:start;">
  
  <!-- 1. SOL: BÜLTEN FORMU -->
  <div class="card">
    <form method="POST" action="<?= SITE_URL; ?>/admin/newsletter-send.php" id="bultenSendForm">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />

      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" style="font-weight:600;">E-Posta Konusu (Gelen Kutusunda Görünen Başlık): *</label>
        <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars( $subject, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Pratfall Sayı #19 Matbaadan Çıktı · Kusursuzluğun İflası" required style="font-size:1rem; font-weight:600;" />
      </div>

      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" style="font-weight:600;">E-Posta İçi Ana Başlık: *</label>
        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Matbaanın Mürekkebi ve Yeni Sayımız Üzerine Birkaç Söz" required />
      </div>

      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" style="font-weight:600;">Bülten Mektubu / Gövde Metni: *</label>
        <textarea name="content" rows="12" class="form-control" placeholder="Sevgili Pratfall Okuru,&#10;&#10;Bu sayımızda sokakların tereddüdünü ve unutulmuş dizeleri bir araya getirdik..." required style="font-family:Georgia, serif; font-size:0.95rem; line-height:1.7;"><?= htmlspecialchars( $content, ENT_QUOTES, 'UTF-8' ); ?></textarea>
      </div>

      <div style="background:var(--adm-bg); padding:1.25rem; border-radius:8px; margin-bottom:1.5rem; border:1px solid var(--adm-border);">
        <div style="font-size:0.85rem; font-weight:600; margin-bottom:0.75rem;">✦ İsteğe Bağlı Edebi Eylem Butonu (CTA):</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div>
            <label class="form-label" style="font-size:0.78rem;">Buton Metni:</label>
            <input type="text" name="btn_text" class="form-control" value="<?= htmlspecialchars( $btn_text, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Yeni Sayıyı İncele" style="font-size:0.85rem;" />
          </div>
          <div>
            <label class="form-label" style="font-size:0.78rem;">Buton Linki:</label>
            <input type="url" name="btn_url" class="form-control" value="<?= htmlspecialchars( $btn_url, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://pratfall.com.tr/dergiler" style="font-size:0.85rem;" />
          </div>
        </div>
      </div>

      <div style="border-top:1px solid var(--adm-border); padding-top:1.5rem;">
        <label class="form-label" style="font-weight:700; margin-bottom:0.75rem;">Gönderim Türü Seçin:</label>
        
        <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.5rem;">
          <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-size:0.9rem;">
            <input type="radio" name="send_mode" value="test" checked onchange="toggleSendMode('test')" />
            <span><strong>Önce Test E-Postası Gönder</strong> (Gelen kutunuzda şablonu inceleyin)</span>
          </label>
          <div id="testEmailBox" style="margin-left:1.8rem;">
            <input type="email" name="test_email" class="form-control" value="<?= htmlspecialchars( $test_email, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="test-adresiniz@gmail.com" style="max-width:320px; font-size:0.85rem;" />
          </div>

          <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-size:0.9rem; margin-top:0.5rem;">
            <input type="radio" name="send_mode" value="all" onchange="toggleSendMode('all')" />
            <span><strong>Tüm Aktif Abonelere Gönder</strong> (<span style="color:var(--adm-green); font-weight:700;"><?= $active_count; ?></span> aktif edebiyat sever)</span>
          </label>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
          <button type="submit" id="submitBtn" class="btn btn-primary" style="padding:0.65rem 1.4rem;">
            <span>📨</span> <span id="btnLabel">Test E-Postasını Gönder</span>
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- 2. SAĞ: CANLI BİLGİ & ŞABLON KURALLARI -->
  <div>
    
    <div class="card" style="margin-bottom:1.5rem;">
      <h3 style="margin:0 0 1rem 0; font-size:1.05rem; font-weight:700;">📬 Gönderim Durumu</h3>
      
      <div style="display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0; border-bottom:1px solid var(--adm-border); font-size:0.85rem;">
        <span style="color:var(--adm-muted);">Aktif Alıcılar:</span>
        <strong style="color:var(--adm-green);"><?= $active_count; ?> Kişi</strong>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0; border-bottom:1px solid var(--adm-border); font-size:0.85rem;">
        <span style="color:var(--adm-muted);">Kasa Güvenliği:</span>
        <span>🔒 AES-256 Şifreli</span>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0; font-size:0.85rem;">
        <span style="color:var(--adm-muted);">Gönderim Motoru:</span>
        <span><?= $has_smtp ? '✅ cPanel SMTP' : '⚠️ PHP Mail()'; ?></span>
      </div>
    </div>

    <div class="card" style="background:#faf7f0; border:1px solid #e0dacb;">
      <h4 style="margin:0 0 0.75rem 0; font-size:0.95rem; font-family:Georgia, serif;">✦ Pratfall Edebi Tasarım Şablonu</h4>
      <p style="font-size:0.8rem; line-height:1.6; color:#555; margin:0 0 0.75rem 0;">
        Gönderdiğiniz tüm e-postalar otomatik olarak Pratfall'ın brutalist kağıt estetiğine bürünür:
      </p>
      <ul style="font-size:0.78rem; line-height:1.6; color:#555; padding-left:1.2rem; margin:0;">
        <li>Zarif Georgia tipografisi ve sol kırmızı kaşe çizgisi.</li>
        <li>Kişiye özel tek tıkla abonelikten ayrılma bağlantısı (KVKK / GDPR zorunluluğu).</li>
        <li>Tüm e-posta istemcileriyle (Gmail, Apple Mail, Outlook) %100 mobil uyumlu.</li>
      </ul>
    </div>

  </div>

</div>

<script>
function toggleSendMode(mode) {
  var box = document.getElementById('testEmailBox');
  var btnLabel = document.getElementById('btnLabel');
  if (mode === 'test') {
    box.style.display = 'block';
    btnLabel.innerText = 'Test E-Postasını Gönder';
  } else {
    box.style.display = 'none';
    btnLabel.innerText = 'Tüm Abonelere (<?= $active_count; ?> Kişi) Dağıt ↗';
  }
}

document.getElementById('bultenSendForm')?.addEventListener('submit', function(e) {
  var mode = document.querySelector('input[name="send_mode"]:checked')?.value;
  if (mode === 'all') {
    if (!confirm('DİKKAT: Bu bülten kayıtlı olan TÜM <?= $active_count; ?> aktif aboneye gönderilecektir. Onaylıyor musunuz?')) {
      e.preventDefault();
      return false;
    }
  }
  var btn = document.getElementById('submitBtn');
  btn.style.opacity = '0.7';
  btn.disabled = true;
  document.getElementById('btnLabel').innerText = 'Gönderiliyor, lütfen bekleyin...';
});
</script>

<?php
require PATH_ADMIN . '/layout/footer.php';
