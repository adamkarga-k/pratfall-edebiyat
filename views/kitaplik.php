<?php
/**
 * Pratfall Standalone - Pratfall Kitaplığı Özel Sayfası (/kitaplik)
 * 
 * Yayınevimizden çıkan kitapların sergilendiği özel edebi galeri.
 * Kapaklara tıklandığında doğrudan ilgili eserin hedef linkine gider.
 * Fiyat ve ticari etiketlerden arındırılmış, saf edebi estetik.
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

<!-- Pratfall Kitaplığı Özel Edebi Stilleri -->
<style>
  .kitaplik-hero {
    margin-top: 3rem;
    margin-bottom: 3.5rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
  }

  .kitaplik-title {
    font-family: var(--font-serif);
    font-size: clamp(2.4rem, 5vw, 3.8rem);
    font-weight: 700;
    line-height: 1.1;
    margin: 0.5rem 0 1rem 0;
    letter-spacing: -0.02em;
    color: var(--text-main);
  }

  .kitaplik-desc {
    font-family: var(--font-serif);
    font-size: 1.2rem;
    line-height: 1.7;
    color: var(--text-muted);
    max-width: 800px;
    margin: 0;
  }

  /* Kitaplık Raf Izgarası */
  .kitaplik-shelf-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 4rem 3rem;
    margin-bottom: 5rem;
    align-items: start;
  }

  /* Mobil İki Sütunlu Zarif Düzen */
  @media (max-width: 768px) {
    .kitaplik-hero {
      margin-top: 1.75rem;
      margin-bottom: 2.25rem;
      padding-bottom: 1.5rem;
    }
    .kitaplik-title {
      font-size: 2.1rem;
      margin: 0.35rem 0 0.75rem 0;
    }
    .kitaplik-desc {
      font-size: 1.02rem;
      line-height: 1.6;
    }
    .kitaplik-shelf-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 2.25rem 1.15rem;
      margin-bottom: 3.5rem;
    }
    .book-meta-box {
      margin-top: 0.85rem;
      gap: 0.25rem;
    }
    .book-title-link {
      font-size: 1.05rem;
      line-height: 1.25;
    }
    .book-author-text {
      font-size: 0.75rem;
    }
    .book-year-badge {
      font-size: 0.68rem;
    }
    .book-desc-quote {
      font-size: 0.82rem;
      line-height: 1.45;
      margin: 0.35rem 0 0.6rem 0;
      -webkit-line-clamp: 2;
    }
    .book-action-btn {
      padding: 0.35rem 0.75rem;
      font-size: 0.72rem;
      width: 100%;
      justify-content: center;
    }
  }

  @media (max-width: 440px) {
    .kitaplik-shelf-grid {
      gap: 1.75rem 0.85rem;
    }
    .book-title-link {
      font-size: 0.95rem;
    }
    .book-author-text {
      font-size: 0.7rem;
    }
    .book-action-btn {
      padding: 0.3rem 0.55rem;
      font-size: 0.68rem;
    }
  }

  /* Tekil Kitap Parçası */
  .book-item {
    display: flex;
    flex-direction: column;
    position: relative;
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  }

  /* Gerçekçi 3D Kitap Nesnesi */
  .book-cover-stage {
    position: relative;
    width: 100%;
    padding-top: 148%; /* Standart 1 : 1.48 Edebi Kitap Oranı */
    border-radius: 4px 10px 10px 4px;
    background: #1c1917;
    box-shadow: 
      -4px 0 10px rgba(0, 0, 0, 0.12),
      12px 20px 32px rgba(0, 0, 0, 0.18),
      inset 0 0 0 1px rgba(255, 255, 255, 0.15);
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;
    overflow: hidden;
    cursor: pointer;
  }

  /* Kitap Sırtı Işık ve Katlanma Çizgisi Efekti */
  .book-cover-stage::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 24px;
    background: linear-gradient(
      90deg, 
      rgba(255, 255, 255, 0.22) 0%, 
      rgba(0, 0, 0, 0.35) 45%, 
      rgba(255, 255, 255, 0.15) 70%, 
      transparent 100%
    );
    z-index: 4;
    pointer-events: none;
    mix-blend-mode: overlay;
  }

  /* Sağ Kenar Sayfa Kalınlığı Efekti */
  .book-cover-stage::after {
    content: '';
    position: absolute;
    top: 2px;
    right: 0;
    bottom: 2px;
    width: 3px;
    background: linear-gradient(to right, rgba(0,0,0,0.2), #faf8f5, rgba(0,0,0,0.15));
    z-index: 4;
    pointer-events: none;
  }

  .book-item:hover .book-cover-stage {
    transform: translateY(-10px) rotate(-1.2deg) scale(1.02);
    box-shadow: 
      -6px 6px 18px rgba(0, 0, 0, 0.18),
      18px 32px 48px rgba(0, 0, 0, 0.28);
  }

  .book-cover-img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    z-index: 2;
  }

  .book-cover-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    padding: 2.5rem 1.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background: linear-gradient(135deg, #262626 0%, #171717 100%);
    color: #f5f5f4;
    z-index: 2;
    border-left: 6px solid var(--accent-primary);
  }

  /* Kitap Alt Künyesi */
  .book-meta-box {
    margin-top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
  }

  .book-title-link {
    font-family: var(--font-serif);
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text-main);
    text-decoration: none;
    line-height: 1.25;
    transition: color 0.2s ease;
  }

  .book-title-link:hover {
    color: var(--accent-primary);
  }

  .book-author-text {
    font-family: var(--font-mono);
    font-size: 0.85rem;
    color: var(--accent-primary);
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .book-year-badge {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 0.1rem;
  }

  .book-desc-quote {
    font-family: var(--font-serif);
    font-size: 0.95rem;
    font-style: italic;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0.5rem 0 0.85rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .book-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    align-self: flex-start;
    padding: 0.45rem 1.1rem;
    background: var(--bg-card);
    border: 1.5px solid var(--border-color);
    border-radius: 4px;
    color: var(--text-main);
    text-decoration: none;
    font-family: var(--font-mono);
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    transition: all 0.2s ease;
    box-shadow: 2px 2px 0 var(--border-color);
  }

  .book-action-btn:hover {
    background: var(--text-main);
    color: var(--bg-page);
    border-color: var(--text-main);
    transform: translate(-1px, -1px);
    box-shadow: 3px 3px 0 var(--accent-primary);
  }
</style>

<div class="pratfall-container">

  <!-- Edebi Kitaplık Başlığı -->
  <header class="kitaplik-hero">
    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.5rem;">
      <span class="tape-badge rot-n1" style="font-size:0.75rem;">✦ MATBAA NEŞRİYATI</span>
      <span class="stamp-tag" style="font-size:0.72rem;"><?= count( $books ); ?> CİLT ESER</span>
    </div>

    <h1 class="kitaplik-title">
      Pratfall Kitaplığı
    </h1>

    <p class="kitaplik-desc">
      Kağıdın kokusu, cildin ağırlığı ve kusurlu matbaa mürekkebi. Yayınevimiz tarafından basılıp yayına hazırlanan tüm eserleri doğrudan buradan inceleyebilir, kapaklara tıklayarak detaylarına ulaşabilirsiniz.
    </p>
  </header>

  <!-- Kitaplık Rafı / Galerisi -->
  <?php if ( ! empty( $books ) ) : ?>
    <div class="kitaplik-shelf-grid">
      <?php foreach ( $books as $b ) : ?>
        <article class="book-item">
          
          <!-- Kitap Cildi & Kapağı (Tıklanınca doğrudan eserin linkine gider) -->
          <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="book-cover-stage" title="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?> — İncele & Edin">
            <?php if ( ! empty( $b['cover_image'] ) ) : ?>
              <img src="<?= htmlspecialchars( $b['cover_image'] ); ?>" alt="<?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>" class="book-cover-img" loading="lazy" />
            <?php else : ?>
              <div class="book-cover-placeholder">
                <span class="tape-badge" style="font-size:0.68rem; align-self:flex-start;">PRATFALL</span>
                <div>
                  <h2 style="font-family:var(--font-serif); font-size:1.35rem; margin:0 0 0.5rem 0; line-height:1.2; font-weight:700;">
                    <?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>
                  </h2>
                  <?php if ( ! empty( $b['author'] ) ) : ?>
                    <div style="font-family:var(--font-mono); font-size:0.8rem; color:#d6d3d1;">
                      <?= htmlspecialchars( $b['author'], ENT_QUOTES, 'UTF-8' ); ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div style="font-family:var(--font-mono); font-size:0.7rem; color:#a8a29e; border-top:1px solid #444; padding-top:0.5rem;">
                  ✦ BASILI NÜSHA
                </div>
              </div>
            <?php endif; ?>
          </a>

          <!-- Alt Künye (Yayınevi ve Fiyat bilgisi kaldırıldı, saf edebiyat) -->
          <div class="book-meta-box">
            
            <h2 style="margin:0;">
              <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="book-title-link">
                <?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>
              </a>
            </h2>

            <?php if ( ! empty( $b['author'] ) ) : ?>
              <div class="book-author-text">
                <?= htmlspecialchars( $b['author'], ENT_QUOTES, 'UTF-8' ); ?>
              </div>
            <?php endif; ?>

            <?php if ( ! empty( $b['publish_year'] ) ) : ?>
              <div class="book-year-badge">
                <?= htmlspecialchars( $b['publish_year'], ENT_QUOTES, 'UTF-8' ); ?>
              </div>
            <?php endif; ?>

            <?php if ( ! empty( $b['description'] ) ) : ?>
              <div class="book-desc-quote">
                &ldquo;<?= htmlspecialchars( $b['description'], ENT_QUOTES, 'UTF-8' ); ?>&rdquo;
              </div>
            <?php endif; ?>

            <div style="margin-top:0.4rem;">
              <a href="<?= htmlspecialchars( $b['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" class="book-action-btn">
                <span>Kitabı İncele</span>
                <span>↗</span>
              </a>
            </div>

          </div>

        </article>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div style="padding:5rem 2rem; text-align:center; background:var(--bg-card); border:2px dashed var(--border-color); border-radius:6px; margin-bottom:5rem;">
      <span style="font-size:2.5rem; display:block; margin-bottom:1rem;">📚</span>
      <h3 style="font-family:var(--font-serif); font-size:1.4rem; margin:0 0 0.5rem 0;">Kitaplık henüz tanzim ediliyor</h3>
      <p style="color:var(--text-muted); font-size:0.95rem; margin:0;">
        Yayınevimizden çıkacak eserler pek yakında bu raflarda yerini alacak.
      </p>
    </div>
  <?php endif; ?>

</div>

<?php require PATH_VIEWS . '/layout/footer.php'; ?>
