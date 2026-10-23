<?php
/**
 * Pratfall Standalone - Ortak Altbilgi (Footer)
 */

$manifesto_title = Database::get_setting( 'manifesto_title', 'Pratfall Ne Demek?' );
$manifesto_badge = Database::get_setting( 'manifesto_badge', '✦ EDİTORYAL MANİFESTO' );
$manifesto_tag   = Database::get_setting( 'manifesto_tag', 'ELLIOT ARONSON · 1966' );
$manifesto_p1    = Database::get_setting( 'manifesto_p1' );
$manifesto_p2    = str_ireplace( 'Pratfall Mecmua', 'Pratfall', Database::get_setting( 'manifesto_p2' ) );

$footer_slogan    = Database::get_setting( 'footer_slogan', '✦ Kusurlu Kalma Çabası' );
$footer_desc      = str_ireplace( [ 'kültür mecmuasıdır', 'Pratfall Mecmua', 'mecmua' ], [ 'edebiyat dergisi ve yayınevinin bağımsız edebiyat platformudur', 'Pratfall', 'edebiyat platformu' ], Database::get_setting( 'footer_desc' ) );
$newsletter_title = Database::get_setting( 'footer_newsletter_title', 'Matbaa & Edebiyat Bülteni' );
$newsletter_desc  = Database::get_setting( 'footer_newsletter_desc', 'Dergimizde yayınlanan eserler, yayınevimizden çıkan kitaplar ve edebiyat dünyasından bültenler için e-posta bültenimize kaydolun.' );
$copyright_text   = str_ireplace( 'mecmuaya', 'platforma', Database::get_setting( 'footer_copyright', 'Tüm hakları yazarlara ve platforma aittir.' ) );
?>
</main><!-- #primary -->

<footer class="pratfall-footer" id="manifesto">
  <div class="pratfall-container">
    
    <!-- PRATFALL ETKİSİ VE MANİFESTO PANELİ ("PRATFALL NE DEMEK?") -->
    <section class="manifesto-box brutal-card rot-n05" style="margin-bottom:3.5rem; padding:2.5rem; background:var(--bg-card); border-left:6px solid var(--accent-primary);">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.75rem;">
        <span class="tape-badge rot-n1" style="font-size:0.8rem;">
          <?= htmlspecialchars( $manifesto_badge, ENT_QUOTES, 'UTF-8' ); ?>
        </span>
        <span class="stamp-tag" style="font-size:0.75rem;">
          <?= htmlspecialchars( $manifesto_tag, ENT_QUOTES, 'UTF-8' ); ?>
        </span>
      </div>

      <h2 style="font-size:clamp(1.6rem, 3.5vw, 2.3rem); margin:0 0 1.25rem 0; line-height:1.2;">
        <?= htmlspecialchars( $manifesto_title, ENT_QUOTES, 'UTF-8' ); ?>
      </h2>

      <div style="font-family:var(--font-serif); font-size:1.15rem; line-height:1.8; color:var(--text-main);">
        <p style="margin-bottom:1.25rem;">
          <?= $manifesto_p1; ?>
        </p>
        <p style="margin:0;">
          <?= $manifesto_p2; ?>
        </p>
      </div>
    </section>

    <div class="footer-grid">
      
      <!-- 1. Sütun: Tanıtım & Slogan -->
      <div class="footer-col">
        <h3 class="footer-manifesto-title">
          <?= htmlspecialchars( $footer_slogan, ENT_QUOTES, 'UTF-8' ); ?>
        </h3>
        <p class="footer-manifesto-text">
          <?= htmlspecialchars( $footer_desc, ENT_QUOTES, 'UTF-8' ); ?>
        </p>
        <div style="margin-top:1.25rem;">
          <span class="tape-badge rot-n1">KUSURLU KALMA ÇABASI</span>
        </div>
      </div>

      <!-- 2. Sütun: Navigasyon -->
      <div class="footer-col">
        <h4 class="footer-col-title">Site Bölümleri</h4>
        <ul class="footer-links">
          <li><a href="<?= SITE_URL; ?>/">✦ Ana Sayfa</a></li>
          <li><a href="<?= SITE_URL; ?>/#dergiler">✦ Dergilerimizin Sayıları</a></li>
          <li><a href="<?= SITE_URL; ?>/kitaplik">✦ Pratfall Kitaplığı</a></li>
          <li><a href="<?= SITE_URL; ?>/#yazarlar">✦ Kimler Yazmış?</a></li>
          <li><a href="<?= SITE_URL; ?>/#manifesto">✦ Pratfall?</a></li>
          <li><a href="<?= SITE_URL; ?>/dergiler">✦ Tüm Arşiv & Fanzinler</a></li>
        </ul>
      </div>

      <!-- 3. Sütun: Matbaa Bülteni -->
      <div class="footer-col">
        <h4 class="footer-col-title"><?= htmlspecialchars( $newsletter_title, ENT_QUOTES, 'UTF-8' ); ?></h4>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem; line-height:1.5;">
          <?= htmlspecialchars( $newsletter_desc, ENT_QUOTES, 'UTF-8' ); ?>
        </p>
        <form style="display:flex; flex-direction:column; gap:0.5rem;" onsubmit="event.preventDefault(); alert('Bültene kaydınız alındı.');">
          <input type="email" placeholder="E-posta adresiniz..." required style="padding:0.6rem 0.8rem; border:1.5px solid var(--border-color); background:var(--bg-card); color:var(--text-main); font-family:var(--font-mono); font-size:0.8rem;" />
          <button type="submit" class="stamp-tag" style="background:var(--accent-primary); color:#fff; cursor:pointer; text-align:center; padding:0.5rem;">
            Bültene Abone Ol ↗
          </button>
        </form>
      </div>

    </div>

    <!-- Alt Bilgi Çubuğu -->
    <div class="footer-bottom">
      <div>
        &copy; <?= date( 'Y' ); ?> <strong><?= htmlspecialchars( trim( str_ireplace( 'Mecmua', '', Database::get_setting( 'site_name', 'PRATFALL' ) ) ), ENT_QUOTES, 'UTF-8' ); ?></strong>. <?= htmlspecialchars( $copyright_text, ENT_QUOTES, 'UTF-8' ); ?>
        <span style="margin-left:0.5rem; opacity:0.5;">· <a href="<?= SITE_URL; ?>/admin" style="color:inherit; text-decoration:none;">Yönetim Masası</a></span>
      </div>
      <div>
        <a href="#primary" class="stamp-tag">↑ Yukarı Çık</a>
      </div>
    </div>

  </div>
</footer>

<!-- JavaScript Dosyaları -->
<script src="<?= SITE_URL; ?>/assets/js/dergiler-vitrin.js?v=<?= PRATFALL_APP_VERSION; ?>"></script>
<script src="<?= SITE_URL; ?>/assets/js/theme.js?v=<?= PRATFALL_APP_VERSION; ?>"></script>
</body>
</html>
