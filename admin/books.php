<?php
/**
 * Pratfall Standalone - Pratfall Kitaplığı Yönetimi (Yayınevi Kitapları)
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Pratfall Kitaplığı Yönetimi';
$active_tab = 'books';

$db = Database::get();
$action = $_GET['action'] ?? 'list';
$msg = '';
$err = '';

// 1. Silme İşlemi
if ( $action === 'delete' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "DELETE FROM books WHERE id = ?" );
    $stmt->execute( [ $id ] );
    header( 'Location: ' . SITE_URL . '/admin/books.php?msg=deleted' );
    exit;
}

// 2. Kaydet / Güncelle
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $id           = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $title        = trim( $_POST['title'] ?? '' );
        $author       = trim( $_POST['author'] ?? '' );
        $buy_url      = trim( $_POST['buy_url'] ?? '#' );
        $price        = trim( $_POST['price'] ?? '' );
        $publish_year = trim( $_POST['publish_year'] ?? '' );
        $description  = trim( $_POST['description'] ?? '' );
        $order_num    = (int) ( $_POST['order_num'] ?? 0 );
        $cover_image  = trim( $_POST['cover_image'] ?? '' );

        // Kapak Resmi Yüklendiyse (Otomatik WebP & 1600px Optimizasyon)
        if ( isset( $_FILES['cover_file'] ) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK ) {
            $opt_name = ImageOptimizer::optimize_uploaded_file( $_FILES['cover_file'], PATH_UPLOADS . '/covers', 'book' );
            if ( $opt_name ) {
                $cover_image = SITE_URL . '/uploads/covers/' . $opt_name;
            }
        }

        if ( empty( $title ) ) {
            $err = 'Kitap başlığı zorunludur.';
        } else {
            if ( $id > 0 ) {
                $stmt = $db->prepare( "
                    UPDATE books SET 
                        title = ?, author = ?, cover_image = ?, buy_url = ?, 
                        price = ?, publish_year = ?, description = ?, order_num = ?
                    WHERE id = ?
                " );
                $stmt->execute( [ $title, $author, $cover_image, $buy_url, $price, $publish_year, $description, $order_num, $id ] );
                header( 'Location: ' . SITE_URL . '/admin/books.php?msg=updated' );
                exit;
            } else {
                $stmt = $db->prepare( "
                    INSERT INTO books (title, author, cover_image, buy_url, price, publish_year, description, order_num)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                " );
                $stmt->execute( [ $title, $author, $cover_image, $buy_url, $price, $publish_year, $description, $order_num ] );
                header( 'Location: ' . SITE_URL . '/admin/books.php?msg=created' );
                exit;
            }
        }
    }
}

if ( isset( $_GET['msg'] ) ) {
    if ( $_GET['msg'] === 'created' ) $msg = 'Yeni kitap başarıyla kitaplığa eklendi.';
    if ( $_GET['msg'] === 'updated' ) $msg = 'Kitap bilgileri güncellendi.';
    if ( $_GET['msg'] === 'deleted' ) $msg = 'Kitap sistemden silindi.';
}

require PATH_ADMIN . '/layout/header.php';
?>

<?php if ( $action === 'new' || $action === 'edit' ) : ?>
  <?php
  $book = [
      'id'           => 0,
      'title'        => '',
      'author'       => '',
      'cover_image'  => '',
      'buy_url'      => '',
      'price'        => '',
      'publish_year' => '',
      'description'  => '',
      'order_num'    => 0,
  ];

  if ( $action === 'edit' && isset( $_GET['id'] ) ) {
      $stmt = $db->prepare( "SELECT * FROM books WHERE id = ?" );
      $stmt->execute( [ (int) $_GET['id'] ] );
      $found = $stmt->fetch();
      if ( $found ) {
          $book = $found;
      }
  }
  ?>

  <div class="admin-header-row" style="margin-bottom:1.5rem;">
    <div>
      <h1 class="topbar-page-title">
        <?= $book['id'] > 0 ? '📚 Kitap Bilgilerini Düzenle' : '📚 Kitaplığa Yeni Kitap Ekle'; ?>
      </h1>
      <p style="margin:0.25rem 0 0 0; color:var(--adm-muted); font-size:0.87rem;">
        Yayınevinizden çıkan kitapların kapak görselini, satın alma linkini ve detaylarını buradan belirleyebilirsiniz.
      </p>
    </div>
    <div>
      <a href="<?= SITE_URL; ?>/admin/books.php" class="btn btn-sm">
        ← Kitap Listesine Dön
      </a>
    </div>
  </div>

  <?php if ( ! empty( $err ) ) : ?>
    <div class="alert alert-error" style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.75rem 1.25rem; border-radius:var(--adm-radius-sm); margin-bottom:1.25rem;">
      <?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= SITE_URL; ?>/admin/books.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
    <input type="hidden" name="id" value="<?= $book['id']; ?>" />

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.75rem;">
      
      <!-- Sol: Temel Bilgiler -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Kitap Künyesi & Satın Alma Bağlantısı</h2>
        </div>
        <div class="card-body">
          
          <div class="form-group">
            <label class="form-label" for="title">Kitap Başlığı *</label>
            <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars( $book['title'], ENT_QUOTES, 'UTF-8' ); ?>" required placeholder="Örn: Kusurlu Kalma Sanatı" style="font-size:1.05rem; font-weight:600;" />
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
            <div class="form-group">
              <label class="form-label" for="author">Yazar / Çevirmen</label>
              <input type="text" name="author" id="author" class="form-control" value="<?= htmlspecialchars( $book['author'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Cahit Sıtkı Tarancı" />
            </div>

            <div class="form-group">
              <label class="form-label" for="publish_year">Baskı / Yayın Yılı</label>
              <input type="text" name="publish_year" id="publish_year" class="form-control" value="<?= htmlspecialchars( $book['publish_year'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: 2026 · 1. Baskı" />
            </div>
          </div>

          <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
            <div class="form-group">
              <label class="form-label" for="buy_url">Satın Alma / Detay Linki (Kapağa Tıklanınca Gidilecek Adres) *</label>
              <input type="url" name="buy_url" id="buy_url" class="form-control" value="<?= htmlspecialchars( $book['buy_url'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://..." required />
              <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.3rem;">Ziyaretçi kitap kapağına veya "Satın Al" butonuna bastığında bu bağlantıya yönlendirilir (Shopier, BKM, D&R, Idefix vb.).</div>
            </div>

            <div class="form-group">
              <label class="form-label" for="price">Fiyat (İsteğe Bağlı)</label>
              <input type="text" name="price" id="price" class="form-control" value="<?= htmlspecialchars( $book['price'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: 150 TL" />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="description">Tanıtım / Arka Kapak Özeti</label>
            <textarea name="description" id="description" class="form-control" rows="4" placeholder="Kitap hakkında kısa edebi bir tanıtım pasajı..."><?= htmlspecialchars( $book['description'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="order_num">Vitrin Sırası</label>
            <input type="number" name="order_num" id="order_num" class="form-control" style="width:120px;" value="<?= (int) $book['order_num']; ?>" />
            <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.3rem;">Küçük sayılar yatay vitrinde en başta görünür (1, 2, 3...).</div>
          </div>

        </div>
      </div>

      <!-- Sağ: Kapak Görseli -->
      <div>
        <div class="card">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 class="card-title">Kitap Kapağı</h3>
            <span class="pill-badge pill-green" style="font-size:0.65rem;">Otomatik WebP</span>
          </div>
          <div class="card-body">
            
            <?php if ( ! empty( $book['cover_image'] ) ) : ?>
              <div style="margin-bottom:1rem; border:1px solid var(--adm-border); border-radius:var(--adm-radius-sm); overflow:hidden; background:#f8fafc; text-align:center; padding:0.5rem;">
                <img src="<?= htmlspecialchars( $book['cover_image'] ); ?>" alt="Kapak" style="max-height:220px; max-width:100%; object-fit:contain; box-shadow:0 4px 10px rgba(0,0,0,0.15);" />
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label class="form-label">Kapak Görseli Yükle</label>
              <input type="file" name="cover_file" accept="image/*" class="form-control" style="padding:0.35rem;" />
              <div style="font-size:0.72rem; color:var(--adm-muted); margin-top:0.3rem;">
                Yüklenen görsel otomatik olarak WebP formatına dönüştürülür ve hafifletilir.
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Veya Görsel URL'si</label>
              <input type="text" name="cover_image" class="form-control" value="<?= htmlspecialchars( $book['cover_image'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://..." style="font-size:0.8rem;" />
            </div>

            <div style="margin-top:1.5rem;">
              <button type="submit" class="btn btn-accent" style="width:100%; justify-content:center; padding:0.75rem; font-weight:700;">
                <?= $book['id'] > 0 ? '✓ Kitap Bilgilerini Güncelle' : '✓ Kitaplığına Ekle'; ?>
              </button>
            </div>

          </div>
        </div>
      </div>

    </div>
  </form>

<?php else : ?>

  <!-- LİSTELEME EKRANI -->
  <div class="admin-header-row" style="margin-bottom:1.5rem;">
    <div>
      <h1 class="topbar-page-title" style="display:flex; align-items:center; gap:0.5rem;">
        <span>📚</span> Pratfall Kitaplığı (Yayınevi Kitapları)
      </h1>
      <p style="margin:0.25rem 0 0 0; color:var(--adm-muted); font-size:0.87rem;">
        Buraya eklediğiniz kitap kapakları hem <a href="<?= SITE_URL; ?>/kitaplik" target="_blank" style="color:var(--adm-primary); font-weight:600;">/kitaplik</a> özel sayfasında hem de ana sayfada son eserlerin altında yatay vitrinde sergilenir.
      </p>
    </div>
    <div style="display:flex; gap:0.75rem;">
      <a href="<?= SITE_URL; ?>/kitaplik" target="_blank" class="btn btn-sm">
        <span>↗</span> Kitaplığı Önizle
      </a>
      <a href="<?= SITE_URL; ?>/admin/books.php?action=new" class="btn btn-accent btn-sm">
        <span>+</span> Yeni Kitap Ekle
      </a>
    </div>
  </div>

  <?php if ( ! empty( $msg ) ) : ?>
    <div class="alert alert-success" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:0.75rem 1.25rem; border-radius:var(--adm-radius-sm); margin-bottom:1.25rem;">
      ✓ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?>
    </div>
  <?php endif; ?>

  <?php
  $books = $db->query( "SELECT * FROM books ORDER BY order_num ASC, id DESC" )->fetchAll();
  ?>

  <div class="card">
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:60px;">Kapak</th>
            <th>Kitap Adı</th>
            <th>Yazar</th>
            <th>Baskı / Yıl</th>
            <th>Fiyat</th>
            <th>Yönlendirme Linki</th>
            <th>Sıra</th>
            <th style="text-align:right;">İşlemler</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( ! empty( $books ) ) : ?>
            <?php foreach ( $books as $b ) : ?>
              <tr>
                <td>
                  <?php if ( ! empty( $b['cover_image'] ) ) : ?>
                    <img src="<?= htmlspecialchars( $b['cover_image'] ); ?>" alt="Kapak" style="width:40px; height:56px; object-fit:cover; border-radius:3px; box-shadow:0 2px 5px rgba(0,0,0,0.15);" />
                  <?php else : ?>
                    <div style="width:40px; height:56px; background:#e2e8f0; border-radius:3px; display:flex; align-items:center; justify-content:center; font-size:0.6rem; color:#64748b;">
                      KAPAKSIZ
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="<?= SITE_URL; ?>/admin/books.php?action=edit&id=<?= $b['id']; ?>" style="color:var(--adm-text); font-weight:700; text-decoration:none;">
                    <?= htmlspecialchars( $b['title'], ENT_QUOTES, 'UTF-8' ); ?>
                  </a>
                  <?php if ( ! empty( $b['description'] ) ) : ?>
                    <div style="font-size:0.75rem; color:var(--adm-muted); max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      <?= htmlspecialchars( $b['description'], ENT_QUOTES, 'UTF-8' ); ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="font-weight:500;"><?= htmlspecialchars( $b['author'] ?: '—', ENT_QUOTES, 'UTF-8' ); ?></span>
                </td>
                <td style="font-size:0.8rem; color:var(--adm-muted);">
                  <?= htmlspecialchars( $b['publish_year'] ?: '—', ENT_QUOTES, 'UTF-8' ); ?>
                </td>
                <td>
                  <span class="pill-badge pill-purple" style="font-size:0.75rem;">
                    <?= htmlspecialchars( $b['price'] ?: '—', ENT_QUOTES, 'UTF-8' ); ?>
                  </span>
                </td>
                <td>
                  <?php if ( ! empty( $b['buy_url'] ) && $b['buy_url'] !== '#' ) : ?>
                    <a href="<?= htmlspecialchars( $b['buy_url'], ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" rel="noopener" style="font-size:0.78rem; color:var(--adm-accent); text-decoration:none;" title="<?= htmlspecialchars( $b['buy_url'], ENT_QUOTES, 'UTF-8' ); ?>">
                      Linke Git ↗
                    </a>
                  <?php else : ?>
                    <span style="color:var(--adm-muted); font-size:0.78rem;">Tanımsız (#)</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="pill-badge pill-gray" style="font-size:0.75rem;">
                    #<?= (int) $b['order_num']; ?>
                  </span>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= SITE_URL; ?>/admin/books.php?action=edit&id=<?= $b['id']; ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;">
                    Düzenle
                  </a>
                  <a href="<?= SITE_URL; ?>/admin/books.php?action=delete&id=<?= $b['id']; ?>" onclick="return confirm('Bu kitabı kitaplıktan silmek istediğinize emin misiniz?')" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem; color:#ef4444;">
                    Sil
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:3rem; color:var(--adm-muted);">
                Kitaplıkta henüz kayıtlı bir kitap bulunmuyor. Yeni bir kitap eklemek için yukarıdaki butonu kullanabilirsiniz.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
