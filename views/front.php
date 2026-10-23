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
$per_page = 6;
$offset = ( $page - 1 ) * $per_page;

// Toplam Yazı Sayısı
$total_posts = (int) $db->query( "SELECT COUNT(*) FROM posts WHERE status = 'published'" )->fetchColumn();
$total_pages = ceil( $total_posts / $per_page );

// Yazıları Çek (Yazar Bilgisiyle Birlikte)
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
$posts = $stmt->fetchAll();

// Manşet Eseri, İkincil Seçki ve Kalan Yazılar
$lead_post = ( $page === 1 && ! empty( $posts ) ) ? $posts[0] : null;
$secondary_post = ( $page === 1 && count( $posts ) > 1 ) ? $posts[1] : null;
$feed_posts = ( $page === 1 && count( $posts ) > 2 ) ? array_slice( $posts, 2 ) : ( $page === 1 ? [] : $posts );

// Eğer feed boşsa veya az yazı varsa demo yazıları göster
if ( empty( $feed_posts ) && ! empty( $posts ) ) {
    $feed_posts = $posts;
}

// Yazarları Çek (Kimler Yazmış?)
$authors = $db->query( "
    SELECT a.*, COUNT(p.id) AS post_count 
    FROM authors a
    LEFT JOIN posts p ON a.id = p.author_id AND p.status = 'published'
    GROUP BY a.id
    ORDER BY post_count DESC, a.id ASC
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

// SEO Bilgileri
$seo_data = [
    'title'       => Database::get_setting( 'site_slogan', 'Kusurlu Kalma Çabası' ),
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
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span class="dergi-period"><?= htmlspecialchars( $mag['period'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></span>
                <span class="dergi-price"><?= htmlspecialchars( $mag['price'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></span>
              </div>
              <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-buy-btn">
                Satın Al ↗
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
                <span class="stamp-tag"><?= htmlspecialchars( $lead_post['category'] ?? 'DENEME' ); ?></span>
                <time><?= Helpers::format_date( $lead_post['published_at'] ); ?></time>
                <span>✦ <?= (int) ( $lead_post['reading_time'] ?? 5 ); ?> dk derin okuma</span>
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
                      <?= htmlspecialchars( $lead_post['author_title'] ?? 'Mecmua Yazarı' ); ?>
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

        <!-- Yan Kolaj -->
        <aside class="hero-side-column">
          
          <div class="brutal-card curator-note-card rot-p1 rot-hover-straight">
            <div class="curator-title">
              <span>✦ KÜRATÖRÜN MASASI</span>
            </div>
            <blockquote class="curator-quote">
              "Bir yazıdaki en sahici cümle, yazarın silmekten korktuğu ama sonunda sayfada bıraktığı o kusurlu cümledir."
            </blockquote>
            <div class="curator-sign">
              — Sayı #18 Yayın Kurulu Notu
            </div>
          </div>

          <?php if ( $secondary_post ) : ?>
          <div class="brutal-card secondary-feature-card rot-n05 rot-hover-straight">
            <div class="secondary-feature-thumb">
              <a href="<?= SITE_URL; ?>/eser/<?= $secondary_post['slug']; ?>">
                <img src="<?= ! empty( $secondary_post['featured_image'] ) ? htmlspecialchars( $secondary_post['featured_image'] ) : 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=300&q=80'; ?>" alt="<?= htmlspecialchars( $secondary_post['title'] ); ?>" />
              </a>
            </div>
            <div>
              <span class="stamp-tag" style="margin-bottom:0.35rem; font-size:0.65rem;">
                <?= htmlspecialchars( $secondary_post['category'] ); ?>
              </span>
              <h4 class="secondary-feature-title">
                <a href="<?= SITE_URL; ?>/eser/<?= $secondary_post['slug']; ?>">
                  <?= htmlspecialchars( $secondary_post['title'] ); ?>
                </a>
              </h4>
              <div style="font-family:var(--font-mono); font-size:0.72rem; color:var(--text-muted);">
                <?= htmlspecialchars( $secondary_post['author_name'] ); ?> · <?= (int) $secondary_post['reading_time']; ?> dk
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="brutal-card" style="padding:1.25rem; background:var(--bg-card); border-left:4px solid var(--accent-secondary);">
            <div style="font-family:var(--font-mono); font-size:0.75rem; font-weight:700; color:var(--accent-secondary); margin-bottom:0.25rem;">
              ✦ MATBAADAN TAZE ÇIKANLAR
            </div>
            <p style="font-size:0.85rem; margin:0; line-height:1.5; color:var(--text-muted);">
              Dergimizin yeni sayısı ve yazarlarımızın bağımsız fanzinleri şimdi vitrinde listeleniyor.
            </p>
          </div>

        </aside>

      </div>
    </section>
    <?php endif; ?>

    <!-- 5. YAZARLAR MASASI (YOKLAMA LİSTESİ - KİMLER YAZMIŞ?) -->
    <section class="author-spotlight-section" id="yazarlar">
      <div class="author-spotlight-header">
        <div>
          <span class="tape-badge rot-n1" style="font-size:0.75rem; margin-bottom:0.35rem; display:inline-block;">
            ✦ KİMLER YAZMIŞ?
          </span>
          <h2 class="author-spotlight-title" style="margin-top:0.25rem;">
            Yazarlar Masası
          </h2>
          <p style="margin:0.25rem 0 0 0; font-family:var(--font-mono); font-size:0.85rem; color:var(--text-muted);">
            Burası sadece yoklama listesi.
          </p>
        </div>
      </div>

      <div class="author-spotlight-grid">
        <?php foreach ( $authors as $author ) : ?>
        <a href="<?= SITE_URL; ?>/yazar/<?= $author['slug']; ?>" class="author-pill-card" style="text-decoration:none; color:inherit;">
          <div class="author-pill-avatar">
            <?= Helpers::author_avatar( $author, 52 ); ?>
          </div>
          <div class="author-pill-info">
            <span class="author-pill-name"><?= htmlspecialchars( $author['name'] ); ?></span>
            <span class="author-pill-role"><?= htmlspecialchars( ! empty($author['title']) ? $author['title'] : 'Yazar & Katkıda Bulunan' ); ?></span>
            <span class="author-pill-count">✦ <?= (int) $author['post_count']; ?> Eser</span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>

    <hr class="newspaper-divider" />

    <!-- 6. ASİMETRİK ESER & YAZI AKIŞI -->
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
            <span class="tape-badge post-card-tag rot-n1"><?= htmlspecialchars( $post['category'] ?? 'YAZI' ); ?></span>
            <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>">
              <img src="<?= ! empty( $post['featured_image'] ) ? htmlspecialchars( $post['featured_image'] ) : 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?auto=format&fit=crop&w=600&q=80'; ?>" alt="<?= htmlspecialchars( $post['title'] ); ?>" loading="lazy" />
            </a>
          </div>
          <div class="post-card-meta">
            <time><?= Helpers::format_date( $post['published_at'] ); ?></time>
            <span>✦ <?= (int) ( $post['reading_time'] ?? 5 ); ?> dk okuma</span>
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
  <section class="pratfall-dergiler-section" id="kitaplik-vitrin" aria-label="Pratfall Kitaplığı Yatay Vitrini" style="margin-top:2rem; margin-bottom:3rem; background:var(--bg-card); border-top:2px solid var(--border-color); border-bottom:2px solid var(--border-color); padding:2.5rem 0;">
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

        <div style="display:flex; align-items:center; gap:1.25rem;">
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
          <div class="dergi-card">
            <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-cover-wrap" style="height:280px;" title="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?> - İncele & Satın Al">
              <?php if ( ! empty( $b['publish_year'] ) ) : ?>
                <span class="dergi-issue-badge" style="background:#0f172a;"><?= htmlspecialchars( $b['publish_year'], ENT_QUOTES, 'UTF-8' ); ?></span>
              <?php endif; ?>
              <?php if ( ! empty( $b['cover_image'] ) ) : ?>
                <img src="<?= htmlspecialchars( $b['cover_image'] ); ?>" alt="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>" class="dergi-cover-img" style="height:100%; object-fit:cover;" />
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
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                <span class="dergi-period" style="color:var(--accent-primary); font-weight:700;"><?= htmlspecialchars( $b['author'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></span>
                <span class="dergi-price"><?= htmlspecialchars( $b['price'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></span>
              </div>
              <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-buy-btn">
                Satın Al ↗
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
