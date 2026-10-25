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
$page_title = 'Bülten Dağıtım Masası';
$active_tab = 'subscribers';

$msg = '';
$err = '';

// Aktif Abone Sayısı
$active_count = (int) $db->query( "SELECT COUNT(*) FROM subscribers WHERE status = 'active'" )->fetchColumn();

// SMTP Ayar Kontrolü
$smtp_host = Database::get_setting( 'smtp_host', '' );
$smtp_user = Database::get_setting( 'smtp_user', '' );
$smtp_port = Database::get_setting( 'smtp_port', '465' );
$smtp_sec  = Database::get_setting( 'smtp_secure', 'ssl' );
$has_smtp  = ( ! empty( $smtp_host ) && ! empty( $smtp_user ) );

// Form Değerleri
$subject     = trim( (string) ( $_POST['subject'] ?? '' ) );
$title       = trim( (string) ( $_POST['title'] ?? '' ) );
$content     = trim( (string) ( $_POST['content'] ?? '' ) );
$btn_text    = trim( (string) ( $_POST['btn_text'] ?? '' ) );
$btn_url     = trim( (string) ( $_POST['btn_url'] ?? '' ) );
$test_email  = trim( (string) ( $_POST['test_email'] ?? ( Auth::user()['email'] ?? 'mail@pratfall.com.tr' ) ) );
$send_mode   = $_POST['send_mode'] ?? 'test';

// Şablon Başlık ve İmzaları
$top_badge   = trim( (string) ( $_POST['top_badge'] ?? Database::get_setting( 'newsletter_top_badge', '✦ MATBAA & EDEBİYAT BÜLTENİ' ) ) );
$brand_name  = trim( (string) ( $_POST['brand_name'] ?? Database::get_setting( 'newsletter_brand_name', 'PRATFALL' ) ) );
$slogan      = trim( (string) ( $_POST['slogan'] ?? Database::get_setting( 'newsletter_slogan', 'Kusurlu Kalma Çabası · Bağımsız Edebiyat Platformu' ) ) );
$footer_note = trim( (string) ( $_POST['footer_note'] ?? Database::get_setting( 'newsletter_footer_note', 'Bu e-posta, Pratfall Edebiyat Platformu bültenine kayıt olduğunuz için iletilmiştir.' ) ) );

// GÖNDERİM İŞLEMİ
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif ( empty( $subject ) || empty( $title ) || empty( $content ) ) {
        $err = 'Lütfen Konu, E-Posta Başlığı ve İçerik alanlarını eksiksiz doldurun.';
    } else {
        // Yeni şablon başlıklarını veritabanına kaydet (Kalıcı olsun)
        Database::set_setting( 'newsletter_top_badge', $top_badge );
        Database::set_setting( 'newsletter_brand_name', $brand_name );
        Database::set_setting( 'newsletter_slogan', $slogan );
        Database::set_setting( 'newsletter_footer_note', $footer_note );

        $custom_tpl = [
            'top_badge'   => $top_badge,
            'brand_name'  => $brand_name,
            'slogan'      => $slogan,
            'footer_note' => $footer_note,
        ];

        // İçeriği satır sonlarına göre paragraflara çevir
        $formatted_content = nl2br( $content );

        if ( $send_mode === 'test' ) {
            // SADECE TEST E-POSTASI GÖNDER
            if ( empty( $test_email ) || ! filter_var( $test_email, FILTER_VALIDATE_EMAIL ) ) {
                $err = 'Lütfen geçerli bir test e-posta adresi yazın.';
            } else {
                $unsub_url = SITE_URL . '/bulten/ayril?token=DEMO_TEST_TOKEN';
                $html = Mailer::createTemplate( $title, $formatted_content, $unsub_url, $btn_text, $btn_url, $custom_tpl );
                $res = Mailer::send( $test_email, '[TEST] ' . $subject, $html );

                if ( $res['success'] ) {
                    $msg = "✦ Test bülteni <strong>{$test_email}</strong> adresine başarıyla iletildi! Gelen kutunuzu (ve varsa spam/tanıtım kutusunu) kontrol edebilirsiniz.";
                } else {
                    $err = 'Test gönderimi başarısız oldu: ' . htmlspecialchars( $res['message'], ENT_QUOTES, 'UTF-8' );
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
                    $html = Mailer::createTemplate( $title, $formatted_content, $unsub_url, $btn_text, $btn_url, $custom_tpl );
                    
                    $res = Mailer::send( $decrypted_email, $subject, $html );
                    if ( $res['success'] ) {
                        $sent_count++;
                    } else {
                        $fail_count++;
                    }

                    // Sunucu kaynaklarını korumak için mikro gecikme (Spam filtresi koruması)
                    usleep( 50000 ); // 0.05 saniye
                }

                $msg = "✦ Bülten dağıtımı tamamlandı! <strong>{$sent_count}</strong> edebiyat severe başarıyla iletildi." . ( $fail_count > 0 ? " ({$fail_count} adreste iletim hatası yaşandı)." : "" );
            }
        }
    }
}

