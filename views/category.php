<?php
/**
 * Pratfall Standalone - Kategori Eser Akışı (/kategori/{slug})
 * 
 * - Seçilen edebi türe (Şiir, Deneme, İnceleme vb.) ait tüm eserleri listeler.
 * - Sayfalama (pagination) ve diğer kategorilere hızlı geçiş barı içerir.
 */

$db = Database::get();

// 1. Kategoriyi Tespit Et
$cat_row = null;
if ( ! empty( $slug ) ) {
    $stmt = $db->prepare( "SELECT * FROM categories WHERE slug = ? LIMIT 1" );
    $stmt->execute( [ $slug ] );
    $cat_row = $stmt->fetch();

    // Veritabanı tablosunda yoksa, posts tablosundaki kategori isimlerinden slug eşleştirmesi dene
    if ( ! $cat_row ) {
        $existing_cats = $db->query( "SELECT DISTINCT category FROM posts WHERE status = 'published'" )->fetchAll( PDO::FETCH_COLUMN );
        foreach ( $existing_cats as $c_name ) {
            if ( Database::slugify( $c_name ) === $slug ) {
                $cat_row = [
                    'id'          => 0,
                    'name'        => $c_name,
                    'slug'        => $slug,
                    'description' => "{$c_name} türünde yayınlanan tüm edebi metinler ve eserler.",
                ];
                break;
            }
        }
    }
}

if ( ! $cat_row ) {
    http_response_code( 404 );
    require PATH_VIEWS . '/404.php';
    exit;
}

$cat_name = $cat_row['name'];
$cat_desc = ! empty( $cat_row['description'] ) ? $cat_row['description'] : "{$cat_name} türünde yayınlanan tüm edebi metinler ve eserler.";

// 2. Sayfalama Parametreleri
$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page = 9;
$offset = ( $page - 1 ) * $per_page;

