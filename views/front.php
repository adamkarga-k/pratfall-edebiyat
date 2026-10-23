<?php
/**
 * Pratfall Standalone - Ana Sayfa Şablonu
 * 
 * demo-preview.html tasarım mimarisiyle %100 birebir uyumlu.
 * Dinamik SQLite veritabanı entegrasyonu içerir.
 */

$db = Database::get();

// Sayfalama Parametresi
$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page = 9; // Her sayfada Son Eserler akışında tam 9 eser yer alır

// Manşet Eseri (Öne Çıkarılan veya En Son Eser - Yalnızca 1. Sayfada Gösterilir)
$lead_post = null;
$lead_id = 0;

if ( $page === 1 ) {
    // 1. Öncelik: Editörün manşete sabitlediği (is_featured = 1) eser
    $featured_stmt = $db->query( "
        SELECT p.*, a.name AS author_name, a.slug AS author_slug, a.photo AS author_photo, a.title AS author_title
        FROM posts p
        LEFT JOIN authors a ON p.author_id = a.id
        WHERE p.status = 'published' AND p.is_featured = 1
        ORDER BY p.published_at DESC
        LIMIT 1
    " );
    $lead_post = $featured_stmt->fetch();

    // 2. Öncelik: Eğer özel öne çıkarılan yoksa en son yayınlanan eser manşet olur
    if ( ! $lead_post ) {
        $latest_stmt = $db->query( "
            SELECT p.*, a.name AS author_name, a.slug AS author_slug, a.photo AS author_photo, a.title AS author_title
            FROM posts p
            LEFT JOIN authors a ON p.author_id = a.id
            WHERE p.status = 'published'
            ORDER BY p.published_at DESC
            LIMIT 1
        " );
        $lead_post = $latest_stmt->fetch();
    }

    if ( $lead_post ) {
        $lead_id = (int) $lead_post['id'];
    }
} else {
    // 2. ve sonraki sayfalarda manşet eseri tekrarlanmasın diye yine ana manşet ID'sini tespit et
    $feat_id_stmt = $db->query( "
        SELECT id FROM posts 
        WHERE status = 'published' AND is_featured = 1 
        ORDER BY published_at DESC LIMIT 1
    " );
    $lead_id = (int) ( $feat_id_stmt->fetchColumn() ?: 0 );
    if ( ! $lead_id ) {
        $latest_id_stmt = $db->query( "
            SELECT id FROM posts 
            WHERE status = 'published' 
            ORDER BY published_at DESC LIMIT 1
        " );
        $lead_id = (int) ( $latest_id_stmt->fetchColumn() ?: 0 );
    }
}

// Toplam Akış Yazısı Sayısı (Manşetteki eser hariç)
if ( $lead_id > 0 ) {
    $count_stmt = $db->prepare( "SELECT COUNT(*) FROM posts WHERE status = 'published' AND id != ?" );
    $count_stmt->execute( [ $lead_id ] );
    $total_feed_posts = (int) $count_stmt->fetchColumn();
} else {
    $total_feed_posts = (int) $db->query( "SELECT COUNT(*) FROM posts WHERE status = 'published'" )->fetchColumn();
}

$total_pages = max( 1, (int) ceil( $total_feed_posts / $per_page ) );
$offset = ( $page - 1 ) * $per_page;

// Akış Yazılarını Çek (Her sayfada tam 9 eser; 1. sayfada manşet ile birlikte toplam 10 eser)
if ( $lead_id > 0 ) {
    $stmt = $db->prepare( "
        SELECT p.*, a.name AS author_name, a.slug AS author_slug, a.photo AS author_photo, a.title AS author_title
        FROM posts p
        LEFT JOIN authors a ON p.author_id = a.id
        WHERE p.status = 'published' AND p.id != ?
        ORDER BY p.published_at DESC
        LIMIT ? OFFSET ?
    " );
    $stmt->bindValue( 1, $lead_id, PDO::PARAM_INT );
    $stmt->bindValue( 2, $per_page, PDO::PARAM_INT );
    $stmt->bindValue( 3, $offset, PDO::PARAM_INT );
    $stmt->execute();
    $feed_posts = $stmt->fetchAll();
} else {
    $stmt = $db->prepare( "
        SELECT p.*, a.name AS author_name, a.slug AS author_slug, a.photo AS author_photo, a.title AS author_title
        FROM posts p
        LEFT JOIN authors a ON p.author_id = a.id
        WHERE p.status = 'published'
        ORDER BY p.published_at DESC
        LIMIT ? OFFSET ?
    " );
    $stmt->bindValue( 1, $per_page, PDO::PARAM_INT );
    $stmt->bindValue( 2, $offset, PDO::PARAM_INT );
    $stmt->execute();
    $feed_posts = $stmt->fetchAll();
}

$insta_dergi_url = Database::get_setting( 'site_instagram_url', 'https://www.instagram.com/pratfalldergi/' );
$insta_kitap_url = Database::get_setting( 'site_instagram_kitap_url', 'https://www.instagram.com/pratfallkitap/' );

// Ana Sayfa: Rastgele 4 Yazar (Her sayfa yenilendiğinde başka 4 yazar gelir)
$authors = $db->query( "
    SELECT a.*, COUNT(p.id) AS post_count 
    FROM authors a
    LEFT JOIN posts p ON a.id = p.author_id AND p.status = 'published'
    GROUP BY a.id
    ORDER BY RANDOM()
    LIMIT 4
" )->fetchAll();

// Dergileri Çek
$magazines = $db->query( "SELECT * FROM magazines ORDER BY order_num ASC, id DESC" )->fetchAll();

// Pratfall Kitaplığı (Yayınevi Kitapları)
$books = $db->query( "SELECT * FROM books ORDER BY order_num ASC, id DESC" )->fetchAll();

// Eğer dergi eklenmemişse şık demo dergileri oluştur
if ( empty( $magazines ) ) {
    $magazines = [
        [
            'issue_no'    => 'Sayı #18',
            'title'       => 'Kusursuzluğun İflası',
            'period'      => 'Güz / Kış 2026',
            'price'       => '120 ₺',
            'cover_image' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=500&q=80',
            'buy_url'     => '#'
        ],
        [
            'issue_no'    => 'Sayı #17',
            'title'       => 'Çatlaklardan Sızan Şiir',
            'period'      => 'İlkbahar / Yaz 2026',
            'price'       => '110 ₺',
            'cover_image' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=500&q=80',
            'buy_url'     => '#'
        ],
        [
            'issue_no'    => 'Sayı #16',
            'title'       => 'Daktilo Sesleri & Sis',
            'period'      => 'Kış 2025',
            'price'       => '95 ₺',
            'cover_image' => 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?auto=format&fit=crop&w=500&q=80',
            'buy_url'     => '#'
        ]
    ];
}

// SEO Bilgileri (Önce Dergi Adı, Sonra Slogan)
$seo_data = [
    'is_home'     => true,
    'title'       => '',
    'description' => Database::get_setting( 'footer_desc' ),
    'url'         => SITE_URL,
    'type'        => 'website',
];

require PATH_VIEWS . '/layout/header.php';
?>

  <!-- 3. DERGİLERİMİZ YATAY VİTRİN ŞERİDİ (Etkileşimli Tut-Sürükle) -->
  <section class="pratfall-dergiler-section" id="dergiler" aria-label="Dergilerimiz Yatay Vitrini">
    <div class="pratfall-container">
      <div class="pratfall-dergiler-header">
        <div class="dergiler-header-left">
          <h2 class="pratfall-dergiler-title">
            Dergilerimiz
          </h2>
          <span class="tape-badge tape-peach rot-p1">
            TÜM BASILI SAYILAR
          </span>
        </div>

        <div class="dergiler-header-right">
          <div class="dergiler-nav-controls">
            <button type="button" class="dergi-nav-btn dergi-nav-prev" title="Önceki Sayılar" aria-label="Önceki Sayılar">
              ←
            </button>
            <button type="button" class="dergi-nav-btn dergi-nav-next" title="Sonraki Sayılar" aria-label="Sonraki Sayılar">
              →
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Yatay Kaydırma Alanı (Drag-to-Scroll) -->
    <div class="pratfall-dergiler-viewport">
      <div class="pratfall-dergiler-track">
        <?php foreach ( $magazines as $mag ) : ?>
          <div class="dergi-card">
            <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-cover-wrap">
              <span class="dergi-issue-badge"><?= htmlspecialchars( $mag['issue_no'], ENT_QUOTES, 'UTF-8' ); ?></span>
              <img src="<?= htmlspecialchars( $mag['cover_image'] ); ?>" alt="<?= htmlspecialchars( $mag['title'], ENT_QUOTES, 'UTF-8' ); ?>" class="dergi-cover-img" />
            </a>
            <div class="dergi-info">
              <h3 class="dergi-title" title="<?= htmlspecialchars( $mag['title'], ENT_QUOTES, 'UTF-8' ); ?>">
                <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener">
                  <?= htmlspecialchars( $mag['title'], ENT_QUOTES, 'UTF-8' ); ?>
                </a>
              </h3>
              <?php if ( ! empty( $mag['period'] ) ) : ?>
                <div style="margin-bottom:0.4rem;">
                  <span class="dergi-period"><?= htmlspecialchars( $mag['period'], ENT_QUOTES, 'UTF-8' ); ?></span>
                </div>
              <?php endif; ?>
              <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-buy-btn">
                İncele ↗
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="pratfall-container">

    <!-- 4. EDİTORYAL ASİMETRİK MANŞET & KOLAJ -->
    <?php if ( $page === 1 && $lead_post ) : ?>
    <section class="editorial-hero-section">
      <div class="editorial-hero-grid">
        
        <!-- Manşet -->
        <div class="hero-main-column">
          <article class="brutal-card lead-card rot-hover-straight">
            <div class="lead-image-wrap">
              <span class="tape-badge lead-tape-badge rot-n1">
                ✦ EDİTORYAL MANŞET
              </span>
              <a href="<?= SITE_URL; ?>/eser/<?= $lead_post['slug']; ?>">
                <img src="<?= ! empty( $lead_post['featured_image'] ) ? htmlspecialchars( $lead_post['featured_image'] ) : 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=1200&q=80'; ?>" alt="<?= htmlspecialchars( $lead_post['title'], ENT_QUOTES, 'UTF-8' ); ?>" />
              </a>
            </div>
            <div class="lead-content">
              <div class="lead-meta">
                <a href="<?= SITE_URL; ?>/kategori/<?= Database::slugify( $lead_post['category'] ?? 'deneme' ); ?>" class="stamp-tag" style="text-decoration:none; color:inherit;">
                  <?= htmlspecialchars( $lead_post['category'] ?? 'DENEME' ); ?>
                </a>
                <time><?= Helpers::format_date( $lead_post['published_at'] ); ?></time>
              </div>
              <h2 class="lead-title">
                <a href="<?= SITE_URL; ?>/eser/<?= $lead_post['slug']; ?>">
                  <?= htmlspecialchars( $lead_post['title'], ENT_QUOTES, 'UTF-8' ); ?>
                </a>
              </h2>
              <div class="lead-excerpt">
                <p><?= htmlspecialchars( $lead_post['excerpt'] ); ?></p>
              </div>
              <div class="lead-footer">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                  <a href="<?= SITE_URL; ?>/yazar/<?= $lead_post['author_slug']; ?>" class="post-card-author-img">
                    <?= Helpers::author_avatar( [ 'photo' => $lead_post['author_photo'], 'name' => $lead_post['author_name'] ], 36 ); ?>
                  </a>
                  <div>
                    <div style="font-weight:700;">
                      <a href="<?= SITE_URL; ?>/yazar/<?= $lead_post['author_slug']; ?>" style="color:inherit; text-decoration:none;">
                        <?= htmlspecialchars( $lead_post['author_name'] ); ?>
                      </a>
                    </div>
                    <div style="font-size:0.7rem; color:var(--text-muted);">
                      <?= htmlspecialchars( $lead_post['author_title'] ?? 'Yazar' ); ?>
                    </div>
                  </div>
                </div>
                <a href="<?= SITE_URL; ?>/yazar/<?= $lead_post['author_slug']; ?>" class="stamp-tag" style="background:var(--accent-primary); color:#fff; cursor:pointer; text-decoration:none;">
                  Yazar Masası ↗
                </a>
              </div>
            </div>
          </article>
        </div>

        <!-- Yan Kolaj: Üstte Bizi Takip Edin, Altta Yazarlar Masası -->
        <aside class="hero-side-column">
          
          <!-- 1. Bizi Takip Edin (Instagram Hesapları Bloğu) -->
          <div class="brutal-card rot-n05 rot-hover-straight" style="padding:1.25rem; background:var(--bg-card);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.9rem; border-bottom:1.5px dashed var(--border-color); padding-bottom:0.6rem;">
              <span class="stamp-tag" style="font-size:0.68rem; background:var(--text-main); color:var(--bg-card); font-weight:700;">
                ✦ BİZİ TAKİP EDİN
              </span>
              <span style="font-family:var(--font-mono); font-size:0.72rem; color:var(--text-muted); font-weight:600; letter-spacing:0.04em;">
                INSTAGRAM
              </span>
            </div>

            <div style="display:flex; flex-direction:column; gap:0.75rem;">
              
              <!-- Pratfall Dergi -->
              <a href="<?= htmlspecialchars( $insta_dergi_url, ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="insta-profile-card" style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem 0.85rem; background:var(--bg-surface); border:1.5px solid var(--border-color); text-decoration:none; color:var(--text-main); transition:transform 0.15s ease, box-shadow 0.15s ease; box-shadow:2px 2px 0 var(--border-color);">
                <div style="display:flex; align-items:center; gap:0.75rem; min-width:0;">
                  <div style="width:40px; height:40px; border-radius:50%; background:linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; box-shadow:0 2px 5px rgba(0,0,0,0.15);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                  </div>
                  <div style="min-width:0;">
                    <div style="font-family:var(--font-serif); font-size:1.05rem; font-weight:700; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Pratfall Dergi</div>
                    <div style="font-family:var(--font-mono); font-size:0.72rem; color:var(--text-muted);">@pratfalldergi</div>
                  </div>
                </div>
                <span class="stamp-tag" style="font-size:0.65rem; padding:0.22rem 0.55rem; background:var(--bg-tape); color:inherit; border:1px solid var(--border-color); flex-shrink:0;">
                  Takip Et ↗
                </span>
              </a>

              <!-- Pratfall Kitap -->
              <a href="<?= htmlspecialchars( $insta_kitap_url, ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="insta-profile-card" style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem 0.85rem; background:var(--bg-surface); border:1.5px solid var(--border-color); text-decoration:none; color:var(--text-main); transition:transform 0.15s ease, box-shadow 0.15s ease; box-shadow:2px 2px 0 var(--border-color);">
                <div style="display:flex; align-items:center; gap:0.75rem; min-width:0;">
                  <div style="width:40px; height:40px; border-radius:50%; background:linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; box-shadow:0 2px 5px rgba(0,0,0,0.15);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                  </div>
                  <div style="min-width:0;">
                    <div style="font-family:var(--font-serif); font-size:1.05rem; font-weight:700; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Pratfall Kitap</div>
                    <div style="font-family:var(--font-mono); font-size:0.72rem; color:var(--text-muted);">@pratfallkitap</div>
                  </div>
                </div>
                <span class="stamp-tag" style="font-size:0.65rem; padding:0.22rem 0.55rem; background:var(--bg-tape); color:inherit; border:1px solid var(--border-color); flex-shrink:0;">
                  Takip Et ↗
                </span>
              </a>

            </div>
          </div>

          <!-- 2. Yazarlar Masası (Yoklama Listesi: Rastgele 4 Yazar & Tüm Yazarlarımız Butonu) -->
          <div class="brutal-card hero-authors-card rot-p05 rot-hover-straight" id="yazarlar" style="padding:1.25rem; background:var(--bg-card);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem; border-bottom:1.5px dashed var(--border-color); padding-bottom:0.6rem; flex-wrap:wrap; gap:0.4rem;">
              <div>
                <span class="tape-badge tape-peach rot-n1" style="font-size:0.62rem; padding:0.15rem 0.5rem; margin-bottom:0.2rem; display:inline-block;">
                  ✦ YOKLAMA LİSTESİ
                </span>
                <h3 style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; margin:0; line-height:1.2;">
                  Yazarlar Masası
                </h3>
              </div>
              <a href="<?= SITE_URL; ?>/yazarlar" class="stamp-tag" style="background:var(--accent-primary); color:#fff; text-decoration:none; font-size:0.72rem; padding:0.28rem 0.65rem; font-weight:700; box-shadow:1.5px 1.5px 0 var(--border-color); transition:all 0.15s ease;" title="Tüm Yazarlarımız">
                Tüm Yazarlarımız ↗
              </a>
            </div>

            <div class="hero-authors-list" style="display:flex; flex-direction:column; gap:0.55rem;">
              <?php foreach ( $authors as $author ) : ?>
              <a href="<?= SITE_URL; ?>/yazar/<?= $author['slug']; ?>" class="hero-author-item" style="display:flex; align-items:center; justify-content:space-between; padding:0.5rem 0.75rem; background:var(--bg-surface); border:1.5px solid var(--border-color); text-decoration:none; color:var(--text-main); transition:all 0.15s ease; box-shadow:2px 2px 0 var(--border-color);">
                <div style="display:flex; align-items:center; gap:0.65rem; min-width:0;">
                  <div style="flex-shrink:0;">
                    <?= Helpers::author_avatar( $author, 36 ); ?>
                  </div>
                  <div style="min-width:0;">
                    <div style="font-family:var(--font-serif); font-size:0.92rem; font-weight:700; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      <?= htmlspecialchars( $author['name'] ); ?>
                    </div>
                    <div style="font-family:var(--font-mono); font-size:0.68rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      <?= htmlspecialchars( ! empty($author['title']) ? $author['title'] : 'Yazar' ); ?>
                    </div>
                  </div>
                </div>
                <span class="stamp-tag" style="font-size:0.62rem; padding:0.15rem 0.45rem; background:var(--bg-card); color:var(--text-muted); border:1px solid var(--border-color); flex-shrink:0;">
                  ✦ <?= (int) $author['post_count']; ?> Eser
                </span>
              </a>
              <?php endforeach; ?>
            </div>
          </div>

        </aside>

      </div>
    </section>
    <?php endif; ?>

    <!-- Tırtıklı Perfore Ayraç: Eserler Akışı -->
    <div class="perforated-divider" style="margin: 2.25rem 0 1.5rem 0;">
      <span class="perforated-badge">✂ · YENİ METİNLER & ESERLER · ✦</span>
    </div>

    <!-- 5. ASİMETRİK ESER & YAZI AKIŞI -->
    <section class="asymmetric-feed-section" id="eserler">
      <div class="section-headline">
        <h2 class="section-headline-title">
          <span>Son Eserler & Yazılar</span>
          <span class="tape-badge rot-p1" style="font-size:0.75rem;">YENİ METİNLER</span>
        </h2>
      </div>

      <div class="asymmetric-feed-grid">
        <?php foreach ( $feed_posts as $post ) : ?>
        <article class="brutal-card post-card rot-hover-straight">
          <div class="post-card-thumb">
            <a href="<?= SITE_URL; ?>/kategori/<?= Database::slugify( $post['category'] ?? 'yazi' ); ?>" class="tape-badge post-card-tag rot-n1" style="text-decoration:none; color:inherit;">
              <?= htmlspecialchars( $post['category'] ?? 'YAZI' ); ?>
            </a>
            <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>">
              <img src="<?= ! empty( $post['featured_image'] ) ? htmlspecialchars( $post['featured_image'] ) : 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?auto=format&fit=crop&w=600&q=80'; ?>" alt="<?= htmlspecialchars( $post['title'] ); ?>" loading="lazy" />
            </a>
          </div>
          <div class="post-card-meta">
            <time><?= Helpers::format_date( $post['published_at'] ); ?></time>
          </div>
          <h3 class="post-card-title">
            <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>"><?= htmlspecialchars( $post['title'] ); ?></a>
          </h3>
          <div class="post-card-excerpt">
            <p><?= htmlspecialchars( $post['excerpt'] ); ?></p>
          </div>
          <div class="post-card-author">
            <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" class="post-card-author-img">
              <?= Helpers::author_avatar( [ 'photo' => $post['author_photo'], 'name' => $post['author_name'] ], 36 ); ?>
            </a>
            <div>
              <span class="post-card-author-name">
                <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" style="text-decoration:none; color:inherit;">
                  <?= htmlspecialchars( $post['author_name'] ); ?>
                </a>
              </span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <!-- Sayfalama -->
      <?php if ( $total_pages > 1 ) : ?>
      <div class="pratfall-pagination">
        <?php if ( $page > 1 ) : ?>
          <a class="prev page-numbers" href="<?= SITE_URL; ?>/?paged=<?= $page - 1; ?>#eserler">← Önceki</a>
        <?php endif; ?>

        <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
          <?php if ( $i === $page ) : ?>
            <span class="page-numbers current"><?= $i; ?></span>
          <?php elseif ( $i <= 3 || $i > $total_pages - 2 || abs( $i - $page ) <= 1 ) : ?>
            <a class="page-numbers" href="<?= SITE_URL; ?>/?paged=<?= $i; ?>#eserler"><?= $i; ?></a>
          <?php elseif ( $i === 4 && $page > 5 ) : ?>
            <span class="page-numbers dots">…</span>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ( $page < $total_pages ) : ?>
          <a class="next page-numbers" href="<?= SITE_URL; ?>/?paged=<?= $page + 1; ?>#eserler">Sonraki →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </section>

  </div><!-- .pratfall-container -->

  <!-- 7. PRATFALL KİTAPLIĞI YATAY VİTRİN ŞERİDİ (Son Eserlerin Altında) -->
  <?php if ( ! empty( $books ) ) : ?>
  <div class="pratfall-container">
    <div class="perforated-divider" style="margin: 2.5rem 0 1rem 0;">
      <span class="perforated-badge">✂ · YAYINEVİMİZDEN ÇIKAN KİTAPLAR · ✦</span>
    </div>
  </div>
  <section class="pratfall-dergiler-section" id="kitaplik-vitrin" aria-label="Pratfall Kitaplığı Yatay Vitrini" style="margin-top:1rem; margin-bottom:3rem; background:var(--bg-card); border-top:2px solid var(--border-color); border-bottom:2px solid var(--border-color); padding:2.5rem 0;">
    <div class="pratfall-container">
      <div class="pratfall-dergiler-header">
        <div class="dergiler-header-left">
          <h2 class="pratfall-dergiler-title">
            Pratfall Kitaplığı
          </h2>
          <span class="tape-badge tape-peach rot-p1">
            YAYINEVİMİZDEN ÇIKANLAR
          </span>
        </div>

        <div class="dergiler-header-right">
          <a href="<?= SITE_URL; ?>/kitaplik" class="stamp-tag" style="font-size:0.75rem; text-decoration:none;">
            Tüm Kitaplığı Gör (<?= count( $books ); ?>) ↗
          </a>
          <div class="dergiler-nav-controls">
            <button type="button" class="dergi-nav-btn dergi-nav-prev" title="Önceki Kitaplar" aria-label="Önceki Kitaplar">
              ←
            </button>
            <button type="button" class="dergi-nav-btn dergi-nav-next" title="Sonraki Kitaplar" aria-label="Sonraki Kitaplar">
              →
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Yatay Kaydırma Alanı (Drag-to-Scroll) -->
    <div class="pratfall-dergiler-viewport">
      <div class="pratfall-dergiler-track">
        <?php foreach ( $books as $b ) : ?>
          <div class="dergi-card book-vitrin-card">
            <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-cover-wrap book-vitrin-cover" title="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?> - İncele & Satın Al">
              <?php if ( ! empty( $b['publish_year'] ) ) : ?>
                <span class="dergi-issue-badge" style="background:#0f172a;"><?= htmlspecialchars( $b['publish_year'], ENT_QUOTES, 'UTF-8' ); ?></span>
              <?php endif; ?>
              <?php if ( ! empty( $b['cover_image'] ) ) : ?>
                <img src="<?= htmlspecialchars( $b['cover_image'] ); ?>" alt="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>" class="dergi-cover-img" loading="lazy" />
              <?php else : ?>
                <div style="width:100%; height:100%; background:#1e293b; color:#f8fafc; display:flex; align-items:center; justify-content:center; padding:1rem; text-align:center; font-family:var(--font-serif);">
                  <?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>
                </div>
              <?php endif; ?>
            </a>
            <div class="dergi-info">
              <h3 class="dergi-title" title="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>">
                <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener">
                  <?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>
                </a>
              </h3>
              <?php if ( ! empty( $b['author'] ) ) : ?>
                <div style="margin-bottom:0.5rem;">
                  <span class="dergi-period" style="color:var(--accent-primary); font-weight:700;"><?= htmlspecialchars( $b['author'], ENT_QUOTES, 'UTF-8' ); ?></span>
                </div>
              <?php endif; ?>
              <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-buy-btn">
                İncele ↗
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
