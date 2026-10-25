<?php
/**
 * Pratfall Standalone - Yazar Masası & Eserleri (Author Profile & Works)
 * 
 * - Fotoğrafsız yazarlar için editoryal monogram pulu
 * - Gereksiz istatistikler ve sayaçlar kaldırılmış, saf edebiyat tasarımı
 */

$db = Database::get();

$stmt = $db->prepare( "SELECT * FROM authors WHERE slug = ? LIMIT 1" );
$stmt->execute( [ $slug ] );
$author = $stmt->fetch();

if ( ! $author ) {
    http_response_code( 404 );
    require PATH_VIEWS . '/404.php';
    exit;
}

// Yazarın Eserleri
$stmt = $db->prepare( "
    SELECT * FROM posts 
    WHERE author_id = ? AND status = 'published' 
    ORDER BY published_at DESC
" );
$stmt->execute( [ $author['id'] ] );
$posts = $stmt->fetchAll();

$has_photo = ! empty( $author['photo'] );
$has_quote = ! empty( $author['quote'] );
$has_bio   = ! empty( $author['bio'] );
$has_socials = ! empty( $author['twitter'] ) || ! empty( $author['instagram'] ) || ! empty( $author['website'] );

$is_poet = Helpers::is_poet( $author );
$canonical_url = Helpers::author_url( $author );

// 301 Kalıcı Yönlendirme (Kusursuz SEO & Doğru URL Uzantısı)
// Şair ise ve URL /yazar/ ile geldiyse /sair/ uzantısına; Şair değilse ve /sair/ ile geldiyse /yazar/ uzantısına yönlendir
$req_uri = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
if ( $is_poet && preg_match( '#/yazar/' . preg_quote( $author['slug'], '#' ) . '(/|$)#iu', $req_uri ) ) {
    header( 'Location: ' . $canonical_url, true, 301 );
    exit;
} elseif ( ! $is_poet && preg_match( '#/sair/' . preg_quote( $author['slug'], '#' ) . '(/|$)#iu', $req_uri ) ) {
    header( 'Location: ' . $canonical_url, true, 301 );
    exit;
}

// SEO Bilgileri
$seo_data = [
    'title'        => $author['name'] . ( $is_poet ? ' Şiirleri & Masası' : ' Masası' ),
    'description'  => $author['bio'] ?: ( $author['name'] . ' tarafından Pratfall sayfalarında kaleme alınan ' . ( $is_poet ? 'şiirler ve edebi eserler.' : 'eserler.' ) ),
    'url'          => $canonical_url,
    'image'        => $author['photo'] ?? '',
    'type'         => 'profile',
    'author_name'  => $author['name'],
    'author_title' => $author['title'] ?? ( $is_poet ? 'Şair' : 'Yazar' ),
];

require PATH_VIEWS . '/layout/header.php';
?>

<div class="pratfall-container">

  <!-- 1. ESNEK YAZAR MASASI BAŞLIĞI -->
  <header class="author-desk-hero <?= $has_photo ? 'has-custom-photo' : 'has-monogram'; ?>">
    
    <!-- Yazar Portresi veya Editoryal Monogram Pul -->
    <div class="author-polaroid-wrap">
      <?php if ( $has_photo ) : ?>
        <img src="<?= $author['photo']; ?>" alt="<?= htmlspecialchars( $author['name'], ENT_QUOTES, 'UTF-8' ); ?>" class="author-polaroid-img" />
      <?php else : ?>
        <div class="author-monogram-large">
          <?= Helpers::author_avatar( $author, 130 ); ?>
        </div>
      <?php endif; ?>
      <div class="author-polaroid-caption">
        ✦ <?= htmlspecialchars( $author['name'], ENT_QUOTES, 'UTF-8' ); ?>
      </div>
    </div>

    <!-- Yazar Bilgileri -->
    <div class="author-desk-info">
      <div class="author-desk-badge">
        <span class="tape-badge rot-p1">✦ <?= $is_poet ? 'ŞAİR MASASI & DİZELERİ' : 'YAZAR MASASI & ESERLERİ'; ?></span>
      </div>

      <h1 class="author-desk-name">
        <?= htmlspecialchars( $author['name'], ENT_QUOTES, 'UTF-8' ); ?>
      </h1>

      <?php if ( ! empty( $author['title'] ) ) : ?>
        <div class="author-desk-title">
          <?= htmlspecialchars( $author['title'], ENT_QUOTES, 'UTF-8' ); ?>
        </div>
      <?php endif; ?>

      <?php if ( $has_quote ) : ?>
        <blockquote class="author-desk-quote">
          "<?= htmlspecialchars( $author['quote'], ENT_QUOTES, 'UTF-8' ); ?>"
        </blockquote>
      <?php endif; ?>

      <?php if ( $has_bio ) : ?>
        <div class="author-desk-bio">
          <p><?= htmlspecialchars( $author['bio'], ENT_QUOTES, 'UTF-8' ); ?></p>
        </div>
      <?php endif; ?>

      <!-- Sosyal Medya Butonları -->
      <?php if ( $has_socials ) : ?>
        <div class="author-social-links">
          <?php if ( ! empty( $author['twitter'] ) ) : ?>
            <a href="https://twitter.com/<?= htmlspecialchars( $author['twitter'], ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="author-social-btn">
              𝕏 Twitter
            </a>
          <?php endif; ?>
          <?php if ( ! empty( $author['instagram'] ) ) : ?>
            <a href="https://instagram.com/<?= htmlspecialchars( $author['instagram'], ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="author-social-btn">
              📷 Instagram
            </a>
          <?php endif; ?>
          <?php if ( ! empty( $author['website'] ) ) : ?>
            <a href="<?= htmlspecialchars( $author['website'], ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="author-social-btn">
              🌐 Web Sitesi
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

  </header>

  <!-- Tırtıklı Perfore Ayraç -->
  <div class="perforated-divider" style="margin-top: 3.5rem; margin-bottom: 2rem;">
    <span class="perforated-badge">✂ · <?= $is_poet ? 'ŞAİRİN MATBAA ÇIKTILARI' : 'YAZARIN MATBAA ÇIKTILARI'; ?> · ✦</span>
  </div>

  <!-- 2. BÖLÜM BAŞLIĞI -->
  <div class="section-headline" style="margin-bottom:2rem;">
    <h2 class="section-headline-title">
      <span><?= htmlspecialchars( $author['name'], ENT_QUOTES, 'UTF-8' ); ?> Tarafından Kaleme Alınan <?= $is_poet ? 'Şiirler & Eserler' : 'Eserler'; ?></span>
      <span class="tape-badge rot-p1" style="font-size:0.75rem;"><?= count( $posts ); ?> ESER</span>
    </h2>
  </div>

  <!-- 3. YAZARIN ESERLERİ GALERİSİ -->
  <div class="asymmetric-feed-grid">
    <?php if ( ! empty( $posts ) ) : ?>
      <?php foreach ( $posts as $post ) : ?>
        <article class="brutal-card post-card rot-hover-straight">
          <div class="post-card-thumb">
            <a href="<?= SITE_URL; ?>/kategori/<?= Database::slugify( $post['category'] ?? 'yazi' ); ?>" class="tape-badge post-card-tag rot-n1" style="text-decoration:none; color:inherit;">
              <?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?>
            </a>
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
          </div>

          <h3 class="post-card-title">
            <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>">
              <?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>
            </a>
          </h3>

          <div class="post-card-excerpt">
            <p><?= htmlspecialchars( $post['excerpt'], ENT_QUOTES, 'UTF-8' ); ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    <?php else : ?>
      <div class="brutal-card" style="grid-column: 1 / -1; padding: 3rem; text-align: center;">
        <h3>Bu yazarın henüz yayında olan bir eseri bulunmuyor.</h3>
      </div>
    <?php endif; ?>
  </div>

</div><!-- .pratfall-container -->

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
