<?php
/**
 * Pratfall Standalone - Tüm Yazarlarımız Sayfası (/yazarlar)
 * 
 * - Tüm yazarların harf sırasına göre (A'dan Z'ye) listelendiği özel yoklama sayfası.
 * - Anlık filtreleme (arama) ve A-Z harf indeks çubuğu içerir.
 */

$db = Database::get();

// Tüm Yazarları ve Yayınlanan Eser Sayılarını Çek
$authors = $db->query( "
    SELECT a.*, COUNT(p.id) AS post_count 
    FROM authors a
    LEFT JOIN posts p ON a.id = p.author_id AND p.status = 'published'
    GROUP BY a.id
" )->fetchAll();

// Türkçe Karakterlere Tam Uyumlu Alfabetik Sıralama (A-Z)
Helpers::sort_turkish_alphabetical( $authors, 'name' );

// İlk Harflerine Göre Grupla
$grouped = [];
foreach ( $authors as $author ) {
    $first_char = mb_substr( trim( $author['name'] ), 0, 1, 'UTF-8' );
    // Türkçe İ/I ayrımı
    if ( $first_char === 'i' || $first_char === 'İ' ) {
        $key = 'İ';
    } elseif ( $first_char === 'ı' || $first_char === 'I' ) {
        $key = 'I';
    } else {
        $key = mb_strtoupper( $first_char, 'UTF-8' );
    }
    $grouped[$key][] = $author;
}

$seo_data = [
    'title'       => 'Tüm Yazarlarımız ✦ Kadro & Yoklama Listesi',
    'description' => 'Pratfall sayfalarında kelimeleriyle iz bırakan tüm yazarlarımız ve edebiyat kadromuz (A\'dan Z\'ye alfabe sırasıyla).',
    'url'         => SITE_URL . '/yazarlar',
    'type'        => 'website',
];

require PATH_VIEWS . '/layout/header.php';
?>

<div class="pratfall-container" style="padding-top:2rem; padding-bottom:4rem;">

  <!-- Üst Başlık & Edebi Manşet -->
  <div class="section-headline" style="margin-bottom:2rem; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:1.25rem;">
    <div>
      <div style="margin-bottom:0.4rem;">
        <span class="tape-badge rot-n1" style="font-size:0.75rem;">✦ KADROMUZ & KALEMLER · YOKLAMA LİSTESİ</span>
      </div>
      <h1 class="section-headline-title" style="margin:0;">
        <span>Tüm Yazarlarımız</span>
        <span class="pill-badge pill-purple" style="font-size:0.78rem; vertical-align:middle; margin-left:0.5rem;">
          <?= count( $authors ); ?> Yazar
        </span>
      </h1>
      <p style="font-family:var(--font-serif); font-size:1.15rem; color:var(--text-muted); margin:0.6rem 0 0 0; max-width:720px; line-height:1.6;">
        Pratfall sayfalarında kelimeleriyle, şiirleriyle ve denemeleriyle yer alan tüm yazar kadromuz (A'dan Z'ye harf sırasıyla).
      </p>
    </div>

    <!-- Anlık Yazar Arama Kutusu -->
    <div style="min-width:260px; max-width:320px; width:100%;">
      <div style="position:relative;">
        <input type="text" id="authorSearchInput" placeholder="Yazar adı ile ara..." style="width:100%; padding:0.65rem 0.85rem 0.65rem 2.2rem; border:2px solid var(--border-color); background:var(--bg-surface); font-family:var(--font-mono); font-size:0.85rem; box-shadow:2px 2px 0 var(--border-color); color:var(--text-main);" />
        <span style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.9rem;">🔍</span>
      </div>
    </div>
  </div>

  <!-- A-Z Hızlı Harf Atlama İndeksi -->
  <div style="background:var(--bg-card); border:1.5px solid var(--border-color); padding:0.75rem 1rem; margin-bottom:2.5rem; display:flex; flex-wrap:wrap; gap:0.4rem; align-items:center; box-shadow:2px 2px 0 var(--border-color);">
    <span style="font-family:var(--font-mono); font-size:0.72rem; font-weight:700; color:var(--text-muted); margin-right:0.4rem; text-transform:uppercase;">
      HARF DİZİNİ:
    </span>
    <?php foreach ( array_keys( $grouped ) as $letter ) : ?>
      <a href="#harf-<?= urlencode( $letter ); ?>" class="author-letter-link" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:var(--bg-surface); border:1px solid var(--border-color); color:var(--text-main); font-family:var(--font-mono); font-size:0.78rem; font-weight:700; text-decoration:none; transition:all 0.15s ease;" onmouseover="this.style.background='var(--accent-primary)'; this.style.color='#fff';" onmouseout="this.style.background='var(--bg-surface)'; this.style.color='var(--text-main)';">
        <?= htmlspecialchars( $letter ); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Harf Gruplu Yazarlar Listesi -->
  <div id="authorsContainer">
    <?php if ( ! empty( $grouped ) ) : ?>
      <?php foreach ( $grouped as $letter => $group_authors ) : ?>
        <div class="author-letter-section" id="harf-<?= urlencode( $letter ); ?>" style="margin-bottom:3rem; scroll-margin-top:2rem;">
          
          <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; border-bottom:2px dashed var(--border-subtle); padding-bottom:0.5rem;">
            <div style="width:40px; height:40px; background:var(--border-color); color:#fff; display:flex; align-items:center; justify-content:center; font-family:var(--font-mono); font-size:1.25rem; font-weight:800; border-radius:2px; box-shadow:2px 2px 0 var(--accent-primary);">
              <?= htmlspecialchars( $letter ); ?>
            </div>
            <div style="font-family:var(--font-mono); font-size:0.8rem; color:var(--text-muted);">
              <?= count( $group_authors ); ?> Yazar
            </div>
          </div>

          <div class="author-spotlight-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1.25rem;">
            <?php foreach ( $group_authors as $author ) : ?>
              <a href="<?= Helpers::author_url( $author ); ?>" class="author-pill-card searchable-author-card" data-name="<?= htmlspecialchars( mb_strtolower( $author['name'], 'UTF-8' ) ); ?>" style="text-decoration:none; color:inherit; padding:0.85rem 1rem;">
                <div class="author-pill-avatar">
                  <?= Helpers::author_avatar( $author, 52 ); ?>
                </div>
                <div class="author-pill-info" style="min-width:0;">
                  <span class="author-pill-name" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars( $author['name'] ); ?></span>
                  <span class="author-pill-role" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars( ! empty( $author['title'] ) ? $author['title'] : ( Helpers::is_poet( $author ) ? 'Şair' : 'Yazar & Katkıda Bulunan' ) ); ?></span>
                  <span class="author-pill-count">✦ <?= (int) $author['post_count']; ?> Eser</span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>

        </div>
      <?php endforeach; ?>
    <?php else : ?>
      <div class="brutal-card" style="padding:3rem; text-align:center; color:var(--text-muted); background:var(--bg-card);">
        Henüz kayıtlı bir yazar bulunmuyor.
      </div>
    <?php endif; ?>
  </div>

  <div id="noResultsMsg" style="display:none; padding:3rem; text-align:center; background:var(--bg-card); border:1.5px dashed var(--border-color); font-family:var(--font-mono); font-size:0.95rem; color:var(--text-muted); margin-bottom:3rem;">
    Aradığınız kriterlere uygun yazar bulunamadı.
  </div>

  <!-- Altbilgi Dönüş Butonu -->
  <div style="text-align:center; margin-top:2.5rem; padding-top:2rem; border-top:1px dashed var(--border-subtle);">
    <a href="<?= SITE_URL; ?>/" class="stamp-tag" style="background:var(--bg-tape); color:var(--text-main); text-decoration:none; font-size:0.85rem; padding:0.6rem 1.25rem; display:inline-flex; align-items:center; gap:0.5rem; box-shadow:2px 2px 0 var(--border-color);">
      ← Ana Sayfaya Dön
    </a>
  </div>

</div>

<script>
(function() {
  const searchInput = document.getElementById('authorSearchInput');
  const cards = document.querySelectorAll('.searchable-author-card');
  const sections = document.querySelectorAll('.author-letter-section');
  const noResults = document.getElementById('noResultsMsg');

  if (!searchInput) return;

  searchInput.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    let totalVisible = 0;

    sections.forEach(sec => {
      let visibleInSection = 0;
      const secCards = sec.querySelectorAll('.searchable-author-card');
      secCards.forEach(card => {
        const name = card.getAttribute('data-name') || '';
        if (q === '' || name.includes(q)) {
          card.style.display = 'flex';
          visibleInSection++;
          totalVisible++;
        } else {
          card.style.display = 'none';
        }
      });

      sec.style.display = visibleInSection > 0 ? 'block' : 'none';
    });

    if (noResults) {
      noResults.style.display = (totalVisible === 0) ? 'block' : 'none';
    }
  });
})();
</script>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
