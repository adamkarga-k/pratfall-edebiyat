<?php
/**
 * Pratfall Standalone - Medya Kütüphanesi & Otomatik Optimize Edilmiş Görsel Yönetimi
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Medya Kütüphanesi';
$active_tab = 'media';

$msg = '';
$err = '';

// 1. Silme İşlemi
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['file'] ) ) {
    $filename = basename( $_GET['file'] );
    $sub = trim( $_GET['sub'] ?? '' );
    $sub = in_array( $sub, [ 'covers', 'docx-images' ] ) ? $sub : '';
    
    $file_path = PATH_UPLOADS . ( $sub ? '/' . $sub : '' ) . '/' . $filename;
    if ( file_exists( $file_path ) && is_file( $file_path ) ) {
        @unlink( $file_path );
        $msg = "{$filename} görseli başarıyla silindi.";
    } else {
        $err = "Görsel bulunamadı veya silinemedi.";
    }
}

// 2. Yeni Görsel Yükleme (Otomatik WebP & 1600px Optimizasyon)
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_FILES['media_files'] ) ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $uploaded_count = 0;
        $files = $_FILES['media_files'];
        $count = count( $files['name'] );

        for ( $i = 0; $i < $count; $i++ ) {
            if ( $files['error'][$i] === UPLOAD_ERR_OK && ! empty( $files['tmp_name'][$i] ) ) {
                $file_array = [
                    'name'     => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
                $res = ImageOptimizer::optimize_uploaded_file( $file_array, PATH_UPLOADS . '/covers', 'media' );
                if ( $res ) {
                    $uploaded_count++;
                }
            }
        }

        if ( $uploaded_count > 0 ) {
            $msg = "{$uploaded_count} adet görsel başarıyla optimize edildi (WebP sıkıştırması uygulandı) ve kütüphaneye eklendi.";
        } else {
            $err = "Görsel yüklenemedi. Lütfen geçerli bir JPG, PNG veya WebP görseli seçin.";
        }
    }
}

// 3. Klasörlerdeki Tüm Görselleri Tara
$all_media = [];
$scan_dirs = [
    ''            => PATH_UPLOADS,
    'covers'      => PATH_UPLOADS . '/covers',
    'docx-images' => PATH_UPLOADS . '/docx-images',
];

$valid_exts = [ 'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg' ];

foreach ( $scan_dirs as $sub => $dir ) {
    if ( is_dir( $dir ) ) {
        $files = scandir( $dir );
        foreach ( $files as $f ) {
            if ( $f === '.' || $f === '..' || strpos( $f, '.htaccess' ) !== false || strpos( $f, '.gitkeep' ) !== false ) {
                continue;
            }
            $full_path = $dir . '/' . $f;
            if ( is_file( $full_path ) ) {
                $ext = strtolower( pathinfo( $f, PATHINFO_EXTENSION ) );
                if ( in_array( $ext, $valid_exts ) ) {
                    $size_bytes = filesize( $full_path );
                    $size_kb = round( $size_bytes / 1024, 1 );
                    $dimensions = '—';
                    if ( $ext !== 'svg' ) {
                        $img_info = @getimagesize( $full_path );
                        if ( $img_info ) {
                            $dimensions = $img_info[0] . '×' . $img_info[1] . ' px';
                        }
                    }
                    $rel_url = SITE_URL . '/uploads' . ( $sub ? '/' . $sub : '' ) . '/' . $f;

                    $all_media[] = [
                        'filename'   => $f,
                        'sub'        => $sub,
                        'url'        => $rel_url,
                        'size'       => $size_kb . ' KB',
                        'size_raw'   => $size_bytes,
                        'dimensions' => $dimensions,
                        'ext'        => strtoupper( $ext ),
                        'mtime'      => filemtime( $full_path ),
                    ];
                }
            }
        }
    }
}

// En yeniden eskiye sırala
usort( $all_media, function( $a, $b ) {
    return $b['mtime'] - $a['mtime'];
} );

$total_storage = round( array_sum( array_column( $all_media, 'size_raw' ) ) / 1024 / 1024, 2 );

require PATH_ADMIN . '/layout/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="margin:0; font-size:1.4rem; font-weight:700;">Medya Kütüphanesi</h2>
    <p style="margin:0.2rem 0 0 0; color:var(--adm-muted); font-size:0.85rem;">
      Tüm kapaklar, yazar portreleri ve Word aktarımlarından gelen görseller. Sitenizin hızı için yüklenen her görsel otomatik sıkıştırılır ve WebP'ye dönüştürülür.
    </p>
  </div>
  <div>
    <span class="pill-badge pill-green" style="font-size:0.8rem; padding:0.35rem 0.75rem;">
      Toplam: <?= count( $all_media ); ?> Dosya · <?= $total_storage; ?> MB
    </span>
  </div>
</div>

<?php if ( ! empty( $msg ) ) : ?>
  <div class="alert alert-success">✓ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
<?php endif; ?>

<?php if ( ! empty( $err ) ) : ?>
  <div class="alert alert-danger">✕ <?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></div>
<?php endif; ?>

<!-- YENİ GÖRSEL YÜKLEME KARTI -->
<div class="card" style="margin-bottom:1.75rem;">
  <div class="card-body" style="padding:1.5rem;">
    <form method="POST" action="<?= SITE_URL; ?>/admin/media.php" enctype="multipart/form-data" id="uploadForm">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
      <div style="display:flex; align-items:center; gap:1.25rem; flex-wrap:wrap;">
        <div style="flex:1; min-width:260px;">
          <label class="form-label" style="margin-bottom:0.35rem;">Yeni Görsel Yükle (Toplu Seçim Yapabilirsiniz)</label>
          <input type="file" name="media_files[]" multiple accept="image/*" class="form-control" style="padding:0.45rem;" required />
        </div>
        <div style="margin-top:auto;">
          <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.25rem;">
            <span>⚡</span> Yükle & Otomatik Optimize Et
          </button>
        </div>
      </div>
      <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.5rem;">
        ✦ Otomatik Sıkıştırma: Görseller 1600px genişliğe ölçeklendirilir ve %85 WebP formatında sıkıştırılarak maksimum hız sağlanır.
      </div>
    </form>
  </div>
</div>

<!-- GÖRSEL IZGARA GALERİSİ -->
<?php if ( ! empty( $all_media ) ) : ?>
  <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:1.25rem;">
    <?php foreach ( $all_media as $m ) : ?>
      <div class="card" style="margin-bottom:0; overflow:hidden; display:flex; flex-direction:column;">
        <div style="position:relative; width:100%; height:150px; background:#f1f5f9; overflow:hidden;">
          <img src="<?= $m['url']; ?>" alt="<?= htmlspecialchars( $m['filename'], ENT_QUOTES, 'UTF-8' ); ?>" style="width:100%; height:100%; object-fit:cover; display:block;" loading="lazy" />
          <span class="pill-badge pill-gray" style="position:absolute; top:8px; right:8px; font-size:0.68rem; background:rgba(0,0,0,0.65); color:#fff; border:none;">
            <?= $m['ext']; ?>
          </span>
          <?php if ( $m['sub'] ) : ?>
            <span class="pill-badge pill-gray" style="position:absolute; bottom:8px; left:8px; font-size:0.65rem; background:rgba(0,0,0,0.65); color:#fef08a; border:none;">
              <?= $m['sub']; ?>
            </span>
          <?php endif; ?>
        </div>
        
        <div style="padding:0.85rem; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
          <div>
            <div style="font-size:0.82rem; font-weight:600; word-break:break-all; line-height:1.3; margin-bottom:0.35rem;" title="<?= htmlspecialchars( $m['filename'], ENT_QUOTES, 'UTF-8' ); ?>">
              <?= htmlspecialchars( mb_strimwidth( $m['filename'], 0, 24, '...' ), ENT_QUOTES, 'UTF-8' ); ?>
            </div>
            <div style="font-size:0.73rem; color:var(--adm-muted); display:flex; justify-content:space-between; margin-bottom:0.75rem;">
              <span><?= $m['dimensions']; ?></span>
              <strong style="color:var(--adm-text);"><?= $m['size']; ?></strong>
            </div>
          </div>

          <div style="display:flex; gap:0.4rem; border-top:1px solid var(--adm-border); padding-top:0.6rem;">
            <button type="button" class="btn btn-sm" onclick="copyUrl('<?= $m['url']; ?>', this)" style="flex:1; justify-content:center; font-size:0.72rem; padding:0.25rem;">
              📋 URL Kopyala
            </button>
            <a href="<?= SITE_URL; ?>/admin/media.php?action=delete&file=<?= urlencode( $m['filename'] ); ?>&sub=<?= urlencode( $m['sub'] ); ?>" onclick="return confirm('Bu görseli silmek istediğinize emin misiniz?')" class="btn btn-sm" style="color:#ef4444; font-size:0.72rem; padding:0.25rem 0.5rem;" title="Görseli Sil">
              Sil
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else : ?>
  <div class="card" style="padding:4rem 2rem; text-align:center;">
    <div style="font-size:2.5rem; margin-bottom:0.75rem;">🖼️</div>
    <h3 style="margin:0 0 0.5rem 0;">Medya Kütüphaneniz Boş</h3>
    <p style="color:var(--adm-muted); font-size:0.85rem; margin:0 auto; max-width:400px;">
      Yukarıdaki yükleme alanını kullanarak yazı kapaklarınızı ve dergi fotoğraflarınızı yükleyebilirsiniz.
    </p>
  </div>
<?php endif; ?>

<script>
function copyUrl(url, btn) {
  navigator.clipboard.writeText(url).then(function() {
    const origText = btn.innerHTML;
    btn.innerHTML = '✓ Kopyalandı!';
    btn.style.color = '#10b981';
    setTimeout(function() {
      btn.innerHTML = origText;
      btn.style.color = '';
    }, 1800);
  });
}
</script>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