require PATH_ADMIN . '/layout/header.php';
?>

<!-- ÜST BAŞLIK VE AKSİYONLAR -->
<div class="admin-header-row">
  <div>
    <h1 class="admin-title">📬 Bülten Dağıtım Masası</h1>
    <p style="margin:0.25rem 0 0 0; color:var(--adm-muted); font-size:0.9rem;">
      Kayıtlı edebiyat severlere şık, neo-brutalist matbaa şablonuyla e-posta mektubu gönderin.
    </p>
  </div>
  <div style="display:flex; gap:0.75rem; align-items:center;">
    <a href="<?= SITE_URL; ?>/admin/settings.php#smtp" class="btn btn-secondary" style="font-size:0.85rem;">
      ⚙️ SMTP Ayarları
    </a>
    <a href="<?= SITE_URL; ?>/admin/subscribers.php" class="btn btn-secondary" style="font-size:0.85rem;">
      ← Abone Listesi
    </a>
  </div>
</div>

<!-- DURUM ROZETLERİ -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
  <div class="admin-card" style="margin:0; padding:1rem 1.25rem; display:flex; align-items:center; gap:0.85rem;">
    <div style="width:38px; height:38px; border-radius:8px; background:var(--adm-green-soft); color:var(--adm-green); display:flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700;">
      👥
    </div>
    <div>
      <div style="font-size:0.75rem; color:var(--adm-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Aktif Aboneler</div>
      <div style="font-size:1.25rem; font-weight:700; color:var(--adm-text);"><?= number_format( $active_count, 0, ',', '.' ); ?> Okur</div>
    </div>
  </div>

  <div class="admin-card" style="margin:0; padding:1rem 1.25rem; display:flex; align-items:center; gap:0.85rem;">
    <div style="width:38px; height:38px; border-radius:8px; background:var(--adm-accent-soft); color:var(--adm-accent); display:flex; align-items:center; justify-content:center; font-size:1.1rem;">
      🔒
    </div>
    <div>
      <div style="font-size:0.75rem; color:var(--adm-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Kasa Güvenliği</div>
      <div style="font-size:0.95rem; font-weight:700; color:var(--adm-text);">AES-256 Şifreli</div>
    </div>
  </div>

  <div class="admin-card" style="margin:0; padding:1rem 1.25rem; display:flex; align-items:center; gap:0.85rem;">
    <div style="width:38px; height:38px; border-radius:8px; background:<?= $has_smtp ? 'var(--adm-green-soft)' : 'var(--adm-yellow-soft)'; ?>; color:<?= $has_smtp ? 'var(--adm-green)' : 'var(--adm-yellow)'; ?>; display:flex; align-items:center; justify-content:center; font-size:1.1rem;">
      <?= $has_smtp ? '⚡' : '⚠️'; ?>
    </div>
    <div>
      <div style="font-size:0.75rem; color:var(--adm-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Gönderim Motoru</div>
      <div style="font-size:0.95rem; font-weight:700; color:var(--adm-text);">
        <?= $has_smtp ? htmlspecialchars( $smtp_user ) . ' (' . strtoupper($smtp_sec) . ')' : 'Yerel mail()'; ?>
      </div>
    </div>
  </div>
