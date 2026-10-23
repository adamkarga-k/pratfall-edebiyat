<?php
/**
 * Pratfall Standalone - Tüm Dergiler Arşivi (/dergiler)
 */

$db = Database::get();
$magazines = $db->query( "SELECT * FROM magazines ORDER BY order_num ASC, id DESC" )->fetchAll();

$seo_data = [
    'title'       => 'Dergilerimizin Tüm Sayıları',
    'description' => 'Pratfall basılı dergi sayıları ve edebiyat arşivi.',
    'url'         => SITE_URL . '/dergiler',
    'type'        => 'website',
];

require PATH_VIEWS . '/layout/header.php';
?>

<div class="pratfall-container">

  <div class="section-headline" style="margin-top:2rem; margin-bottom:2.5rem;">
    <h1 class="section-headline-title">
      <span>Dergilerimizin Tüm Sayıları</span>
      <span class="tape-badge rot-p1" style="font-size:0.75rem;">TAM ARŞİV</span>
    </h1>
    <p style="font-family:var(--font-serif); font-size:1.1rem; color:var(--text-muted); margin-top:0.5rem;">
      Matbaadan çıkan tüm sayılarımızı buradan inceleyebilir, basılı nüshaları sipariş verebilirsiniz.
    </p>
  </div>

  <div class="asymmetric-feed-grid" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:2rem;">
    <?php foreach ( $magazines as $mag ) : ?>
      <div class="brutal-card dergi-card-item rot-hover-straight" style="margin:0;">
        <div class="dergi-card-poster" style="height:360px;">
          <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener">
            <?php if ( ! empty( $mag['cover_image'] ) ) : ?>
              <img src="<?= $mag['cover_image']; ?>" alt="<?= htmlspecialchars( $mag['title'], ENT_QUOTES, 'UTF-8' ); ?>" class="dergi-poster-img" />
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
          <?php if ( ! empty( $mag['period'] ) ) : ?>
            <div style="margin-bottom:0.75rem;">
              <span class="dergi-period"><?= htmlspecialchars( $mag['period'], ENT_QUOTES, 'UTF-8' ); ?></span>
            </div>
          <?php endif; ?>
          <a href="<?= htmlspecialchars( $mag['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="dergi-buy-btn">
            Sayıyı İncele ↗
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
