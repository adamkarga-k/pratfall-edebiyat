<?php
/**
 * Pratfall Standalone - 404 Sayfa Bulunamadı
 */

$seo_data = [
    'title' => 'Sayfa Bulunamadı (404)',
];

require PATH_VIEWS . '/layout/header.php';
?>

<div class="pratfall-container">
  <div class="brutal-card rot-n05" style="margin:4rem auto; max-width:650px; padding:3rem; text-align:center; background:var(--bg-card);">
    <span class="tape-badge rot-n2" style="font-size:0.85rem;">✦ EDİTORYAL HATA 404</span>
    <h1 style="font-size:2.8rem; margin:1rem 0; font-family:var(--font-serif);">Sayfa Çatlaklarda Kayboldu</h1>
    <p style="font-family:var(--font-serif); font-size:1.15rem; line-height:1.7; color:var(--text-muted); margin-bottom:2rem;">
      "Kusurlu Kalma Çabası" derken belki de bu sayfayı hiç basmadık ya da sahafların tozlu raflarında bir yere sıkıştı. Aradığınız metin taşınmış veya silinmiş olabilir.
    </p>
    <a href="<?= SITE_URL; ?>/" class="stamp-tag" style="padding:0.75rem 1.5rem; font-size:0.9rem;">
      ← Mecmua Ana Sayfasına Dön
    </a>
  </div>
</div>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