</div>

<?php if ( ! $has_smtp ) : ?>
  <div class="alert" style="background:#fffbeb; border:1px solid #fef3c7; color:#92400e; margin-bottom:1.5rem;">
    <span style="font-size:1.2rem;">⚠️</span>
    <div>
      <strong>Kurumsal SMTP Yapılandırılmamış:</strong> E-postalarınızın spam kutusuna düşmeden %100 gelen kutusuna ulaşması için 
      <a href="<?= SITE_URL; ?>/admin/settings.php" style="color:#92400e; font-weight:700; text-decoration:underline;">Site Ayarları</a> sayfasından kurumsal posta bilgilerinizi kaydedin.
    </div>
  </div>
<?php endif; ?>

<?php if ( ! empty( $msg ) ) : ?>
  <div class="alert alert-success">
    <span>✦</span>
    <div><?= $msg; ?></div>
  </div>
<?php endif; ?>

<?php if ( ! empty( $err ) ) : ?>
  <div class="alert alert-error">
    <span>⚠️</span>
    <div><?= $err; ?></div>
  </div>
<?php endif; ?>

<!-- ANA DÜZEN: FORM VE CANLI ÖNİZLEME -->
<div style="display:grid; grid-template-columns: minmax(400px, 1.2fr) minmax(360px, 1fr); gap:1.75rem; align-items:start;">
  
  <!-- 1. SOL SÜTUN: BÜLTEN YAZIM ALANI -->
  <div class="admin-card" style="margin:0;">
    <form method="POST" action="<?= SITE_URL; ?>/admin/newsletter-send.php" id="bultenSendForm">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />

      <!-- E-Posta Konusu -->
      <div class="form-group" style="margin-bottom:1.25rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
          <label class="form-label" style="font-weight:700; margin:0;">
            1. E-Posta Konusu (Gelen Kutusunda Görünen Başlık) *
          </label>
          <span id="subjectCharCount" style="font-size:0.75rem; color:var(--adm-muted); font-family:var(--adm-font-mono);">
            0 / 80
          </span>
        </div>
        <input type="text" name="subject" id="inputSubject" class="form-control" value="<?= htmlspecialchars( $subject, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Pratfall Sayı #19 Matbaadan Çıktı · Kusursuzluğun İflası" required style="font-size:1rem; font-weight:600; padding:0.65rem 0.85rem;" />
        <small style="font-size:0.75rem; color:var(--adm-muted); display:block; margin-top:0.35rem;">
          💡 İpucu: İlk 40-50 karakter mobil bildirimlerde doğrudan görünür. Merak uyandırıcı ve edebi tutun.
        </small>
      </div>

      <!-- E-Posta İçi Manşet Başlık -->
      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" style="font-weight:700; margin-bottom:0.4rem;">
          2. E-Posta İçi Manşet Başlığı *
        </label>
        <input type="text" name="title" id="inputTitle" class="form-control" value="<?= htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Matbaanın Mürekkebi ve Yeni Sayımız Üzerine Birkaç Söz" required style="font-size:0.95rem;" />
      </div>

      <!-- Bülten Mektubu Metni -->
      <div class="form-group" style="margin-bottom:1.25rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
          <label class="form-label" style="font-weight:700; margin:0;">
            3. Bülten Mektubu / Gövde Metni *
          </label>
          <!-- Hızlı Edebi Tipografi Araçları -->
          <div style="display:flex; gap:0.35rem;">
            <button type="button" class="stamp-tag" onclick="insertSnippet('— ')" title="Uzun Tire Ekle" style="cursor:pointer; background:#fff;">— Uzun Tire</button>
            <button type="button" class="stamp-tag" onclick="insertSnippet('“', '”')" title="Edebi Tırnak" style="cursor:pointer; background:#fff;">“Tırnak”</button>
            <button type="button" class="stamp-tag" onclick="insertSnippet('✦ ')" title="Yıldız İşareti" style="cursor:pointer; background:#fff;">✦ Kaşe</button>
          </div>
        </div>
        <textarea name="content" id="inputContent" rows="12" class="form-control" placeholder="Sevgili Pratfall Okuru,&#10;&#10;Bu sayımızda sokakların tereddüdünü ve unutulmuş dizeleri bir araya getirdik..." required style="font-family:var(--adm-font-serif); font-size:1.05rem; line-height:1.75; padding:0.85rem;"><?= htmlspecialchars( $content, ENT_QUOTES, 'UTF-8' ); ?></textarea>
      </div>

      <!-- Edebi Eylem Butonu (CTA) -->
      <div style="background:var(--adm-surface-alt); padding:1.25rem; border-radius:var(--adm-radius-sm); margin-bottom:1.5rem; border:1px solid var(--adm-border);">
        <div style="font-size:0.85rem; font-weight:700; margin-bottom:0.75rem; display:flex; align-items:center; gap:0.5rem;">
          <span>🎯</span> İsteğe Bağlı Edebi Eylem Butonu (CTA)
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div>
            <label class="form-label" style="font-size:0.78rem; font-weight:600;">Buton Üzerindeki Yazı:</label>
            <input type="text" name="btn_text" id="inputBtnText" class="form-control" value="<?= htmlspecialchars( $btn_text, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Yeni Sayıyı İncele ↗" style="font-size:0.85rem;" />
          </div>
          <div>
            <label class="form-label" style="font-size:0.78rem; font-weight:600;">Hedef Web Bağlantısı (URL):</label>
            <input type="url" name="btn_url" id="inputBtnUrl" class="form-control" value="<?= htmlspecialchars( $btn_url, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://pratfall.com.tr/dergiler" style="font-size:0.85rem;" />
          </div>
        </div>
      </div>

      <!-- ŞABLON METİNLERİNİ ÖZELLEŞTİRME KARTI -->
      <div style="background:var(--adm-surface-alt); padding:1.25rem; border-radius:var(--adm-radius-sm); margin-bottom:1.5rem; border:1px solid var(--adm-border);">
        <details open>
          <summary style="font-size:0.88rem; font-weight:700; cursor:pointer; user-select:none; display:flex; align-items:center; gap:0.5rem; color:var(--adm-text);">
            <span>🎨 Şablon Başlıklarını & İmzalarını Özelleştir</span>
            <span class="stamp-tag" style="font-size:0.68rem; margin-left:auto; background:#fff;">Canlı Önizlemeli</span>
          </summary>
          <p style="font-size:0.75rem; color:var(--adm-muted); margin:0.5rem 0 1rem 0;">
            E-postanın üstündeki kırmızı rozet, dergi adı, sloganı ve altbilgi notunu buradan dilediğiniz gibi değiştirebilirsiniz.
          </p>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div>
              <label class="form-label" style="font-size:0.78rem; font-weight:600;">En Üst Kırmızı Rozet Metni:</label>
              <input type="text" name="top_badge" id="inputTopBadge" class="form-control" value="<?= htmlspecialchars( $top_badge, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="✦ MATBAA & EDEBİYAT BÜLTENİ" style="font-size:0.85rem;" />
            </div>
            <div>
              <label class="form-label" style="font-size:0.78rem; font-weight:600;">Dergi / Marka Adı:</label>
              <input type="text" name="brand_name" id="inputBrandName" class="form-control" value="<?= htmlspecialchars( $brand_name, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="PRATFALL" style="font-size:0.85rem; font-weight:700;" />
            </div>
          </div>

          <div style="margin-bottom:1rem;">
            <label class="form-label" style="font-size:0.78rem; font-weight:600;">Başlık Altındaki Slogan / Açıklama:</label>
            <input type="text" name="slogan" id="inputSlogan" class="form-control" value="<?= htmlspecialchars( $slogan, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Kusurlu Kalma Çabası · Bağımsız Edebiyat Platformu" style="font-size:0.85rem;" />
          </div>

          <div>
            <label class="form-label" style="font-size:0.78rem; font-weight:600;">En Alttaki Bilgilendirme Notu:</label>
            <input type="text" name="footer_note" id="inputFooterNote" class="form-control" value="<?= htmlspecialchars( $footer_note, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Bu e-posta, Pratfall Edebiyat Platformu bültenine kayıt olduğunuz için iletilmiştir." style="font-size:0.85rem;" />
          </div>
        </details>
      </div>

      <!-- Gönderim Seçenekleri -->
      <div style="border-top:1px solid var(--adm-border); padding-top:1.5rem;">
        <label class="form-label" style="font-weight:700; margin-bottom:0.85rem;">
          4. Gönderim Türü Seçin:
        </label>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
          <!-- Kart 1: Test -->
          <label id="cardTestMode" style="border:2px solid var(--adm-primary); background:var(--adm-surface); padding:1rem; border-radius:var(--adm-radius-sm); cursor:pointer; display:flex; flex-direction:column; gap:0.5rem; transition:all 0.2s;">
            <div style="display:flex; align-items:center; gap:0.5rem;">
              <input type="radio" name="send_mode" value="test" checked onchange="toggleSendMode('test')" />
              <strong style="font-size:0.9rem;">🧪 Test E-Postası Gönder</strong>
            </div>
            <p style="font-size:0.75rem; color:var(--adm-muted); margin:0; line-height:1.4;">
              Tüm abonelere gitmeden önce bülteni kendi gelen kutunuzda birebir test edin.
            </p>
          </label>

          <!-- Kart 2: Toplu Dağıtım -->
          <label id="cardAllMode" style="border:1px solid var(--adm-border); background:var(--adm-surface-alt); padding:1rem; border-radius:var(--adm-radius-sm); cursor:pointer; display:flex; flex-direction:column; gap:0.5rem; transition:all 0.2s;">
            <div style="display:flex; align-items:center; gap:0.5rem;">
              <input type="radio" name="send_mode" value="all" onchange="toggleSendMode('all')" />
              <strong style="font-size:0.9rem;">🚀 Tüm Abonelere Dağıt</strong>
            </div>
            <p style="font-size:0.75rem; color:var(--adm-muted); margin:0; line-height:1.4;">
              Kasada kayıtlı olan <strong style="color:var(--adm-green);"><?= $active_count; ?> aktif</strong> aboneye eşzamanlı gönderilir.
            </p>
          </label>
        </div>

        <!-- Test E-Posta Giriş Kutusu -->
        <div id="testEmailBox" style="background:#f8fafc; border:1px dashed var(--adm-border); padding:1rem; border-radius:var(--adm-radius-sm); margin-bottom:1.5rem;">
          <label class="form-label" style="font-size:0.8rem; font-weight:700; margin-bottom:0.35rem;">
            Test E-Postasının İletileceği Adres:
          </label>
          <div style="display:flex; gap:0.5rem;">
            <input type="email" name="test_email" class="form-control" value="<?= htmlspecialchars( $test_email, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="ornek@gmail.com" required style="font-size:0.85rem;" />
          </div>
          <small style="font-size:0.72rem; color:var(--adm-muted); margin-top:0.35rem; display:block;">
            * Buraya kendi kişisel Gmail veya şirket mailinizi yazarak önizleme alabilirsiniz.
          </small>
        </div>

        <!-- Gönder Butonu -->
        <div style="display:flex; justify-content:flex-end;">
          <button type="submit" id="submitBtn" class="btn btn-primary" style="padding:0.75rem 1.8rem; font-size:0.95rem; display:inline-flex; align-items:center; gap:0.5rem;">
            <span>📨</span>
            <span id="btnLabel">Test E-Postasını Gönder</span>
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- 2. SAĞ SÜTUN: CANLI E-POSTA ÖNİZLEMESİ (LIVE EMAIL PREVIEW) -->
  <div>
    <div class="admin-card" style="margin:0; padding:1.25rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--adm-border); padding-bottom:0.75rem;">
        <div style="font-weight:700; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
          <span>👁️</span> Canlı E-Posta Önizlemesi
        </div>
        <span class="stamp-tag" style="font-size:0.68rem;">Gmail / Apple Mail</span>
      </div>

      <!-- Simüle E-Posta İstemcisi Penceresi -->
      <div style="background:#f4eee1; border:1px solid #d4ccb8; border-radius:6px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        
        <!-- İstemci Başlık Çubuğu -->
        <div style="background:#e5ded0; padding:0.5rem 0.85rem; border-bottom:1px solid #d4ccb8; font-size:0.75rem; font-family:var(--adm-font-mono); color:#555;">
          <div><strong>Kimden:</strong> PRATFALL &lt;mail@pratfall.com.tr&gt;</div>
          <div style="margin-top:2px;"><strong>Konu:</strong> <span id="prevSubject" style="color:#1a1a1a; font-weight:700;">(Konu Başlığı)</span></div>
        </div>

        <!-- Mektup Gövdesi -->
        <div style="padding:1.5rem 1rem;">
          <div style="max-width:440px; margin:0 auto; background:#fff; border:2px solid #1a1a1a; box-shadow:4px 4px 0px #1a1a1a; border-radius:2px;">
            
            <!-- Şablon Başlığı -->
            <div style="padding:16px 20px 12px 20px; border-bottom:2px solid #1a1a1a; background-color:#fbf7ee;">
              <div id="prevTopBadge" style="font-family:'Courier New', monospace; font-size:9px; font-weight:bold; letter-spacing:1.5px; color:#8b1e0f; margin-bottom:4px;">
                <?= htmlspecialchars( $top_badge, ENT_QUOTES, 'UTF-8' ); ?>
              </div>
              <div id="prevBrandName" style="font-family:Georgia, serif; font-size:18px; font-weight:bold; letter-spacing:2px; color:#1a1a1a;">
                <?= htmlspecialchars( $brand_name, ENT_QUOTES, 'UTF-8' ); ?>
              </div>
              <div id="prevSlogan" style="font-family:'Courier New', monospace; font-size:9px; color:#666; margin-top:2px;">
                <?= htmlspecialchars( $slogan, ENT_QUOTES, 'UTF-8' ); ?>
              </div>
            </div>

            <!-- İçerik Alanı -->
            <div style="padding:20px;">
              <h2 id="prevTitle" style="font-family:Georgia, serif; font-size:18px; color:#1a1a1a; margin:0 0 14px 0; line-height:1.35;">
                Yeni Sayımız Matbaadan Çıktı
              </h2>

              <div id="prevContent" style="font-family:Georgia, serif; font-size:13.5px; line-height:1.75; color:#222; min-height:80px; white-space:pre-line;">
                Sevgili Pratfall Okuru,
                
                Bu sayımızda sokakların tereddüdünü ve unutulmuş dizeleri bir araya getirdik...
              </div>

              <!-- Önizleme Butonu -->
              <div id="prevBtnWrap" style="text-align:center; margin-top:20px; display:none;">
                <span id="prevBtn" style="display:inline-block; background:#1a1a1a; color:#fbf7ee; padding:8px 18px; font-family:'Courier New', monospace; font-size:11px; font-weight:bold; border:2px solid #1a1a1a;">
                  Yeni Sayıyı İncele ↗
                </span>
              </div>
            </div>

            <!-- Şablon Altbilgisi -->
            <div style="padding:12px 20px; border-top:1px solid #eee; background-color:#faf7ee; font-size:10px; color:#777; text-align:center; font-family:'Courier New', monospace;">
              <div id="prevFooterNote" style="margin-bottom:6px; line-height:1.4;">
                <?= htmlspecialchars( $footer_note, ENT_QUOTES, 'UTF-8' ); ?>
              </div>
              <div>
                <a href="<?= SITE_URL; ?>" style="color:#1a1a1a; text-decoration:underline;">pratfall.com.tr</a> · 
                <span style="color:#8b1e0f; text-decoration:underline; font-weight:bold;">Abonelikten Ayrıl</span>
              </div>
            </div>

          </div>
        </div>

      </div>

      <!-- İpuçları Kutusu -->
      <div style="margin-top:1.25rem; background:var(--adm-surface-alt); border:1px solid var(--adm-border); border-radius:6px; padding:1rem; font-size:0.78rem; line-height:1.6; color:var(--adm-muted);">
        <strong style="color:var(--adm-text); display:block; margin-bottom:0.35rem;">✦ Spam Filtrelerine Takılmama Rehberi:</strong>
        • Konu başlığında tamamen BÜYÜK HARFLER veya "ÜCRETSİZ, KAZAN" gibi kelimeler kullanmayın.<br>
        • Metin ve bağlantı dengesini koruyun; edebi ve doğal mektup dili en yüksek açılma oranına sahiptir.<br>
        • E-postalarınız kurumsal cPanel SPF/DKIM ve TLS şifrelemesiyle güvenli şekilde imzalanır.
      </div>
    </div>
  </div>

