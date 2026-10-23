<?php
/**
 * Pratfall Standalone - cPanel Git Senkronizasyon ve Canlı Dağıtım Paneli
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Git Senkronizasyon';
$active_tab = 'git-sync';

$git_dir = PRATFALL_ROOT . '/.git';
$has_git = is_dir( $git_dir );
$commit_info = 'Git deposu henüz bağlanmadı veya paylaşımlı barındırmada .git dizini gizlendi.';
$last_commit_hash = '—';
$last_commit_msg  = '—';
$last_commit_date = '—';

if ( $has_git ) {
    // HEAD dosyasını oku
    $head_file = $git_dir . '/HEAD';
    if ( file_exists( $head_file ) ) {
        $head = trim( file_get_contents( $head_file ) );
        if ( strpos( $head, 'ref: ' ) === 0 ) {
            $ref_path = $git_dir . '/' . substr( $head, 5 );
            if ( file_exists( $ref_path ) ) {
                $last_commit_hash = substr( trim( file_get_contents( $ref_path ) ), 0, 8 );
            }
        } else {
            $last_commit_hash = substr( $head, 0, 8 );
        }
    }
}

// cPanel Git Pull Tetikleme (Eğer exec izinliyse)
$pull_output = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['pull_action'] ) ) {
    if ( Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        if ( function_exists( 'exec' ) ) {
            @exec( 'cd ' . escapeshellarg( PRATFALL_ROOT ) . ' && git pull 2>&1', $out, $ret );
            $pull_output = implode( "\n", $out );
        } else {
            $pull_output = 'Sunucunuzda exec() fonksiyonu güvenlik nedeniyle kapalı. Güncellemeleri cPanel > Git Sürüm Kontrolü menüsünden "Güncellemeleri Çek (Pull)" butonuna basarak alabilirsiniz.';
        }
    }
}

require PATH_ADMIN . '/layout/header.php';
?>

<div class="admin-header-row">
  <div>
    <h1 class="admin-title">🔄 cPanel Git Senkronizasyon & Canlı Dağıtım</h1>
    <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
      Antigravity üzerinde hazırladığımız kod ve tasarım güncellemelerinin cPanel hosting'inize anında yansımasını sağlar.
    </p>
  </div>
</div>

<?php if ( ! empty( $pull_output ) ) : ?>
  <div class="alert alert-success">
    <strong>Git Pull Çıktısı:</strong>
    <pre style="margin:0.5rem 0 0 0; font-family:var(--admin-mono); font-size:0.8rem;"><?= htmlspecialchars( $pull_output, ENT_QUOTES, 'UTF-8' ); ?></pre>
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem; align-items:start;">
  
  <div>
    <!-- Durum Kartı -->
    <div class="admin-card" style="border-left:6px solid var(--admin-blue);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <span class="tape-badge rot-n1" style="background:var(--admin-blue); color:#fff; border-color:#0369a1;">
          ✦ CPANEL YÖNTEM C ENTEGRASYONU
        </span>
        <span class="stamp-tag">SÜRÜM v<?= PRATFALL_APP_VERSION; ?></span>
      </div>

      <h2 style="margin:0 0 1rem 0; font-family:var(--admin-serif); font-size:1.4rem;">
        Sistem ve Kod Senkronizasyon Durumu
      </h2>

      <div style="font-family:var(--admin-mono); font-size:0.85rem; line-height:1.8; background:#fafaf9; padding:1.25rem; border:1.5px solid var(--admin-border); margin-bottom:1.5rem;">
        <div><strong>Git Durumu:</strong> <?= $has_git ? '✅ Aktif (.git dizini mevcut)' : 'ℹ️ cPanel veya Dosya Dağıtımı Aktif'; ?></div>
        <div><strong>Son Commit ID:</strong> <code><?= $last_commit_hash; ?></code></div>
        <div><strong>Veritabanı Koruması:</strong> ✅ <code>data/database.sqlite</code> (Gitignore korumalı, güncellemelerde asla ezilmez)</div>
        <div><strong>Yüklenen Resimler:</strong> ✅ <code>uploads/</code> (Gitignore korumalı, resimleriniz asla kaybolmaz)</div>
      </div>

      <form method="POST" action="<?= SITE_URL; ?>/admin/git-sync">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
        <input type="hidden" name="pull_action" value="1" />
        <button type="submit" class="btn btn-primary" style="padding:0.65rem 1.25rem;">
          🔄 Güncellemeleri Kontrol Et & Canlıya Al (Git Pull)
        </button>
      </form>
    </div>

    <!-- Güvenlik & Mimari İlkesi -->
    <div class="admin-card">
      <h3 style="margin-top:0; font-family:var(--admin-serif); font-size:1.2rem;">
        🛡️ İçerikleriniz Güncellemelerden Neden Etkilenmez?
      </h3>
      <p style="font-size:0.9rem; line-height:1.6; color:var(--admin-text); margin:0;">
        Bu sistemde <strong>İçerik (Yazılar, Dergiler, Yazarlar)</strong> ile <strong>Kod (Tasarım, HTML, CSS, PHP)</strong> tamamen birbirinden yalıtılmıştır. Antigravity üzerinde yeni bir sayfa şablonu, yeni bir renk paleti veya yeni bir özellik yazdığımızda, cPanel Git sadece kod dosyalarını günceller. Sizin panelden girdiğiniz hiçbir eser, hiçbir yazar profili veya resim asla silinmez.
      </p>
    </div>
  </div>

  <!-- cPanel Kurulum Rehberi -->
  <div class="admin-card" style="background:#fafaf9; font-size:0.85rem; line-height:1.6;">
    <h3 style="margin-top:0; font-family:var(--admin-serif); font-size:1.15rem; border-bottom:1.5px solid var(--admin-border); padding-bottom:0.5rem;">
      📋 cPanel'de Git Nasıl Bağlanır?
    </h3>
    <ol style="padding-left:1.2rem; margin:0.75rem 0 0 0;">
      <li><strong>cPanel</strong> paneline giriş yapın.</li>
      <li><strong>Dosyalar</strong> altında <strong>Git™ Sürüm Kontrolü</strong> (Git Version Control) aracına tıklayın.</li>
      <li><strong>Oluştur (Create)</strong> butonuna basın.</li>
      <li>GitHub depo bağlantımızı ve hosting klasörünüzü (<code>public_html</code>) seçin.</li>
      <li><strong>Oluştur</strong> deyin. Artık burada yaptığımız her güncelleme tek tıkla canlı sitenize akar!</li>
    </ol>
  </div>

</div>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
