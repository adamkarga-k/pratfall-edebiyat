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

// 0. AJAX ile Kitap Sıralamasını Güncelleme (Drag & Drop Reorder)
if ( $action === 'ajax_reorder' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    header( 'Content-Type: application/json; charset=UTF-8' );
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        echo json_encode( [ 'success' => false, 'message' => 'Güvenlik doğrulaması başarısız oldu (CSRF).' ] );
        exit;
    }

    $raw_order = $_POST['order'] ?? [];
    if ( is_string( $raw_order ) ) {
        $raw_order = json_decode( $raw_order, true );
    }

    if ( is_array( $raw_order ) && ! empty( $raw_order ) ) {
        $stmt = $db->prepare( "UPDATE books SET order_num = ? WHERE id = ?" );
        $idx = 1;
        foreach ( $raw_order as $book_id ) {
            $stmt->execute( [ $idx, (int) $book_id ] );
            $idx++;
        }
        echo json_encode( [ 'success' => true, 'message' => 'Kitap sıralaması başarıyla kaydedildi.' ] );
        exit;
    }

    echo json_encode( [ 'success' => false, 'message' => 'Geçersiz sıralama verisi gönderildi.' ] );
    exit;
}

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
            <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:0.3rem;">(İsteğe bağlı) Sayı girmek zorunda değilsiniz; kitap listesinden fareyle sürükleyip bırakarak veya oklarla da anında sıralayabilirsiniz.</div>
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

  <div style="background:#f8fafc; border:1px dashed var(--adm-border); border-radius:var(--adm-radius-sm); padding:0.75rem 1rem; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem; font-size:0.84rem; color:var(--adm-muted);">
    <div style="display:flex; align-items:center; gap:0.6rem;">
      <span style="font-size:1.2rem; color:var(--adm-accent);">⠿</span>
      <span><strong>Taşıyarak Sıralama:</strong> Kitapların vitrin sırasını değiştirmek için solundaki <strong>⠿</strong> simgesinden tutup yukarı/aşağı sürükleyin veya <strong>▲ / ▼</strong> oklarını kullanın. Sıralama anında otomatik kaydedilir.</span>
    </div>
    <div id="reorder-status-badge" style="display:none; font-size:0.78rem; font-weight:600; padding:0.3rem 0.75rem; border-radius:999px; transition:all 0.2s ease;"></div>
  </div>

  <div class="card">
    <div style="overflow-x:auto;">
      <table class="admin-table" id="books-sortable-table">
        <thead>
          <tr>
            <th style="width:52px; text-align:center;">Taşı</th>
            <th style="width:60px;">Kapak</th>
            <th>Kitap Adı</th>
            <th>Yazar</th>
            <th>Baskı / Yıl</th>
            <th>Fiyat</th>
            <th>Yönlendirme Linki</th>
            <th style="width:70px; text-align:center;">Sıra</th>
            <th style="text-align:right;">İşlemler</th>
          </tr>
        </thead>
        <tbody id="books-sortable-body">
          <?php if ( ! empty( $books ) ) : ?>
            <?php foreach ( $books as $b ) : ?>
              <tr class="sortable-book-row" draggable="true" data-id="<?= $b['id']; ?>" style="transition:background-color 0.15s ease;">
                <td style="text-align:center; vertical-align:middle; padding:0.4rem 0.25rem;">
                  <div style="display:inline-flex; align-items:center; justify-content:center; gap:0.35rem;">
                    <span class="drag-handle" style="cursor:grab; color:var(--adm-muted); user-select:none; font-size:1.25rem; line-height:1; padding:0.25rem; border-radius:3px;" title="Sürükleyip sırasını değiştirin">⠿</span>
                    <div style="display:inline-flex; flex-direction:column; gap:2px;">
                      <button type="button" class="btn-move-row btn-move-up" title="Yukarı taşı" style="border:none; background:transparent; cursor:pointer; padding:2px; line-height:1; font-size:0.68rem; color:var(--adm-muted); border-radius:2px;" onmouseover="this.style.color='var(--adm-accent)'" onmouseout="this.style.color='var(--adm-muted)'">▲</button>
                      <button type="button" class="btn-move-row btn-move-down" title="Aşağı taşı" style="border:none; background:transparent; cursor:pointer; padding:2px; line-height:1; font-size:0.68rem; color:var(--adm-muted); border-radius:2px;" onmouseover="this.style.color='var(--adm-accent)'" onmouseout="this.style.color='var(--adm-muted)'">▼</button>
                    </div>
                  </div>
                </td>
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
                <td style="text-align:center;">
                  <span class="pill-badge pill-gray book-order-badge" style="font-size:0.75rem; min-width:32px; display:inline-block;">
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
              <td colspan="9" style="text-align:center; padding:3rem; color:var(--adm-muted);">
                Kitaplıkta henüz kayıtlı bir kitap bulunmuyor. Yeni bir kitap eklemek için yukarıdaki butonu kullanabilirsiniz.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <style>
    .sortable-book-row.is-dragging {
      opacity: 0.45;
      background: #f1f5f9 !important;
      outline: 2px dashed var(--adm-accent);
    }
    .sortable-book-row.drag-over-top {
      border-top: 3px solid var(--adm-accent) !important;
    }
    .sortable-book-row.drag-over-bottom {
      border-bottom: 3px solid var(--adm-accent) !important;
    }
    .drag-handle:active {
      cursor: grabbing !important;
    }
  </style>

  <script>
  (function() {
    const tableBody = document.getElementById('books-sortable-body');
    const statusBadge = document.getElementById('reorder-status-badge');
    const csrfToken = <?= json_encode( Auth::csrf_token() ); ?>;
    const reorderUrl = <?= json_encode( SITE_URL . '/admin/books.php?action=ajax_reorder' ); ?>;
    let draggedRow = null;
    let saveTimeout = null;

    if (!tableBody) return;

    function showStatus(text, type) {
      if (!statusBadge) return;
      statusBadge.style.display = 'inline-block';
      statusBadge.textContent = text;
      if (type === 'loading') {
        statusBadge.style.background = '#e0f2fe';
        statusBadge.style.color = '#0369a1';
        statusBadge.style.border = '1px solid #bae6fd';
      } else if (type === 'success') {
        statusBadge.style.background = '#ecfdf5';
        statusBadge.style.color = '#047857';
        statusBadge.style.border = '1px solid #a7f3d0';
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
          statusBadge.style.display = 'none';
        }, 2500);
      } else if (type === 'error') {
        statusBadge.style.background = '#fef2f2';
        statusBadge.style.color = '#b91c1c';
        statusBadge.style.border = '1px solid #fecaca';
      }
    }

    function updateVisualOrderBadges() {
      const rows = tableBody.querySelectorAll('.sortable-book-row');
      rows.forEach((row, idx) => {
        const badge = row.querySelector('.book-order-badge');
        if (badge) {
          badge.textContent = '#' + (idx + 1);
        }
      });
    }

    function saveOrder() {
      updateVisualOrderBadges();
      const rows = tableBody.querySelectorAll('.sortable-book-row');
      const orderIds = Array.from(rows).map(row => row.getAttribute('data-id'));

      showStatus('⏳ Sıralama kaydediliyor...', 'loading');

      const formData = new FormData();
      formData.append('csrf_token', csrfToken);
      orderIds.forEach(id => formData.append('order[]', id));

      fetch(reorderUrl, {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showStatus('✓ Sıralama kaydedildi', 'success');
        } else {
          showStatus('✕ ' + (data.message || 'Hata oluştu'), 'error');
        }
      })
      .catch(err => {
        console.error(err);
        showStatus('✕ Bağlantı hatası', 'error');
      });
    }

    // Drag and Drop Olayları
    tableBody.addEventListener('dragstart', function(e) {
      const row = e.target.closest('.sortable-book-row');
      if (!row) return;
      draggedRow = row;
      row.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', row.getAttribute('data-id'));
    });

    tableBody.addEventListener('dragend', function(e) {
      if (draggedRow) {
        draggedRow.classList.remove('is-dragging');
        draggedRow = null;
      }
      tableBody.querySelectorAll('.sortable-book-row').forEach(r => {
        r.classList.remove('drag-over-top', 'drag-over-bottom');
      });
      saveOrder();
    });

    tableBody.addEventListener('dragover', function(e) {
      e.preventDefault();
      const targetRow = e.target.closest('.sortable-book-row');
      if (!targetRow || targetRow === draggedRow) return;

      const rect = targetRow.getBoundingClientRect();
      const isAfter = (e.clientY - rect.top) > (rect.height / 2);

      if (isAfter) {
        targetRow.after(draggedRow);
      } else {
        targetRow.before(draggedRow);
      }
    });

    // Yukarı / Aşağı Ok Butonları ile Taşıma
    tableBody.addEventListener('click', function(e) {
      const btnUp = e.target.closest('.btn-move-up');
      const btnDown = e.target.closest('.btn-move-down');

      if (btnUp) {
        e.preventDefault();
        const row = btnUp.closest('.sortable-book-row');
        const prev = row.previousElementSibling;
        if (prev && prev.classList.contains('sortable-book-row')) {
          prev.before(row);
          saveOrder();
        }
      } else if (btnDown) {
        e.preventDefault();
        const row = btnDown.closest('.sortable-book-row');
        const next = row.nextElementSibling;
        if (next && next.classList.contains('sortable-book-row')) {
          next.after(row);
          saveOrder();
        }
      }
    });

  })();
  </script>

<?php endif; ?>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