// 3. Toplam Eser Sayısı
$count_stmt = $db->prepare( "
    SELECT COUNT(*) 
    FROM posts 
    WHERE status = 'published' AND LOWER(category) = LOWER(?)
" );
$count_stmt->execute( [ $cat_name ] );
$total_posts = (int) $count_stmt->fetchColumn();
$total_pages = max( 1, (int) ceil( $total_posts / $per_page ) );

// 4. Kategori Eserlerini Çek
$posts_stmt = $db->prepare( "
    SELECT p.*, a.name AS author_name, a.slug AS author_slug, a.photo AS author_photo, a.title AS author_title
    FROM posts p
    LEFT JOIN authors a ON p.author_id = a.id
    WHERE p.status = 'published' AND LOWER(p.category) = LOWER(?)
    ORDER BY p.published_at DESC
    LIMIT ? OFFSET ?
" );
$posts_stmt->bindValue( 1, $cat_name, PDO::PARAM_STR );
$posts_stmt->bindValue( 2, $per_page, PDO::PARAM_INT );
$posts_stmt->bindValue( 3, $offset, PDO::PARAM_INT );
$posts_stmt->execute();
$cat_posts = $posts_stmt->fetchAll();

// Diğer Kategoriler (Hızlı Geçiş Barı İçin)
$all_categories = $db->query( "SELECT * FROM categories ORDER BY order_num ASC, name ASC" )->fetchAll();

// SEO Bilgileri
$seo_data = [
    'title'       => "{$cat_name} Eserleri & Yazıları ✦ Pratfall",
    'description' => $cat_desc,
    'url'         => SITE_URL . '/kategori/' . $cat_row['slug'],
    'type'        => 'website',
];

require PATH_VIEWS . '/layout/header.php';
?>

<div class="pratfall-container" style="padding-top:2rem; padding-bottom:4rem;">

  <!-- Kategori Başlığı & Manşet Alanı -->
  <div class="section-headline" style="margin-bottom:2rem; border-bottom:2px solid var(--border-color); padding-bottom:1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:1rem;">
      <div>
        <div style="margin-bottom:0.4rem;">
          <span class="tape-badge rot-n1" style="font-size:0.75rem;">✦ EDEBİ TÜR & KATEGORİ</span>
        </div>
        <h1 class="section-headline-title" style="margin:0;">
          <span><?= htmlspecialchars( $cat_name, ENT_QUOTES, 'UTF-8' ); ?></span>
          <span class="pill-badge pill-purple" style="font-size:0.8rem; vertical-align:middle; margin-left:0.5rem;">
            <?= $total_posts; ?> Eser
          </span>
        </h1>
        <p style="font-family:var(--font-serif); font-size:1.15rem; color:var(--text-muted); margin:0.6rem 0 0 0; max-width:760px; line-height:1.6;">
          <?= htmlspecialchars( $cat_desc, ENT_QUOTES, 'UTF-8' ); ?>
        </p>
      </div>

      <div>
        <a href="<?= SITE_URL; ?>/" class="stamp-tag" style="background:var(--bg-tape); color:var(--text-main); text-decoration:none; font-size:0.8rem; padding:0.4rem 0.9rem; box-shadow:2px 2px 0 var(--border-color);">
          ← Ana Sayfaya Dön
        </a>
      </div>
    </div>
  </div>

  <!-- Diğer Edebi Kategoriler Hızlı Filtre Barı -->
  <?php if ( ! empty( $all_categories ) ) : ?>
    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; margin-bottom:2.5rem; background:var(--bg-card); padding:0.75rem 1rem; border:1.5px solid var(--border-color); box-shadow:2px 2px 0 var(--border-color);">
      <span style="font-family:var(--font-mono); font-size:0.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-right:0.3rem;">
        TÜRLER:
      </span>
      <?php foreach ( $all_categories as $c ) : 
        $is_active = ( strtolower( $c['name'] ) === strtolower( $cat_name ) );
      ?>
        <a href="<?= SITE_URL; ?>/kategori/<?= htmlspecialchars( $c['slug'] ); ?>" class="stamp-tag" style="font-size:0.72rem; text-decoration:none; padding:0.25rem 0.65rem; <?= $is_active ? 'background:var(--accent-primary); color:#fff; border-color:var(--accent-primary);' : 'background:var(--bg-surface); color:var(--text-main);'; ?>">
          <?= htmlspecialchars( $c['name'] ); ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Eser Listesi Grid (Ana Sayfa Akış Tasarımıyla Birebir Uyumlu) -->
  <?php if ( ! empty( $cat_posts ) ) : ?>
    <div class="asymmetric-feed-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:2rem;">
      <?php foreach ( $cat_posts as $post ) : ?>
        <article class="brutal-card post-card rot-hover-straight">
          
          <div class="post-card-thumb">
            <span class="tape-badge post-card-tag rot-n1"><?= htmlspecialchars( $post['category'] ?? $cat_name ); ?></span>
            <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>">
              <?php if ( ! empty( $post['featured_image'] ) ) : ?>
                <img src="<?= htmlspecialchars( $post['featured_image'] ); ?>" alt="<?= htmlspecialchars( $post['title'] ); ?>" loading="lazy" />
              <?php else : ?>
                <div style="width:100%; height:100%; background:#292524; display:flex; align-items:center; justify-content:center; color:#f6f3eb; padding:1.5rem; text-align:center;">
                  <?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>
                </div>
              <?php endif; ?>
            </a>
          </div>

          <div class="post-card-meta">
            <time><?= Helpers::format_date( $post['published_at'] ); ?></time>
          </div>

          <h3 class="post-card-title">
            <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>">
              <?= htmlspecialchars( $post['title'] ); ?>
            </a>
          </h3>

          <?php if ( ! empty( $post['excerpt'] ) ) : ?>
            <div class="post-card-excerpt">
              <p><?= htmlspecialchars( $post['excerpt'] ); ?></p>
            </div>
          <?php endif; ?>

          <div class="post-card-author">
            <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" class="post-card-author-img">
              <?= Helpers::author_avatar( [ 'photo' => $post['author_photo'], 'name' => $post['author_name'] ], 36 ); ?>
            </a>
            <div>
              <span class="post-card-author-name">
                <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" style="text-decoration:none; color:inherit;">
                  <?= htmlspecialchars( $post['author_name'] ?? 'Pratfall Yazarı' ); ?>
                </a>
              </span>
            </div>
          </div>

        </article>
      <?php endforeach; ?>
    </div>

    <!-- Sayfalama (Pagination) -->
    <?php if ( $total_pages > 1 ) : ?>
      <div class="pratfall-pagination" style="margin-top:3.5rem;">
        <?php if ( $page > 1 ) : ?>
          <a class="prev page-numbers" href="<?= SITE_URL; ?>/kategori/<?= $cat_row['slug']; ?>?paged=<?= $page - 1; ?>">← Önceki</a>
        <?php endif; ?>

        <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
          <?php if ( $i === $page ) : ?>
            <span class="page-numbers current"><?= $i; ?></span>
          <?php elseif ( $i <= 3 || $i > $total_pages - 2 || abs( $i - $page ) <= 1 ) : ?>
            <a class="page-numbers" href="<?= SITE_URL; ?>/kategori/<?= $cat_row['slug']; ?>?paged=<?= $i; ?>"><?= $i; ?></a>
          <?php elseif ( $i === 4 && $page > 5 ) : ?>
            <span class="page-numbers dots">…</span>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ( $page < $total_pages ) : ?>
          <a class="next page-numbers" href="<?= SITE_URL; ?>/kategori/<?= $cat_row['slug']; ?>?paged=<?= $page + 1; ?>">Sonraki →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php else : ?>
    <div class="brutal-card" style="padding:3.5rem 2rem; text-align:center; color:var(--text-muted); background:var(--bg-card); border:1.5px dashed var(--border-color);">
      <p style="font-family:var(--font-serif); font-size:1.2rem; margin-bottom:1rem; color:var(--text-main);">
        Bu kategoride henüz yayınlanmış bir eser bulunmuyor.
      </p>
      <a href="<?= SITE_URL; ?>/" class="stamp-tag" style="text-decoration:none; font-size:0.85rem; padding:0.5rem 1.25rem;">
        ← Ana Sayfaya Dön
      </a>
    </div>
  <?php endif; ?>

</div>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
