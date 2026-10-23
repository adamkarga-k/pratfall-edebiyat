<?php
/**
 * Pratfall Standalone - Modern & Sakin Yönetim Masası (Kontrol Paneli)
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

$page_title = 'Kontrol Paneli';
$active_tab = 'dashboard';
require PATH_ADMIN . '/layout/header.php';

$db = Database::get();

// Sayımlar
$total_posts       = (int) $db->query( "SELECT COUNT(*) FROM posts" )->fetchColumn();
$published_posts   = (int) $db->query( "SELECT COUNT(*) FROM posts WHERE status = 'published'" )->fetchColumn();
$draft_posts       = (int) $db->query( "SELECT COUNT(*) FROM posts WHERE status = 'draft'" )->fetchColumn();
$total_authors     = (int) $db->query( "SELECT COUNT(*) FROM authors" )->fetchColumn();
$total_magazines   = (int) $db->query( "SELECT COUNT(*) FROM magazines" )->fetchColumn();

// Son Yazılar (Son Etkinlik için)
$recent_posts = $db->query( "
    SELECT p.*, a.name AS author_name 
    FROM posts p 
    LEFT JOIN authors a ON p.author_id = a.id 
    ORDER BY p.published_at DESC 
    LIMIT 7
" )->fetchAll();

// SQLite Dosya Boyutu
$db_size = file_exists( DB_FILE ) ? round( filesize( DB_FILE ) / 1024, 1 ) . ' KB' : '0 KB';
?>

<!-- 1. ÜST İSTATİSTİK KARTLARI (Sakin & Ferah) -->
<div class="stats-row">
  
  <div class="stat-card">
    <div class="stat-card-label">Yazılar & Eserler</div>
    <div class="stat-card-value"><?= $total_posts; ?></div>
    <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.4rem;">
      <span class="pill-badge pill-green" style="padding:0.1rem 0.4rem;"><?= $published_posts; ?> Yayında</span>
      <?php if ( $draft_posts > 0 ) : ?>
        <span class="pill-badge pill-yellow" style="padding:0.1rem 0.4rem; margin-left:0.25rem;"><?= $draft_posts; ?> Taslak</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-label">Dergilerimiz (Sayılar)</div>
    <div class="stat-card-value"><?= $total_magazines; ?></div>
    <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.4rem;">
      Yatay vitrinde sergilenenler
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-label">Kimler Yazmış? (Yazarlar)</div>
    <div class="stat-card-value"><?= $total_authors; ?></div>
    <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.4rem;">
      Kayıtlı edebi yazar profili
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-label">SQLite Veritabanı</div>
    <div class="stat-card-value" style="font-size:1.8rem;"><?= $db_size; ?></div>
    <div style="font-size:0.75rem; color:var(--adm-green); margin-top:0.4rem; font-weight:600;">
      ● Ultra hafif & bağımsız
    </div>
  </div>

</div>

<!-- 2. ANA IZGARA: İÇERİK DAĞILIMI + SON ETKİNLİK -->
<div class="dashboard-grid">

  <!-- Sol Kart: İçerik Özeti (Referans Görseldeki İçerik Kutusu) -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">İçerik Dağılımı</h2>
      <a href="<?= SITE_URL; ?>/admin/posts?action=new" class="btn btn-sm" style="font-size:0.75rem;">+ Hızlı Ekle</a>
    </div>
    <div class="card-body" style="padding-top:0.5rem; padding-bottom:0.5rem;">
      <ul class="content-summary-list">
        
        <li class="content-summary-item">
          <span>Yazılar & Eserler</span>
          <span class="pill-badge pill-green">@ <?= $total_posts; ?></span>
        </li>

        <li class="content-summary-item">
          <span>Yayındaki Metinler</span>
          <span class="pill-badge pill-green">@ <?= $published_posts; ?></span>
        </li>

        <li class="content-summary-item">
          <span>Taslak Metinler</span>
          <span class="pill-badge pill-yellow">@ <?= $draft_posts; ?></span>
        </li>

        <li class="content-summary-item">
          <span>Dergilerimiz (Sayılar)</span>
          <span class="pill-badge pill-blue">@ <?= $total_magazines; ?></span>
        </li>

        <li class="content-summary-item">
          <span>Kimler Yazmış? (Yazar Profilleri)</span>
          <span class="pill-badge pill-gray">@ <?= $total_authors; ?></span>
        </li>

      </ul>
    </div>
  </div>

  <!-- Sağ Kart: Son Etkinlik (Referans Görseldeki Son Etkinlik Listesi) -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Son Etkinlik & Eserler</h2>
      <a href="<?= SITE_URL; ?>/admin/posts" class="btn btn-sm" style="font-size:0.75rem;">Tümünü Gör (<?= $total_posts; ?>) →</a>
    </div>
    <div class="card-body" style="padding-top:0.5rem; padding-bottom:0.5rem;">
      
      <?php if ( ! empty( $recent_posts ) ) : ?>
        <ul class="activity-feed-list">
          <?php foreach ( $recent_posts as $post ) : ?>
            <li class="activity-feed-item">
              <div class="activity-feed-left">
                <!-- Sade Onay / Belge İkonu -->
                <svg class="activity-icon-check" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                
                <div>
                  <a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $post['id']; ?>" class="activity-title" title="<?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>">
                    <?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>
                  </a>
                  <span class="activity-tag">· <?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?></span>
                  <?php if ( ! empty( $post['author_name'] ) ) : ?>
                    <span style="font-size:0.76rem; color:var(--adm-muted);"> (<?= htmlspecialchars( $post['author_name'], ENT_QUOTES, 'UTF-8' ); ?>)</span>
                  <?php endif; ?>
                </div>
              </div>

              <div style="display:flex; align-items:center; gap:0.75rem;">
                <span class="pill-badge <?= $post['status'] === 'published' ? 'pill-green' : 'pill-yellow'; ?>" style="font-size:0.7rem;">
                  <?= $post['status'] === 'published' ? 'Yayında' : 'Taslak'; ?>
                </span>
                <span class="activity-time"><?= date( 'd.m.Y', strtotime( $post['published_at'] ) ); ?></span>
                <a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $post['id']; ?>" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.72rem;">
                  Düzenle
                </a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else : ?>
        <div style="padding:2.5rem; text-align:center; color:var(--adm-muted);">
          Henüz kayıtlı bir eser bulunmuyor.
        </div>
      <?php endif; ?>

    </div>
  </div>

</div>

<!-- 3. HIZLI ERİŞİM DİP BİLGİSİ -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-top:1.5rem;">
  
  <div class="card" style="margin-bottom:0; background:linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
    <div class="card-body" style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <div style="font-weight:700; font-size:0.95rem; margin-bottom:0.25rem;">Word (.docx) Toplu İçe Aktarıcı</div>
        <div style="font-size:0.82rem; color:var(--adm-muted);">Bilgisayarınızdan onlarca Word dosyasını tek tıkla içeri aktarın.</div>
      </div>
      <a href="<?= SITE_URL; ?>/admin/import-docx" class="btn btn-accent btn-sm">
        İçe Aktar →
      </a>
    </div>
  </div>

  <div class="card" style="margin-bottom:0; background:linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
    <div class="card-body" style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <div style="font-weight:700; font-size:0.95rem; margin-bottom:0.25rem;">cPanel Git Dağıtımı</div>
        <div style="font-size:0.82rem; color:var(--adm-muted);">Kod güncellemelerini tek tıkla canlı sunucunuza aktarın.</div>
      </div>
      <a href="<?= SITE_URL; ?>/admin/git-sync" class="btn btn-sm">
        Senkronizasyon →
      </a>
    </div>
  </div>

</div>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
