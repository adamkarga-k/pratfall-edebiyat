<?php
/**
 * Pratfall Standalone - Eser Okuma Sayfası (Single Post)
 */

$db = Database::get();

$stmt = $db->prepare( "
    SELECT p.*, a.name AS author_name, a.slug AS author_slug, a.photo AS author_photo, a.title AS author_title, a.bio AS author_bio
    FROM posts p
    LEFT JOIN authors a ON p.author_id = a.id
    WHERE p.slug = ? AND p.status = 'published'
    LIMIT 1
" );
$stmt->execute( [ $slug ] );
$post = $stmt->fetch();

if ( ! $post ) {
    http_response_code( 404 );
    require PATH_VIEWS . '/404.php';
    exit;
}

// Okunma Sayacını Artır (Aynı oturumda tekrar saymayı engelle)
$viewed_key = 'viewed_post_' . $post['id'];
if ( empty( $_SESSION[$viewed_key] ) ) {
    $_SESSION[$viewed_key] = true;
    try {
        $db->prepare( "UPDATE posts SET views_count = COALESCE(views_count, 0) + 1 WHERE id = ?" )->execute( [ $post['id'] ] );
        $post['views_count'] = (int) ( $post['views_count'] ?? 0 ) + 1;
    } catch ( Throwable $e ) {}
}

$is_single = true;
$is_poem = ( mb_stripos( $post['category'] ?? '', 'şiir' ) !== false || mb_stripos( $post['category'] ?? '', 'siir' ) !== false );

// SEO Bilgileri
$seo_data = [
    'title'         => $post['title'],
    'description'   => $post['excerpt'],
    'url'           => SITE_URL . '/eser/' . $post['slug'],
    'image'         => $post['featured_image'],
    'type'          => 'article',
    'published_at'  => date( 'c', strtotime( $post['published_at'] ) ),
    'updated_at'    => date( 'c', strtotime( $post['updated_at'] ?? $post['published_at'] ) ),
    'author_name'   => $post['author_name'] ?? ( Helpers::is_poet( $post ) ? 'Pratfall Şairi' : 'Pratfall Yazarı' ),
    'author_url'    => Helpers::author_url( $post ),
    'author_title'  => $post['author_title'] ?? ( Helpers::is_poet( $post ) ? 'Şair' : 'Yazar' ),
    'author_photo'  => $post['author_photo'] ?? '',
    'category'      => $post['category'] ?? 'Edebiyat',
    'tags'          => $post['tags'] ?? '',
];

require PATH_VIEWS . '/layout/header.php';
?>

<!-- İnce Mürekkep Okuma İlerleme Çubuğu (Fixed Reading Progress Bar) -->
<div id="readingProgressBar" style="position:fixed; top:0; left:0; width:0%; height:3.5px; background:linear-gradient(90deg, var(--accent-primary, #8b1e0f), #d94e34); z-index:99999; transition:width 0.1s ease-out; box-shadow:0 1px 3px rgba(139,30,15,0.4);">
  <div style="position:absolute; right:0; top:-2px; width:7px; height:7px; background:#141416; border-radius:50%; box-shadow:0 0 5px rgba(0,0,0,0.5);"></div>
</div>

<article class="single-article <?= $is_poem ? 'is-poem' : ''; ?>">
  <div class="pratfall-container-narrow">
    
    <!-- Başlık Alanı -->
    <header class="single-article-header">
      <div class="single-article-badge-row">
        <a href="<?= SITE_URL; ?>/kategori/<?= Database::slugify( $post['category'] ?? 'deneme' ); ?>" class="stamp-tag" style="text-decoration:none; color:inherit;">
          <?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?>
        </a>
        <span class="tape-badge rot-n1">✦ <?= Helpers::literary_reading_rhythm( $post['reading_time'], $post['category'] ); ?></span>
      </div>

      <h1 class="single-article-title">
        <?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>
      </h1>

      <?php if ( ! empty( $post['excerpt'] ) ) : ?>
        <div class="single-article-subtitle">
          <?= htmlspecialchars( $post['excerpt'], ENT_QUOTES, 'UTF-8' ); ?>
        </div>
      <?php endif; ?>

      <!-- Yazar / Şair Künyesi -->
      <div class="single-article-byline">
        <a href="<?= Helpers::author_url( $post ); ?>" class="single-byline-avatar">
          <?= Helpers::author_avatar( [ 'photo' => $post['author_photo'], 'name' => $post['author_name'] ], 44 ); ?>
        </a>
        <div class="single-byline-info">
          <a href="<?= Helpers::author_url( $post ); ?>" class="single-byline-author">
            <?= htmlspecialchars( $post['author_name'], ENT_QUOTES, 'UTF-8' ); ?>
          </a>
          <div class="single-byline-date">
            <time><?= Helpers::format_date( $post['published_at'] ); ?></time>
            <?php if ( ! empty( $post['author_title'] ) ) : ?>
              <span> · <?= htmlspecialchars( $post['author_title'], ENT_QUOTES, 'UTF-8' ); ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </header>

  </div><!-- .pratfall-container-narrow -->

  <!-- Öne Çıkan Görsel -->
  <?php if ( ! empty( $post['featured_image'] ) ) : ?>
    <div class="pratfall-container">
      <div class="single-featured-image rot-hover-straight">
        <img src="<?= $post['featured_image']; ?>" alt="<?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>" />
      </div>
    </div>
  <?php endif; ?>

  <!-- Eser Gövde Metni -->
  <div class="pratfall-container-narrow">
    <div class="single-content <?= $is_poem ? 'is-poem no-dropcap' : ''; ?>">
      <?= $is_poem ? Helpers::format_poem_content( $post['content'] ) : $post['content']; ?>
    </div>

    <?php if ( ! empty( $post['tags'] ) ) : ?>
      <div class="single-tags-row" style="margin-top:2.5rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
        <span style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-muted); font-weight:700;">✦ ETİKETLER:</span>
        <?php foreach ( explode( ',', $post['tags'] ) as $tag ) : ?>
          <?php $tag = trim( $tag ); if ( empty( $tag ) ) continue; ?>
          <span class="stamp-tag" style="font-size:0.75rem;">#<?= htmlspecialchars( $tag, ENT_QUOTES, 'UTF-8' ); ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Tırtıklı Perfore Ayraç -->
    <div class="perforated-divider" style="margin: 3rem 0;">
      <span class="perforated-badge">✂ · ESERİN SONU · ✦</span>
    </div>

    <!-- Yazı Sonu Yazar / Şair Masası Kartı -->
    <?php $is_author_poet = Helpers::is_poet( $post ); ?>
    <div class="article-author-box rot-hover-straight">
      <div class="article-author-box-avatar">
        <?= Helpers::author_avatar( [ 'photo' => $post['author_photo'], 'name' => $post['author_name'] ], 90 ); ?>
      </div>
      <div>
        <span class="tape-badge rot-p1" style="font-size:0.65rem; margin-bottom:0.4rem;">
          ✦ <?= $is_author_poet ? 'ŞAİRİN MASASI' : 'YAZARIN MASASI'; ?>
        </span>
        <h3 class="article-author-box-name">
          <a href="<?= Helpers::author_url( $post ); ?>">
            <?= htmlspecialchars( $post['author_name'], ENT_QUOTES, 'UTF-8' ); ?>
          </a>
        </h3>
        <?php if ( ! empty( $post['author_title'] ) ) : ?>
          <div style="font-family:var(--font-mono); font-size:0.75rem; color:var(--accent-primary); font-weight:700; margin-bottom:0.5rem;">
            <?= htmlspecialchars( $post['author_title'], ENT_QUOTES, 'UTF-8' ); ?>
          </div>
        <?php endif; ?>
        <?php if ( ! empty( $post['author_bio'] ) ) : ?>
          <div class="article-author-box-bio">
            <p><?= htmlspecialchars( $post['author_bio'], ENT_QUOTES, 'UTF-8' ); ?></p>
          </div>
        <?php endif; ?>
        <a href="<?= Helpers::author_url( $post ); ?>" class="stamp-tag">
          Tüm Eserleri ↗
        </a>
      </div>
    </div>

  </div><!-- .pratfall-container-narrow -->
</article>

<script>
(function() {
  var bar = document.getElementById('readingProgressBar');
  if (!bar) return;

  function updateProgress() {
    var winScroll = document.body.scrollTop || document.documentElement.scrollTop;
    var height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    if (height > 0) {
      var scrolled = (winScroll / height) * 100;
      bar.style.width = scrolled + '%';
    }
  }

  window.addEventListener('scroll', updateProgress, { passive: true });
  window.addEventListener('resize', updateProgress, { passive: true });
})();
</script>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
