<?php
/**
 * Pratfall Standalone - Yönetim Masası Ana Gösterge (Dashboard)
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

$page_title = 'Gösterge';
$active_tab = 'dashboard';
require PATH_ADMIN . '/layout/header.php';

$db = Database::get();

// Sayımlar
$total_posts = (int) $db->query( "SELECT COUNT(*) FROM posts" )->fetchColumn();
$total_authors = (int) $db->query( "SELECT COUNT(*) FROM authors" )->fetchColumn();
$total_magazines = (int) $db->query( "SELECT COUNT(*) FROM magazines" )->fetchColumn();

// Son Yazılar
$recent_posts = $db->query( "
    SELECT p.*, a.name as author_name 
    FROM posts p 
    LEFT JOIN authors a ON p.author_id = a.id 
    ORDER BY p.published_at DESC 
    LIMIT 6
" )->fetchAll();

// SQLite Dosya Boyutu
$db_size = file_exists( DB_FILE ) ? round( filesize( DB_FILE ) / 1024, 1 ) . ' KB' : '0 KB';
?>

<div class="admin-header-row">
  <div>
    <h1 class="admin-title">Yönetim Masası</h1>
    <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
      Hoş geldiniz. Mecmuanın tüm içeriklerini, yazarlarını ve dergilerini buradan yönetebilirsiniz.
    </p>
  </div>
  <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
    <a href="<?= SITE_URL; ?>/admin/import-docx" class="btn btn-primary" style="background:#ea580c;">
      📥 Word'den Toplu İçe Aktar
    </a>
    <a href="<?= SITE_URL; ?>/admin/posts?action=new" class="btn btn-primary">
      + Yeni Metin Ekle
    </a>
  </div>
</div>

<!-- 1. Hızlı İstatistikler -->
<div class="stats-grid">
  <div class="stat-box">
    <div class="stat-num"><?= $total_posts; ?></div>
    <div class="stat-label">Yayındaki Eserler</div>
  </div>
  <div class="stat-box">
    <div class="stat-num"><?= $total_authors; ?></div>
    <div class="stat-label">Kayıtlı Yazar</div>
  </div>
  <div class="stat-box">
    <div class="stat-num"><?= $total_magazines; ?></div>
    <div class="stat-label">Dergi Sayısı</div>
  </div>
  <div class="stat-box">
    <div class="stat-num" style="color:var(--admin-green);"><?= $db_size; ?></div>
    <div class="stat-label">Veritabanı (SQLite)</div>
  </div>
</div>

<!-- 2. Son Eklenen Yazılar Tablosu -->
<div class="admin-card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
    <h2 style="margin:0; font-family:var(--admin-serif); font-size:1.4rem;">Son Yayınlanan Eserler</h2>
    <a href="<?= SITE_URL; ?>/admin/posts" class="btn" style="font-size:0.75rem;">Tümünü Gör (<?= $total_posts; ?>) →</a>
  </div>

  <table class="admin-table">
    <thead>
      <tr>
        <th>Başlık</th>
        <th>Yazar</th>
        <th>Kategori</th>
        <th>Tarih</th>
        <th>Durum</th>
        <th style="text-align:right;">İşlemler</th>
      </tr>
    </thead>
    <tbody>
      <?php if ( ! empty( $recent_posts ) ) : ?>
        <?php foreach ( $recent_posts as $post ) : ?>
          <tr>
            <td>
              <strong><a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $post['id']; ?>" style="color:var(--admin-text); text-decoration:none;"><?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?></a></strong>
            </td>
            <td><?= htmlspecialchars( $post['author_name'] ?? 'Bilinmiyor', ENT_QUOTES, 'UTF-8' ); ?></td>
            <td><span class="stamp-tag" style="font-size:0.7rem;"><?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?></span></td>
            <td style="font-family:var(--admin-mono); font-size:0.8rem;"><?= date( 'd.m.Y', strtotime( $post['published_at'] ) ); ?></td>
            <td>
              <span style="font-family:var(--admin-mono); font-size:0.75rem; color:<?= $post['status'] === 'published' ? 'var(--admin-green)' : 'var(--admin-muted)'; ?>; font-weight:700;">
                <?= $post['status'] === 'published' ? '● Yayında' : '○ Taslak'; ?>
              </span>
            </td>
            <td style="text-align:right;">
              <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>" target="_blank" class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Görüntüle ↗</a>
              <a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $post['id']; ?>" class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Düzenle</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else : ?>
        <tr>
          <td colspan="6" style="text-align:center; padding:2rem; color:var(--admin-muted);">
            Henüz eklenmiş bir yazı yok.
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- 3. Sistem & cPanel Git Durumu Kartı -->
<div class="admin-card" style="background:#fafaf9; border-left:6px solid var(--admin-blue);">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
    <div>
      <span class="tape-badge rot-n1" style="font-size:0.7rem; background:var(--admin-blue); color:#fff; border-color:#0369a1;">
        ✦ cPanel Git Sürüm Kontrolü
      </span>
      <h3 style="margin:0.5rem 0 0.25rem 0; font-size:1.2rem;">Canlı Senkronizasyon ve Dağıtım</h3>
      <p style="margin:0; font-size:0.85rem; color:var(--admin-muted); line-height:1.5;">
        Antigravity üzerinde yaptığımız tüm tasarım ve kod güncellemeleri Git deposu üzerinden cPanel'inize aktarılabilir.
      </p>
    </div>
    <a href="<?= SITE_URL; ?>/admin/git-sync" class="btn" style="font-size:0.8rem;">
      Git Senkronizasyon Durumu →
    </a>
  </div>
</div>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
