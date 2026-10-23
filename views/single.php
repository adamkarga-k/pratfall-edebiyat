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

$is_single = true;

// SEO Bilgileri
$seo_data = [
    'title'         => $post['title'],
    'description'   => $post['excerpt'],
    'url'           => SITE_URL . '/eser/' . $post['slug'],
    'image'         => $post['featured_image'],
    'type'          => 'article',
    'published_at'  => date( 'c', strtotime( $post['published_at'] ) ),
    'updated_at'    => date( 'c', strtotime( $post['updated_at'] ?? $post['published_at'] ) ),
    'author_name'   => $post['author_name'] ?? 'Pratfall Yazarı',
    'author_url'    => SITE_URL . '/yazar/' . ( $post['author_slug'] ?? '' ),
];

require PATH_VIEWS . '/layout/header.php';
?>

<article class="single-article">
  <div class="pratfall-container-narrow">
    
    <!-- Başlık Alanı -->
    <header class="single-article-header">
      <div class="single-article-badge-row">
        <span class="stamp-tag"><?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?></span>
        <span class="tape-badge rot-n1">✦ <?= $post['reading_time']; ?> DAKİKA OKUMA</span>
      </div>

      <h1 class="single-article-title">
        <?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>
      </h1>

      <?php if ( ! empty( $post['excerpt'] ) ) : ?>
        <div class="single-article-subtitle">
          <?= htmlspecialchars( $post['excerpt'], ENT_QUOTES, 'UTF-8' ); ?>
        </div>
      <?php endif; ?>

      <!-- Yazar Künyesi -->
      <div class="single-article-byline">
        <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" class="single-byline-avatar">
          <?= Helpers::author_avatar( [ 'photo' => $post['author_photo'], 'name' => $post['author_name'] ], 44 ); ?>
        </a>
        <div class="single-byline-info">
          <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" class="single-byline-author">
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
    <div class="single-content">
      <?= $post['content']; ?>
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

    <!-- Yazı Sonu Yazar Masası Kartı -->
    <div class="article-author-box rot-hover-straight" style="margin-top:4rem;">
      <div class="article-author-box-avatar">
        <?= Helpers::author_avatar( [ 'photo' => $post['author_photo'], 'name' => $post['author_name'] ], 90 ); ?>
      </div>
      <div>
        <span class="tape-badge rot-p1" style="font-size:0.65rem; margin-bottom:0.4rem;">
          ✦ YAZARIN MASASI
        </span>
        <h3 class="article-author-box-name">
          <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>">
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
        <a href="<?= SITE_URL; ?>/yazar/<?= $post['author_slug']; ?>" class="stamp-tag">
          <?= htmlspecialchars( $post['author_name'], ENT_QUOTES, 'UTF-8' ); ?> Adlı Yazarın Tüm Eserleri ↗
        </a>
      </div>
    </div>

  </div><!-- .pratfall-container-narrow -->
</article>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
