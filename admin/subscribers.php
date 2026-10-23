<?php
/**
 * Pratfall Standalone - E-Posta Bülteni & Aboneler Yönetim Masası
 * 
 * Veritabanında AES-256-CBC ile şifreli tutulan e-postaları güvenle yönetir.
 * Toplu içe aktarma, CSV dışa aktarma, tek tıkla üyelikten çıkarma ve arama destekler.
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$db = Database::get();
$page_title = 'E-Posta Bülteni & Aboneler';
$active_tab = 'subscribers';

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// ==========================================
// 1. CSV DIŞA AKTARMA (Tüm Aboneleri İndir)
// ==========================================
if ( $action === 'export_csv' ) {
    // Çıktı arabelleğini temizle
    if ( ob_get_level() ) {
        ob_end_clean();
    }

    $filename = 'pratfall-bulten-aboneleri-' . date( 'Y-m-d_H-i' ) . '.csv';
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $output = fopen( 'php://output', 'w' );
    // Excel'in Türkçe karakterleri düzgün açması için UTF-8 BOM ekle
    fputs( $output, "\xEF\xBB\xBF" );

    // Başlık Satırı
    fputcsv( $output, [ 'ID', 'E-Posta Adresi', 'Durum', 'Kayıt Kaynağı', 'Ayrılma Kodu (Token)', 'Kayıt Tarihi', 'Ayrılma Tarihi' ], ';' );

    $stmt = $db->query( "SELECT * FROM subscribers ORDER BY id DESC" );
    while ( $row = $stmt->fetch() ) {
        $decrypted_email = Crypto::decrypt( $row['email_encrypted'] ) ?? '[Çözülemedi]';
        $status_label = ( $row['status'] === 'active' ) ? 'Aktif' : 'Abonelikten Ayrıldı';
        $source_label = ( $row['source'] === 'bulk_import' ) ? 'Toplu İçe Aktarım' : 'Web Sitesi Altbilgi';

        fputcsv( $output, [
            $row['id'],
            $decrypted_email,
            $status_label,
            $source_label,
            $row['unsubscribe_token'],
            $row['created_at'],
            $row['unsubscribed_at'] ?? ''
        ], ';' );
    }

    fclose( $output );
    exit;
}

// ==========================================
// 2. TEKİL SİLME VE DURUM DEĞİŞTİRME
// ==========================================
if ( $action === 'delete' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "DELETE FROM subscribers WHERE id = ?" );
    $stmt->execute( [ $id ] );
    header( 'Location: ' . SITE_URL . '/admin/subscribers.php?msg=deleted' );
    exit;
}

if ( $action === 'toggle' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "SELECT status FROM subscribers WHERE id = ?" );
    $stmt->execute( [ $id ] );
    $row = $stmt->fetch();
    if ( $row ) {
        $new_status = ( $row['status'] === 'active' ) ? 'unsubscribed' : 'active';
        $unsub_at = ( $new_status === 'unsubscribed' ) ? date( 'Y-m-d H:i:s' ) : null;
        $upd = $db->prepare( "UPDATE subscribers SET status = ?, unsubscribed_at = ? WHERE id = ?" );
        $upd->execute( [ $new_status, $unsub_at, $id ] );
    }
    header( 'Location: ' . SITE_URL . '/admin/subscribers.php?msg=status_updated' );
    exit;
}

// ==========================================
// 3. POST İŞLEMLERİ (Toplu veya Tekil Ekleme)
// ==========================================
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $post_action = $_POST['post_action'] ?? '';

        // TEKİL EKLEME
        if ( $post_action === 'add_single' ) {
            $email = trim( (string) ( $_POST['email'] ?? '' ) );
            if ( empty( $email ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                $err = 'Lütfen geçerli bir e-posta adresi girin.';
            } else {
                $email = strtolower( $email );
                $hash = Crypto::hash( $email );
                
                $check = $db->prepare( "SELECT id FROM subscribers WHERE email_hash = ?" );
                $check->execute( [ $hash ] );
                if ( $check->fetch() ) {
                    $err = 'Bu e-posta adresi zaten bülten listesinde kayıtlı.';
                } else {
                    $encrypted = Crypto::encrypt( $email );
                    $token = Crypto::generateToken( 24 );
                    $ins = $db->prepare( "
                        INSERT INTO subscribers (email_encrypted, email_hash, status, source, unsubscribe_token, ip_address)
                        VALUES (?, ?, 'active', 'admin_manual', ?, '127.0.0.1')
                    " );
                    $ins->execute( [ $encrypted, $hash, $token ] );
                    $msg = 'E-posta başarıyla AES-256 ile şifrelenerek listeye eklendi.';
                }
            }
        }

        // TOPLU İÇE AKTARMA (Bulk Import)
        if ( $post_action === 'bulk_import' ) {
            $raw_input = trim( (string) ( $_POST['emails_raw'] ?? '' ) );
            if ( empty( $raw_input ) ) {
                $err = 'Lütfen içe aktarılacak e-posta adreslerini yapıştırın.';
            } else {
                // Satırlara, virgüllere, noktalı virgüllere ve boşluklara göre ayır
                $tokens = preg_split( '/[\r\n,;\t]+/', $raw_input );
                $added_count = 0;
                $skipped_duplicates = 0;
                $invalid_count = 0;
                $seen_in_batch = [];

                $check_stmt = $db->prepare( "SELECT id FROM subscribers WHERE email_hash = ?" );
                $insert_stmt = $db->prepare( "
                    INSERT INTO subscribers (email_encrypted, email_hash, status, source, unsubscribe_token, ip_address)
                    VALUES (?, ?, 'active', 'bulk_import', ?, '127.0.0.1')
                " );

                $db->beginTransaction();
                try {
                    foreach ( $tokens as $t ) {
                        $email = trim( $t );
                        if ( empty( $email ) ) {
                            continue;
                        }
                        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                            $invalid_count++;
                            continue;
                        }
                        $email = strtolower( $email );

                        // Bu partide daha önce işlendiyse atla
                        if ( isset( $seen_in_batch[ $email ] ) ) {
                            $skipped_duplicates++;
                            continue;
                        }
                        $seen_in_batch[ $email ] = true;

                        // Veritabanında kör indeksle kontrol et
                        $hash = Crypto::hash( $email );
                        $check_stmt->execute( [ $hash ] );
                        if ( $check_stmt->fetch() ) {
                            $skipped_duplicates++;
                            continue;
                        }

                        // Güvenli şifrele ve kaydet
                        $encrypted = Crypto::encrypt( $email );
                        $token = Crypto::generateToken( 24 );
                        $insert_stmt->execute( [ $encrypted, $hash, $token ] );
                        $added_count++;
                    }
                    $db->commit();
                    $msg = "Toplu aktarım tamamlandı: <strong>{$added_count}</strong> yeni abone güvenle eklendi. ({$skipped_duplicates} mükerrer atlandı, {$invalid_count} geçersiz satır temizlendi).";
                } catch ( Throwable $e ) {
                    $db->rollBack();
                    $err = 'İçe aktarma sırasında hata oluştu: ' . htmlspecialchars( $e->getMessage() );
                }
            }
        }
    }
}

// İstatistikler
$total_active = (int) $db->query( "SELECT COUNT(*) FROM subscribers WHERE status = 'active'" )->fetchColumn();
$total_unsub  = (int) $db->query( "SELECT COUNT(*) FROM subscribers WHERE status = 'unsubscribed'" )->fetchColumn();
$total_all    = (int) $db->query( "SELECT COUNT(*) FROM subscribers" )->fetchColumn();

// Arama Filtresi
$search_query = trim( (string) ( $_GET['q'] ?? '' ) );
$subscribers = [];

$all_rows = $db->query( "SELECT * FROM subscribers ORDER BY id DESC" )->fetchAll();

foreach ( $all_rows as $row ) {
    $decrypted = Crypto::decrypt( $row['email_encrypted'] ) ?? '[Şifreli / Çözülemedi]';
    
    // Arama filtresi varsa eşleştir
    if ( ! empty( $search_query ) ) {
        $q = strtolower( $search_query );
        if ( strpos( strtolower( $decrypted ), $q ) === false && strpos( $row['email_hash'], $q ) === false ) {
            continue;
        }
    }

    $row['decrypted_email'] = $decrypted;
    $subscribers[] = $row;
}

require PATH_ADMIN . '/layout/header.php';
?>

<!-- ÜST BİLGİ VE AKSİYON BAŞLIĞI -->
<div class="content-header">
  <div>
    <h1 class="page-title">📬 E-Posta Bülteni & Aboneler</h1>
    <p class="page-subtitle">Veritabanında askeri düzeyde AES-256-CBC ile şifreli saklanan güvenli abone kasası.</p>
  </div>
  <div class="header-actions">
    <a href="<?= SITE_URL; ?>/admin/newsletter-send.php" class="btn btn-primary" style="background:#8b1e0f; border-color:#8b1e0f;">
      <span>✍️</span> Yeni Bülten Gönder
    </a>
    <a href="<?= SITE_URL; ?>/admin/subscribers.php?action=export_csv" class="btn btn-secondary">
      <span>💾</span> CSV Olarak İndir (Yedekle)
    </a>
    <button type="button" class="btn btn-primary" onclick="toggleBulkModal()">
      <span>📥</span> Toplu E-Posta İçe Aktar
    </button>
  </div>
</div>

<?php if ( ! empty( $msg ) ) : ?>
  <div class="alert alert-success" style="margin-bottom:1.5rem; background:rgba(30,130,76,0.1); border-left:4px solid var(--adm-green); color:var(--adm-green); padding:1rem; border-radius:6px;">
    <?= $msg === 'deleted' ? 'Abone kaydı kalıcı olarak silindi.' : ( $msg === 'status_updated' ? 'Abone durumu güncellendi.' : $msg ); ?>
  </div>
<?php endif; ?>

<?php if ( ! empty( $err ) ) : ?>
  <div class="alert alert-danger" style="margin-bottom:1.5rem; background:rgba(214,48,49,0.1); border-left:4px solid var(--adm-red); color:var(--adm-red); padding:1rem; border-radius:6px;">
    <?= $err; ?>
  </div>
<?php endif; ?>

<!-- İSTATİSTİK KARTLARI -->
<div class="stats-row" style="margin-bottom:2rem;">
  
  <div class="stat-card">
    <div class="stat-card-label">Aktif Aboneler</div>
    <div class="stat-card-value" style="color:var(--adm-green);"><?= $total_active; ?></div>
    <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.4rem;">
      Bülten gönderimlerine açık
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-label">Abonelikten Ayrılanlar</div>
    <div class="stat-card-value" style="color:var(--adm-muted);"><?= $total_unsub; ?></div>
    <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.4rem;">
      Tek tıkla ayrılanlar (KVKK / GDPR)
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-label">Toplam Kayıt</div>
    <div class="stat-card-value"><?= $total_all; ?></div>
    <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.4rem;">
      Veritabanındaki toplam satır
    </div>
  </div>

  <div class="stat-card" style="border-left:4px solid #10b981;">
    <div class="stat-card-label">Kasa Güvenlik Seviyesi</div>
    <div class="stat-card-value" style="font-size:1.15rem; color:var(--adm-text); display:flex; align-items:center; gap:0.4rem; font-weight:700;">
      <span>🔒 AES-256-CBC</span>
    </div>
    <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.4rem; line-height:1.4;">
      HMAC-SHA256 Kör İndeks ile tam koruma
    </div>
  </div>

</div>

<!-- GÜVENLİK BİLGİLENDİRME BANT (Sakin & Güven Veren) -->
<div class="card" style="margin-bottom:1.75rem; padding:1.25rem 1.5rem; background:rgba(59,130,246,0.04); border:1px solid rgba(59,130,246,0.2); border-radius:8px;">
  <div style="display:flex; align-items:flex-start; gap:1rem;">
    <div style="font-size:1.8rem; line-height:1;">🛡️</div>
    <div style="font-size:0.88rem; line-height:1.6; color:var(--adm-text);">
      <strong>Askeri Düzeyde Veri Koruma:</strong> Veritabanı dosyanız (SQLite) harici bir yolla indirilse veya sızdırılsa dahi, hiçbir e-posta adresi düz metin olarak okunamaz. Her kayıt 16-byte benzersiz rastgele IV ile şifrelenmiştir. Arama ve tekillik kontrolleri ise tek yönlü <code>HMAC-SHA256</code> kör indeksleme (Blind Indexing) ile gerçekleştirilir.
    </div>
  </div>
</div>

<!-- TEKİL HIZLI EKLEME VE ARAMA FORMU -->
<div class="card" style="margin-bottom:1.5rem; padding:1.25rem;">
  <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
    
    <!-- Arama Kutusu -->
    <form method="GET" action="<?= SITE_URL; ?>/admin/subscribers.php" style="display:flex; gap:0.5rem; flex:1; min-width:280px;">
      <input type="text" name="q" value="<?= htmlspecialchars( $search_query, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="E-posta veya kör indeks ara..." class="form-control" style="font-size:0.85rem;" />
      <button type="submit" class="btn btn-secondary">Ara</button>
      <?php if ( ! empty( $search_query ) ) : ?>
        <a href="<?= SITE_URL; ?>/admin/subscribers.php" class="btn btn-secondary" title="Filtreyi Temizle">Temizle</a>
      <?php endif; ?>
    </form>

    <!-- Hızlı Tekil E-Posta Ekle -->
    <form method="POST" action="<?= SITE_URL; ?>/admin/subscribers.php" style="display:flex; gap:0.5rem; flex:1; min-width:320px;">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
      <input type="hidden" name="post_action" value="add_single" />
      <input type="email" name="email" placeholder="Hızlı tekil e-posta ekle..." required class="form-control" style="font-size:0.85rem;" />
      <button type="submit" class="btn btn-primary" style="white-space:nowrap;">+ Abone Ekle</button>
    </form>

  </div>
</div>

<!-- ABONE TABLOSU -->
<div class="card" style="padding:0; overflow:hidden;">
  <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--adm-border); display:flex; justify-content:space-between; align-items:center;">
    <h3 style="margin:0; font-size:1.05rem; font-weight:600;">Kayıtlı Bülten Aboneleri (<?= count( $subscribers ); ?>)</h3>
    <span style="font-size:0.75rem; color:var(--adm-muted); font-family:var(--adm-font-mono);">Anlık şifre çözümüyle listelenmektedir</span>
  </div>

  <?php if ( empty( $subscribers ) ) : ?>
    <div style="padding:3rem 1.5rem; text-align:center; color:var(--adm-muted);">
      <div style="font-size:2rem; margin-bottom:0.75rem;">📭</div>
      <p style="margin:0;">Henüz kayıtlı bülten abonesi bulunmuyor veya aramanızla eşleşen sonuç yok.</p>
    </div>
  <?php else : ?>
    <div style="overflow-x:auto;">
      <table class="admin-table" style="width:100%; text-align:left; border-collapse:collapse;">
        <thead>
          <tr style="background:var(--adm-bg); border-bottom:1px solid var(--adm-border); font-size:0.78rem; color:var(--adm-muted); text-transform:uppercase;">
            <th style="padding:0.75rem 1.25rem; width:50px;">#</th>
            <th style="padding:0.75rem 1.25rem;">E-Posta Adresi</th>
            <th style="padding:0.75rem 1.25rem;">Kör İndeks (Hash)</th>
            <th style="padding:0.75rem 1.25rem;">Durum</th>
            <th style="padding:0.75rem 1.25rem;">Kaynak</th>
            <th style="padding:0.75rem 1.25rem;">Kayıt Tarihi</th>
            <th style="padding:0.75rem 1.25rem; text-align:right;">İşlemler</th>
          </tr>
        </thead>
        <tbody style="font-size:0.88rem;">
          <?php foreach ( $subscribers as $sub ) : 
            $unsub_url = SITE_URL . '/bulten/ayril?token=' . $sub['unsubscribe_token'];
          ?>
            <tr style="border-bottom:1px solid var(--adm-border);">
              <td style="padding:0.85rem 1.25rem; font-family:var(--adm-font-mono); color:var(--adm-muted); font-size:0.75rem;">
                <?= $sub['id']; ?>
              </td>
              <td style="padding:0.85rem 1.25rem; font-weight:600;">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                  <span><?= htmlspecialchars( $sub['decrypted_email'], ENT_QUOTES, 'UTF-8' ); ?></span>
                  <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars( $sub['decrypted_email'], ENT_QUOTES, 'UTF-8' ); ?>'); alert('E-posta kopyalandı.');" style="background:transparent; border:none; cursor:pointer; font-size:0.8rem; opacity:0.6;" title="E-postayı kopyala">📋</button>
                </div>
              </td>
              <td style="padding:0.85rem 1.25rem; font-family:var(--adm-font-mono); font-size:0.72rem; color:var(--adm-muted);">
                <span title="<?= htmlspecialchars( $sub['email_hash'], ENT_QUOTES, 'UTF-8' ); ?>" style="background:rgba(0,0,0,0.04); padding:0.2rem 0.4rem; border-radius:4px;">
                  <?= substr( $sub['email_hash'], 0, 10 ); ?>...<?= substr( $sub['email_hash'], -6 ); ?>
                </span>
              </td>
              <td style="padding:0.85rem 1.25rem;">
                <?php if ( $sub['status'] === 'active' ) : ?>
                  <span class="pill-badge pill-green">● Aktif Abone</span>
                <?php else : ?>
                  <span class="pill-badge pill-gray" style="background:#eee; color:#666;">○ Ayrıldı</span>
                <?php endif; ?>
              </td>
              <td style="padding:0.85rem 1.25rem; font-size:0.8rem; color:var(--adm-muted);">
                <?php if ( $sub['source'] === 'bulk_import' ) : ?>
                  <span>📥 Toplu Aktarım</span>
                <?php elseif ( $sub['source'] === 'admin_manual' ) : ?>
                  <span>👤 Manuel Eklendi</span>
                <?php else : ?>
                  <span>🌐 Site Altbilgisi</span>
                <?php endif; ?>
              </td>
              <td style="padding:0.85rem 1.25rem; font-size:0.8rem; color:var(--adm-muted); font-family:var(--adm-font-mono);">
                <?= date( 'd.m.Y H:i', strtotime( $sub['created_at'] ) ); ?>
              </td>
              <td style="padding:0.85rem 1.25rem; text-align:right; white-space:nowrap;">
                <button type="button" class="btn btn-secondary" style="padding:0.3rem 0.6rem; font-size:0.75rem; margin-right:0.35rem;" onclick="navigator.clipboard.writeText('<?= $unsub_url; ?>'); alert('Özel ayrılma bağlantısı panoya kopyalandı:\n<?= $unsub_url; ?>');" title="Abonelikten ayrılma linkini kopyala">
                  🔗 Ayrılma Linki
                </button>
                <a href="<?= SITE_URL; ?>/admin/subscribers.php?action=toggle&id=<?= $sub['id']; ?>" class="btn btn-secondary" style="padding:0.3rem 0.6rem; font-size:0.75rem; margin-right:0.35rem;" title="Durumu Değiştir">
                  <?= $sub['status'] === 'active' ? 'Pasif Yap' : 'Aktif Et'; ?>
                </a>
                <a href="<?= SITE_URL; ?>/admin/subscribers.php?action=delete&id=<?= $sub['id']; ?>" class="btn btn-secondary" style="padding:0.3rem 0.6rem; font-size:0.75rem; color:var(--adm-red);" onclick="return confirm('Bu aboneyi kalıcı olarak silmek istediğinize emin misiniz?');" title="Kalıcı Olarak Sil">
                  🗑️ Sil
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- TOPLU İÇE AKTARMA MODAL DIALOG -->
<div id="bulkModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:1.5rem;">
  <div style="background:var(--adm-card, #fff); max-width:620px; width:100%; border-radius:10px; border:1px solid var(--adm-border); box-shadow:0 20px 40px rgba(0,0,0,0.2); overflow:hidden;">
    
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--adm-border); display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0; font-size:1.15rem; font-weight:700;">📥 Toplu E-Posta İçe Aktar</h3>
      <button type="button" onclick="toggleBulkModal()" style="background:transparent; border:none; font-size:1.3rem; cursor:pointer; color:var(--adm-muted);">&times;</button>
    </div>

    <form method="POST" action="<?= SITE_URL; ?>/admin/subscribers.php" style="padding:1.5rem;">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
      <input type="hidden" name="post_action" value="bulk_import" />

      <p style="font-size:0.85rem; color:var(--adm-muted); line-height:1.5; margin-top:0; margin-bottom:1rem;">
        Elinizdeki mevcut e-posta listelerini (Excel'den kopyalanan sütunlar, virgülle veya alt alta ayrılmış listeler) aşağıdaki alana yapıştırabilirsiniz. Sistem mükerrer olanları ve geçersiz biçimleri otomatik olarak eleyip her birini tek tek AES-256 ile şifreleyerek veritabanına aktaracaktır.
      </p>

      <div style="margin-bottom:1.25rem;">
        <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.5rem;">E-Posta Adresleri (Alt alta veya virgülle ayrılmış):</label>
        <textarea name="emails_raw" rows="9" placeholder="ornek1@gmail.com&#10;ornek2@hotmail.com&#10;ornek3@domain.com" required class="form-control" style="font-family:var(--adm-font-mono); font-size:0.82rem; line-height:1.4;"></textarea>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" class="btn btn-secondary" onclick="toggleBulkModal()">İptal</button>
        <button type="submit" class="btn btn-primary">Şifreleyerek İçe Aktar ↗</button>
      </div>
    </form>

  </div>
</div>

<script>
function toggleBulkModal() {
  var m = document.getElementById('bulkModal');
  if (m.style.display === 'none' || m.style.display === '') {
    m.style.display = 'flex';
  } else {
    m.style.display = 'none';
  }
}
</script>

<?php
require PATH_ADMIN . '/layout/footer.php';