</div>

<!-- CANLI ÖNİZLEME VE MOD DİNAMİĞİ JAVASCRIPT -->
<script>
function toggleSendMode(mode) {
  var box = document.getElementById('testEmailBox');
  var btnLabel = document.getElementById('btnLabel');
  var cardTest = document.getElementById('cardTestMode');
  var cardAll = document.getElementById('cardAllMode');

  if (mode === 'test') {
    box.style.display = 'block';
    btnLabel.innerText = 'Test E-Postasını Gönder';
    cardTest.style.borderColor = 'var(--adm-primary)';
    cardTest.style.background = 'var(--adm-surface)';
    cardAll.style.borderColor = 'var(--adm-border)';
    cardAll.style.background = 'var(--adm-surface-alt)';
  } else {
    box.style.display = 'none';
    btnLabel.innerText = 'Tüm Abonelere (<?= $active_count; ?> Kişi) Dağıt 🚀';
    cardAll.style.borderColor = 'var(--adm-primary)';
    cardAll.style.background = 'var(--adm-surface)';
    cardTest.style.borderColor = 'var(--adm-border)';
    cardTest.style.background = 'var(--adm-surface-alt)';
  }
}

// Canlı Önizleme Senkronizasyonu
var inSubject    = document.getElementById('inputSubject');
var inTitle      = document.getElementById('inputTitle');
var inContent    = document.getElementById('inputContent');
var inBtnText    = document.getElementById('inputBtnText');
var inBtnUrl     = document.getElementById('inputBtnUrl');
var inTopBadge   = document.getElementById('inputTopBadge');
var inBrandName  = document.getElementById('inputBrandName');
var inSlogan     = document.getElementById('inputSlogan');
var inFooterNote = document.getElementById('inputFooterNote');

