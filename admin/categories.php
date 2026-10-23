<?php
/**
 * Pratfall Standalone - Yazı Kategorileri Yönetimi
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Yazı Kategorileri';
$active_tab = 'categories';

$db = Database::get();
$action = $_GET['action'] ?? 'list';
$msg = '';
$err = '';

// 1. Silme İşlemi
if ( $action === 'delete' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "DELETE FROM categories WHERE id = ?" );
    $stmt->execute( [ $id ] );
    header( 'Location: ' . SITE_URL . '/admin/categories.php?msg=deleted' );
    exit;
}

// 2. Kaydet / Güncelle
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $id          = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $name        = trim( $_POST['name'] ?? '' );
        $slug        = trim( $_POST['slug'] ?? '' );
        $description = trim( $_POST['description'] ?? '' );
        $order_num   = (int) ( $_POST['order_num'] ?? 0 );

        if ( empty( $name ) ) {
            $err = 'Kategori adı zorunludur.';
        } else {
            if ( empty( $slug ) ) {
                $slug = Helpers::slugify( $name );
            }

            if ( $id > 0 ) {
                $stmt = $db->prepare( "UPDATE categories SET name = ?, slug = ?, description = ?, order_num = ? WHERE id = ?" );
                $stmt->execute( [ $name, $slug, $description, $order_num, $id ] );
                header( 'Location: ' . SITE_URL . '/admin/categories.php?msg=updated' );
                exit;
            } else {
                $stmt = $db->prepare( "INSERT INTO categories (name, slug, description, order_num) VALUES (?, ?, ?, ?)" );
                $stmt->execute( [ $name, $slug, $description, $order_num ] );
                header( 'Location: ' . SITE_URL . '/admin/categories.php?msg=created' );
                exit;
            }
        }
    }
}

if ( isset( $_GET['msg'] ) ) {
    if ( $_GET['msg'] === 'created' ) $msg = 'Yeni kategori başarıyla eklendi.';
    if ( $_GET['msg'] === 'updated' ) $msg = 'Kategori bilgileri güncellendi.';
    if ( $_GET['msg'] === 'deleted' ) $msg = 'Kategori silindi.';
}

// Düzenlenen kategori (varsa)
$edit_category = [ 'id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'order_num' => 0 ];
if ( $action === 'edit' && isset( $_GET['id'] ) ) {
    $stmt = $db->prepare( "SELECT * FROM categories WHERE id = ?" );
    $stmt->execute( [ (int) $_GET['id'] ] );
    $found = $stmt->fetch();
    if ( $found ) {
        $edit_category = $found;
    }
}

// Tüm Kategorileri ve Yazı Sayılarını Çek
$categories = $db->query( "
    SELECT c.*, 
           (SELECT COUNT(*) FROM posts p WHERE LOWER(p.category) = LOWER(c.name)) AS post_count
    FROM categories c
    ORDER BY c.order_num ASC, c.name ASC
" )->fetchAll();

require PATH_ADMIN . '/layout/header.php';
?>

<?php if ( ! empty( $msg ) ) : ?>
  <div class="alert alert-success">✓ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
<?php endif; ?>

<?php if ( ! empty( $err ) ) : ?>
  <div class="alert alert-danger">✕ <?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.1fr 2fr; gap:1.75rem; align-items:start;">
  
  <!-- SOL: KATEGORİ EKLE / DÜZENLE FORMU -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><?= $edit_category['id'] > 0 ? 'Kategoriyi Düzenle' : 'Yeni Kategori Ekle'; ?></h3>
    </div>
    <div class="card-body">
      <form method="POST" action="<?= SITE_URL; ?>/admin/categories.php">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
        <input type="hidden" name="id" value="<?= $edit_category['id']; ?>" />

        <div class="form-group">
          <label class="form-label" for="name">Kategori Adı *</label>
          <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars( $edit_category['name'], ENT_QUOTES, 'UTF-8' ); ?>" required placeholder="Örn: Deneme, Şiir, Felsefe..." />
        </div>

        <div class="form-group">
          <label class="form-label" for="slug">Kategori Kısa Adı (Slug)</label>
          <input type="text" name="slug" id="slug" class="form-control" value="<?= htmlspecialchars( $edit_category['slug'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Boş bırakırsanız otomatik üretilir" />
        </div>

        <div class="form-group">
          <label class="form-label" for="description">Açıklama</label>
          <textarea name="description" id="description" class="form-control" rows="3" placeholder="Bu edebi türe dair kısa açıklama..."><?= htmlspecialchars( $edit_category['description'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="order_num">Sıralama Sırası</label>
          <input type="number" name="order_num" id="order_num" class="form-control" value="<?= (int) $edit_category['order_num']; ?>" />
        </div>

        <div style="display:flex; gap:0.5rem; margin-top:1.5rem;">
          <button type="submit" class="btn btn-primary" style="flex:1; justify-content:center;">
            <?= $edit_category['id'] > 0 ? '✓ Değişiklikleri Kaydet' : '+ Kategori Ekle'; ?>
          </button>
          <?php if ( $edit_category['id'] > 0 ) : ?>
            <a href="<?= SITE_URL; ?>/admin/categories.php" class="btn btn-sm">İptal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- SAĞ: KATEGORİLER LİSTESİ -->
  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Mevcut Kategoriler (<?= count( $categories ); ?>)</h3>
      <span style="font-size:0.75rem; color:var(--adm-muted);">Yazı eklerken doğrudan bu listeden seçilir</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:40px;">Sıra</th>
            <th>Kategori Adı</th>
            <th>Kısa Ad (Slug)</th>
            <th>Eser Sayısı</th>
            <th style="text-align:right;">İşlemler</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( ! empty( $categories ) ) : ?>
            <?php foreach ( $categories as $cat ) : ?>
              <tr>
                <td style="color:var(--adm-muted); font-size:0.8rem; font-family:var(--adm-font-mono);">
                  #<?= (int) $cat['order_num']; ?>
                </td>
                <td>
                  <strong><?= htmlspecialchars( $cat['name'], ENT_QUOTES, 'UTF-8' ); ?></strong>
                  <?php if ( ! empty( $cat['description'] ) ) : ?>
                    <div style="font-size:0.75rem; color:var(--adm-muted); margin-top:2px;">
                      <?= htmlspecialchars( $cat['description'], ENT_QUOTES, 'UTF-8' ); ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="font-family:var(--adm-font-mono); font-size:0.8rem; color:var(--adm-subtle);">
                  <?= htmlspecialchars( $cat['slug'], ENT_QUOTES, 'UTF-8' ); ?>
                </td>
                <td>
                  <span class="pill-badge pill-gray" style="font-size:0.72rem;">
                    <?= (int) $cat['post_count']; ?> Eser
                  </span>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= SITE_URL; ?>/admin/categories.php?action=edit&id=<?= $cat['id']; ?>" class="btn btn-sm">
                    Düzenle
                  </a>
                  <a href="<?= SITE_URL; ?>/admin/categories.php?action=delete&id=<?= $cat['id']; ?>" onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')" class="btn btn-sm" style="color:#ef4444;">
                    Sil
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr>
              <td colspan="5" style="text-align:center; padding:2rem; color:var(--adm-muted);">
                Henüz kategori eklenmedi.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
