<?php
/**
 * Pratfall Standalone - Ana Sayfa Şablonu
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

// İlk Yazı (Manşet) ve Kalan Yazılar
$lead_post = ( $page === 1 && ! empty( $posts ) ) ? $posts[0] : null;
$feed_posts = ( $page === 1 && count( $posts ) > 1 ) ? array_slice( $posts, 1 ) : $posts;

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

// SEO Bilgileri
$seo_data = [
    'title'       => Database::get_setting( 'site_slogan', 'Kusurlu Kalma Çabası' ),
    'description' => Database::get_setting( 'footer_desc' ),
    'url'         => SITE_URL,
    'type'        => 'website',
];

require PATH_VIEWS . '/layout/header.php';
?>

<div class="pratfall-container">

  <!-- 1. EDİTORYAL MANŞET & ASİMETRİK KOLAJ -->
  <?php if ( $page === 1 && $lead_post ) : ?>
  <section class="editorial-hero-section" aria-label="Öne Çıkan Seçkiler">
    <div class="editorial-hero-grid">
      
      <!-- Sol: Büyük Manşet Eser -->
      <div class="hero-lead-column">
        <article class="brutal-card hero-lead-card rot-hover-straight">
          <div class="hero-lead-thumb">
            <span class="tape-badge rot-n2">✦ MANŞET METİN</span>
            <a href="<?= SITE_URL; ?>/eser/<?= $lead_post['slug']; ?>">
              <?php if ( ! empty( $lead_post['featured_image'] ) ) : ?>
                <img src="<?= $lead_post['featured_image']; ?>" alt="<?= htmlspecialchars( $lead_post['title'], ENT_QUOTES, 'UTF-8' ); ?>" loading="eager" />
              <?php else : ?>
                <div style="width:100%; height:100%; min-height:380px; background:#292524; display:flex; align-items:center; justify-content:center; color:#f6f3eb; font-family:var(--font-serif); font-size:1.8rem; padding:2rem; text-align:center;">
                  <?= htmlspecialchars( $lead_post['title'], ENT_QUOTES, 'UTF-8' ); ?>
                </div>
              <?php endif; ?>
            </a>
          </div>

          <div class="hero-lead-body">
            <div class="post-card-meta">
              <span class="stamp-tag"><?= htmlspecialchars( $lead_post['category'], ENT_QUOTES, 'UTF-8' ); ?></span>
              <time><?= Helpers::format_date( $lead_post['published_at'] ); ?></time>
              <span>✦ <?= $lead_post['reading_time']; ?> dk okuma</span>
            </div>

            <h2 class="hero-lead-title">
              <a href="<?= SITE_URL; ?>/eser/<?= $lead_post['slug']; ?>">
                <?= htmlspecialchars( $lead_post['title'], ENT_QUOTES, 'UTF-8' ); ?>
              </a>
            </h2>

            <p class="hero-lead-excerpt">
              <?= htmlspecialchars( $lead_post['excerpt'], ENT_QUOTES, 'UTF-8' ); ?>
            </p>

            <div class="post-card-author">
              <a href="<?= SITE_URL; ?>/yazar/<?= $lead_post['author_slug']; ?>" class="post-card-author-img">
                <?= Helpers::author_avatar( [ 'photo' => $lead_post['author_photo'], 'name' => $lead_post['author_name'] ], 36 ); ?>
              </a>
              <div>
                <a href="<?= SITE_URL; ?>/yazar/<?= $lead_post['author_slug']; ?>" class="post-card-author-name">
                  <?= htmlspecialchars( $lead_post['author_name'], ENT_QUOTES, 'UTF-8' ); ?>
                </a>
                <?php if ( ! empty( $lead_post['author_title'] ) ) : ?>
                  <div style="font-size:0.75rem; color:var(--text-muted); font-family:var(--font-mono);">
                    <?= htmlspecialchars( $lead_post['author_title'], ENT_QUOTES, 'UTF-8' ); ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </article>
      </div>

      <!-- Sağ: Küratörün Notu & İkincil Seçki -->
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

        <div class="brutal-card" style="padding:1.5rem; background:var(--bg-card); border-left:4px solid var(--accent-secondary);">
          <div style="font-family:var(--font-mono); font-size:0.75rem; font-weight:700; color:var(--accent-secondary); margin-bottom:0.35rem;">
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

  <!-- 2. KİMLER YAZMIŞ? (YAZARLAR MASASI - YOKLAMA LİSTESİ) -->
  <section class="author-spotlight-section" id="yazarlar" aria-label="Kimler Yazmış?">
    <div class="author-spotlight-header">
      <div>
        <span class="tape-badge rot-n1" style="font-size:0.75rem; margin-bottom:0.35rem; display:inline-block;">
          <?= htmlspecialchars( Database::get_setting( 'authors_badge', '✦ KİMLER YAZMIŞ?' ), ENT_QUOTES, 'UTF-8' ); ?>
        </span>
        <h2 class="author-spotlight-title" style="margin-top:0.25rem;">
          <?= htmlspecialchars( Database::get_setting( 'authors_title', 'Yazarlar Masası' ), ENT_QUOTES, 'UTF-8' ); ?>
        </h2>
        <p style="margin:0.25rem 0 0 0; font-family:var(--font-mono); font-size:0.85rem; color:var(--text-muted);">
          <?= htmlspecialchars( Database::get_setting( 'authors_desc', 'Burası sadece yoklama listesi.' ), ENT_QUOTES, 'UTF-8' ); ?>
        </p>
      </div>
    </div>

    <div class="author-spotlight-grid">
      <?php foreach ( $authors as $author ) : ?>
        <a href="<?= SITE_URL; ?>/yazar/<?= $author['slug']; ?>" class="author-pill-card">
          <div class="author-pill-avatar">
            <?= Helpers::author_avatar( $author, 44 ); ?>
          </div>
          <div class="author-pill-info">
            <span class="author-pill-name"><?= htmlspecialchars( $author['name'], ENT_QUOTES, 'UTF-8' ); ?></span>
            <?php if ( ! empty( $author['title'] ) ) : ?>
              <span class="author-pill-role"><?= htmlspecialchars( $author['title'], ENT_QUOTES, 'UTF-8' ); ?></span>
            <?php endif; ?>
            <span class="author-pill-count">✦ <?= (int) $author['post_count']; ?> Eser</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <hr class="newspaper-divider" />

  <!-- 3. DERGİLERİMİZİN TÜM SAYILARI (YATAY VİTRİN ŞERİDİ) -->
  <section class="pratfall-dergiler-section" id="dergiler" aria-label="Dergilerimiz">
    <div class="pratfall-dergiler-header">
      <div class="dergiler-header-left">
        <h2 class="pratfall-dergiler-title">Dergilerimiz</h2>
        <span class="tape-badge tape-peach rot-p1">TÜM BASILI SAYILAR</span>
      </div>

      <div class="dergiler-nav-controls">
        <button type="button" class="dergi-nav-btn dergi-nav-prev" title="Önceki Sayılar" aria-label="Önceki">←</button>
        <button type="button" class="dergi-nav-btn dergi-nav-next" title="Sonraki Sayılar" aria-label="Sonraki">→</button>
      </div>
    </div>

    <div class="pratfall-dergiler-viewport">
      <div class="pratfall-dergiler-track">
        <?php foreach ( $magazines as $mag ) : ?>
          <div class="dergi-card-item">
            <div class="dergi-card-poster">
              <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener">
                <?php if ( ! empty( $mag['cover_image'] ) ) : ?>
                  <img src="<?= $mag['cover_image']; ?>" alt="<?= htmlspecialchars( $mag['title'], ENT_QUOTES, 'UTF-8' ); ?>" class="dergi-poster-img" loading="lazy" />
                <?php else : ?>
                  <div style="width:100%; height:100%; background:#292524; color:#f6f3eb; display:flex; align-items:center; justify-content:center; padding:1rem; text-align:center;">
                    <?= htmlspecialchars( $mag['issue_no'], ENT_QUOTES, 'UTF-8' ); ?>
                  </div>
                <?php endif; ?>
              </a>
              <span class="tape-badge dergi-tape-tag rot-n1"><?= htmlspecialchars( $mag['issue_no'], ENT_QUOTES, 'UTF-8' ); ?></span>
            </div>

            <div class="dergi-card-details">
              <h3 class="dergi-card-title">
                <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener">
                  <?= htmlspecialchars( $mag['title'], ENT_QUOTES, 'UTF-8' ); ?>
                </a>
              </h3>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <span class="dergi-period"><?= htmlspecialchars( $mag['period'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></span>
                <span class="dergi-price"><?= htmlspecialchars( $mag['price'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></span>
              </div>
              <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-buy-btn">
                Sayıyı Satın Al ↗
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <hr class="newspaper-divider" />

  <!-- 4. ASİMETRİK ESER & YAZI AKIŞI -->
  <section class="asymmetric-feed-section" id="eserler" aria-label="Yazılar">
    <div class="section-headline">
      <h2 class="section-headline-title">
        <span><?= htmlspecialchars( Database::get_setting( 'feed_title', 'Son Eserler & Yazılar' ), ENT_QUOTES, 'UTF-8' ); ?></span>
        <span class="tape-badge rot-p1" style="font-size:0.75rem;">
          <?= htmlspecialchars( Database::get_setting( 'feed_badge', 'YENİ METİNLER' ), ENT_QUOTES, 'UTF-8' ); ?>
        </span>
      </h2>
    </div>

    <div class="asymmetric-feed-grid">
      <?php foreach ( $feed_posts as $post ) : ?>
        <article class="brutal-card post-card rot-hover-straight">
          <div class="post-card-thumb">
            <span class="tape-badge post-card-tag rot-n1"><?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?></span>
            <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>">
              <?php if ( ! empty( $post['featured_image'] ) ) : ?>
                <img src="<?= $post['featured_image']; ?>" alt="<?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>" loading="lazy" />
              <?php else : ?>
                <div style="width:100%; height:100%; background:#292524; display:flex; align-items:center; justify-content:center; color:#f6f3eb; padding:1.5rem; text-align:center;">
                  <?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>
                </div>
              <?php endif; ?>
            </a>
          </div>

          <div class="post-card-meta">
            <time><?= Helpers::format_date( $post['published_at'] ); ?></time>
            <span>✦ <?= $post['reading_time']; ?> dk okuma</span>
          </div>

          <h3 class="post-card-title">
            <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>">
              <?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>
            </a>
          </h3>

          <div class="post-card-excerpt">
            <p><?= htmlspecialchars( $post['excerpt'], ENT_QUOTES, 'UTF-8' ); ?></p>
          </div>

          <div class="post-card-author">
            <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" class="post-card-author-img">
              <?= Helpers::author_avatar( [ 'photo' => $post['author_photo'], 'name' => $post['author_name'] ], 28 ); ?>
            </a>
            <div>
              <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" class="post-card-author-name">
                <?= htmlspecialchars( $post['author_name'], ENT_QUOTES, 'UTF-8' ); ?>
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- 100-200+ Eser İçin Temaya Uygun Sayfalama -->
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

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