var pSubject     = document.getElementById('prevSubject');
var pTitle       = document.getElementById('prevTitle');
var pContent     = document.getElementById('prevContent');
var pBtnWrap     = document.getElementById('prevBtnWrap');
var pBtn         = document.getElementById('prevBtn');
var pTopBadge    = document.getElementById('prevTopBadge');
var pBrandName   = document.getElementById('prevBrandName');
var pSlogan      = document.getElementById('prevSlogan');
var pFooterNote  = document.getElementById('prevFooterNote');
var charCount    = document.getElementById('subjectCharCount');

function syncPreview() {
  if (inSubject) {
    pSubject.innerText = inSubject.value.trim() || '(Konu Başlığı)';
    charCount.innerText = inSubject.value.length + ' / 80';
    if (inSubject.value.length > 70) {
      charCount.style.color = '#ef4444';
    } else {
      charCount.style.color = 'var(--adm-muted)';
    }
  }
  if (inTitle) {
    pTitle.innerText = inTitle.value.trim() || 'Yeni Sayımız Matbaadan Çıktı';
  }
  if (inContent) {
    pContent.innerText = inContent.value.trim() || 'Sevgili Pratfall Okuru,\n\nBu sayımızda sokakların tereddüdünü ve unutulmuş dizeleri bir araya getirdik...';
  }
  if (inBtnText && pBtn && pBtnWrap) {
    var txt = inBtnText.value.trim();
    if (txt) {
      pBtn.innerText = txt;
      pBtnWrap.style.display = 'block';
    } else {
      pBtnWrap.style.display = 'none';
    }
  }
  if (inTopBadge && pTopBadge) {
    pTopBadge.innerText = inTopBadge.value.trim() || '✦ MATBAA & EDEBİYAT BÜLTENİ';
  }
  if (inBrandName && pBrandName) {
    pBrandName.innerText = inBrandName.value.trim() || 'PRATFALL';
  }
  if (inSlogan && pSlogan) {
    pSlogan.innerText = inSlogan.value.trim() || 'Kusurlu Kalma Çabası · Bağımsız Edebiyat Platformu';
  }
  if (inFooterNote && pFooterNote) {
    pFooterNote.innerText = inFooterNote.value.trim() || 'Bu e-posta, Pratfall Edebiyat Platformu bültenine kayıt olduğunuz için iletilmiştir.';
  }
}

