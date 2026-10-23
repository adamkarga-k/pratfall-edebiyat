<?php
/**
 * Pratfall Standalone - Pratfall Kitaplığı Özel Sayfası (/kitaplik)
 * 
 * Yayınevimizden çıkan kitapların sergilendiği,
 * kapaklarına tıklandığında doğrudan satın alma / sipariş linkine giden özel sayfa.
 */

$db = Database::get();
$books = $db->query( "SELECT * FROM books ORDER BY order_num ASC, id DESC" )->fetchAll();

$seo_data = [
    'title'       => 'Pratfall Kitaplığı ✦ Yayınevimizden Çıkan Eserler',
    'description' => 'Pratfall Yayınevi tarafından basılan kitaplar, özel edisyonlar ve edebi külliyat.',
    'url'         => SITE_URL . '/kitaplik',
    'type'        => 'website',
];

require PATH_VIEWS . '/layout/header.php';
?>

<div class="pratfall-container">

  <!-- Başlık & Manifesto Şeridi -->
  <div class="section-headline" style="margin-top:2.5rem; margin-bottom:2.5rem;">
    <h1 class="section-headline-title" style="display:flex; align-items:center; flex-wrap:wrap; gap:0.75rem;">
      <span>Pratfall Kitaplığı</span>
      <span class="tape-badge rot-p1" style="font-size:0.75rem;">YAYINEVİMİZDEN ÇIKANLAR</span>
    </h1>
    <p style="font-family:var(--font-serif); font-size:1.15rem; color:var(--text-muted); margin-top:0.6rem; max-width:820px; line-height:1.6;">
      Kusurlu kalma çabamızın matbaa kokulu basılı ciltleri. Yayınevimizden çıkan tüm eserleri, özel monografileri ve edebi çalışmaları buradan inceleyebilir, kapaklara tıklayarak doğrudan temin edebilirsiniz.
    </p>
  </div>

  <!-- Kitaplar Izgarası -->
  <div class="asymmetric-feed-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:2.5rem; margin-bottom:4rem;">
    <?php if ( ! empty( $books ) ) : ?>
      <?php foreach ( $books as $b ) : ?>
        <div class="brutal-card dergi-card-item rot-hover-straight" style="margin:0; display:flex; flex-direction:column; height:100%;">
          
          <!-- Kitap Kapağı (Tıklanınca doğrudan linke gider) -->
          <div class="dergi-card-poster" style="height:390px; position:relative; background:#292524; overflow:hidden;">
            <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" style="display:block; width:100%; height:100%;" title="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?> - İncele & Satın Al">
              <?php if ( ! empty( $b['cover_image'] ) ) : ?>
                <img src="<?= htmlspecialchars( $b['cover_image'] ); ?>" alt="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>" class="dergi-poster-img" style="object-fit:cover; width:100%; height:100%; transition:transform 0.3s ease;" />
              <?php else : ?>
                <div style="width:100%; height:100%; color:#f6f3eb; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:1.5rem; text-align:center;">
                  <span style="font-size:2rem; margin-bottom:0.5rem;">📖</span>
                  <strong style="font-family:var(--font-serif); font-size:1.1rem;"><?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?></strong>
                </div>
              <?php endif; ?>
            </a>

            <?php if ( ! empty( $b['publish_year'] ) ) : ?>
              <span class="tape-badge dergi-tape-tag rot-n1" style="font-size:0.7rem;">
                <?= htmlspecialchars( $b['publish_year'], ENT_QUOTES, 'UTF-8' ); ?>
              </span>
            <?php endif; ?>
          </div>

          <!-- Kitap Bilgileri -->
          <div class="dergi-card-details" style="display:flex; flex-direction:column; flex:1;">
            
            <h3 class="dergi-card-title" style="margin-bottom:0.25rem;">
              <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener">
                <?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>
              </a>
            </h3>

            <?php if ( ! empty( $b['author'] ) ) : ?>
              <div style="font-family:var(--font-mono); font-size:0.82rem; color:var(--accent-primary); font-weight:700; margin-bottom:0.6rem;">
                <?= htmlspecialchars( $b['author'], ENT_QUOTES, 'UTF-8' ); ?>
              </div>
            <?php endif; ?>

            <?php if ( ! empty( $b['description'] ) ) : ?>
              <p style="font-size:0.85rem; line-height:1.5; color:var(--text-muted); margin:0 0 1rem 0; flex:1;">
                <?= htmlspecialchars( $b['description'], ENT_QUOTES, 'UTF-8' ); ?>
              </p>
            <?php endif; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; border-top:1px dashed var(--border-color); padding-top:0.75rem;">
              <span class="dergi-period" style="font-size:0.75rem;">✦ PRATFALL YAYINLARI</span>
              <?php if ( ! empty( $b['price'] ) ) : ?>
                <span class="dergi-price" style="font-weight:700; color:var(--accent-secondary);"><?= htmlspecialchars( $b['price'], ENT_QUOTES, 'UTF-8' ); ?></span>
              <?php endif; ?>
            </div>

            <!-- Satın Alma Butonu (İstenen Linke Gider) -->
            <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-buy-btn" style="text-align:center;">
              Kitabı İncele / Satın Al ↗
            </a>

          </div>
        </div>
      <?php endforeach; ?>
    <?php else : ?>
      <div style="grid-column: 1 / -1; padding:4rem; text-align:center; color:var(--text-muted); background:var(--bg-card); border:2px dashed var(--border-color);">
        Kitaplıkta henüz sergilenen kitap bulunmuyor.
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