inSubject?.addEventListener('input', syncPreview);
inTitle?.addEventListener('input', syncPreview);
inContent?.addEventListener('input', syncPreview);
inBtnText?.addEventListener('input', syncPreview);
inBtnUrl?.addEventListener('input', syncPreview);
inTopBadge?.addEventListener('input', syncPreview);
inBrandName?.addEventListener('input', syncPreview);
inSlogan?.addEventListener('input', syncPreview);
inFooterNote?.addEventListener('input', syncPreview);

// Hızlı Tipografi Ekleme
function insertSnippet(before, after) {
  var area = inContent;
  if (!area) return;
  var start = area.selectionStart;
  var end = area.selectionEnd;
  var text = area.value;
  var sel = text.substring(start, end);

  after = after || '';
  var replace = before + sel + after;
  area.value = text.substring(0, start) + replace + text.substring(end);
  area.focus();
  area.selectionStart = start + before.length;
  area.selectionEnd = start + before.length + sel.length;
  syncPreview();
}

// Form Gönderim Doğrulaması
document.getElementById('bultenSendForm')?.addEventListener('submit', function(e) {
  var mode = document.querySelector('input[name="send_mode"]:checked')?.value;
  if (mode === 'all') {
    var count = <?= (int) $active_count; ?>;
    if (!confirm('DİKKAT: Bu edebi bülten sistemde kayıtlı olan TÜM ' + count + ' aktif aboneye eşzamanlı olarak gönderilecektir. Gönderimi başlatmak istiyor musunuz?')) {
      e.preventDefault();
      return false;
    }
  }
  var btn = document.getElementById('submitBtn');
  btn.style.opacity = '0.7';
  btn.disabled = true;
  document.getElementById('btnLabel').innerText = 'Gönderiliyor, lütfen bekleyin...';
});

// İlk Yükleme Senkronizasyonu
syncPreview();
</script>

<?php
require PATH_ADMIN . '/layout/footer.php';
