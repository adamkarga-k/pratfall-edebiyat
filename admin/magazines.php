<?php
/**
 * Pratfall Standalone - Dergilerimiz Vitrini Yönetimi
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Dergilerimiz Yönetimi';
$active_tab = 'magazines';

$db = Database::get();
$action = $_GET['action'] ?? 'list';
$msg = '';
$err = '';

// 1. Silme
if ( $action === 'delete' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "DELETE FROM magazines WHERE id = ?" );
    $stmt->execute( [ $id ] );
    header( 'Location: ' . SITE_URL . '/admin/magazines.php?msg=deleted' );
    exit;
}

// 2. Kaydet / Güncelle
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $id          = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $issue_no    = trim( $_POST['issue_no'] ?? '' );
        $title       = trim( $_POST['title'] ?? '' );
        $period      = trim( $_POST['period'] ?? '' );
        $price       = trim( $_POST['price'] ?? '' );
        $buy_url     = trim( $_POST['buy_url'] ?? '#' );
        $order_num   = (int) ( $_POST['order_num'] ?? 0 );
        $cover_image = trim( $_POST['cover_image'] ?? '' );

        // Kapak Resmi Yüklendiyse (Otomatik WebP & 1600px Optimizasyon)
        if ( isset( $_FILES['cover_file'] ) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK ) {
            $opt_name = ImageOptimizer::optimize_uploaded_file( $_FILES['cover_file'], PATH_UPLOADS, 'cover' );
            if ( $opt_name ) {
                $cover_image = SITE_URL . '/uploads/' . $opt_name;
            }
        }

        if ( empty( $issue_no ) || empty( $title ) ) {
            $err = 'Sayı numarası ve dergi başlığı zorunludur.';
        } else {
            if ( $id > 0 ) {
                $stmt = $db->prepare( "
                    UPDATE magazines SET 
                        issue_no = ?, title = ?, period = ?, price = ?, 
                        cover_image = ?, buy_url = ?, order_num = ?
                    WHERE id = ?
                " );
                $stmt->execute( [ $issue_no, $title, $period, $price, $cover_image, $buy_url, $order_num, $id ] );
                header( 'Location: ' . SITE_URL . '/admin/magazines.php?msg=updated' );
                exit;
            } else {
                $stmt = $db->prepare( "
                    INSERT INTO magazines (issue_no, title, period, price, cover_image, buy_url, order_num)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                " );
                $stmt->execute( [ $issue_no, $title, $period, $price, $cover_image, $buy_url, $order_num ] );
                header( 'Location: ' . SITE_URL . '/admin/magazines.php?msg=created' );
                exit;
            }
        }
    }
}

if ( isset( $_GET['msg'] ) ) {
    if ( $_GET['msg'] === 'created' ) $msg = 'Yeni dergi sayısı eklendi.';
    if ( $_GET['msg'] === 'updated' ) $msg = 'Dergi sayısı güncellendi.';
    if ( $_GET['msg'] === 'deleted' ) $msg = 'Dergi sayısı silindi.';
}

require PATH_ADMIN . '/layout/header.php';
?>

<?php if ( $action === 'new' || $action === 'edit' ) : ?>
  <?php
  $mag = [
      'id'          => 0,
      'issue_no'    => '',
      'title'       => '',
      'period'      => '',
      'price'       => '',
      'buy_url'     => '',
      'order_num'   => 0,
      'cover_image' => '',
  ];
  if ( $action === 'edit' && isset( $_GET['id'] ) ) {
      $stmt = $db->prepare( "SELECT * FROM magazines WHERE id = ?" );
      $stmt->execute( [ (int) $_GET['id'] ] );
      $found = $stmt->fetch();
      if ( $found ) $mag = $found;
  }
  ?>

  <div class="admin-header-row">
    <div>
      <h1 class="admin-title"><?= $action === 'edit' ? 'Dergi Sayısını Düzenle' : '+ Yeni Dergi Sayısı Ekle'; ?></h1>
      <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
        Kapak görseli, dönem ve satın alma bağlantısı ekleyin.
      </p>
    </div>
    <a href="<?= SITE_URL; ?>/admin/magazines" class="btn">← Listeye Dön</a>
  </div>

  <?php if ( ! empty( $err ) ) : ?>
    <div class="alert alert-error"><?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <div class="admin-card" style="max-width:750px;">
    <form method="POST" action="<?= SITE_URL; ?>/admin/magazines" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
      <input type="hidden" name="id" value="<?= $mag['id']; ?>" />

      <div style="display:grid; grid-template-columns: 1fr 2fr; gap:1.25rem;">
        <div class="form-group">
          <label class="form-label" for="issue_no">Sayı No *</label>
          <input type="text" name="issue_no" id="issue_no" class="form-control" value="<?= htmlspecialchars( $mag['issue_no'], ENT_QUOTES, 'UTF-8' ); ?>" required placeholder="Örn: Sayı #18" />
        </div>

        <div class="form-group">
          <label class="form-label" for="title">Sayı Başlığı / Teması *</label>
          <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars( $mag['title'], ENT_QUOTES, 'UTF-8' ); ?>" required placeholder="Örn: Kusursuzluğun İflası" />
        </div>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1.25rem;">
        <div class="form-group">
          <label class="form-label" for="period">Dönem / Yıl</label>
          <input type="text" name="period" id="period" class="form-control" value="<?= htmlspecialchars( $mag['period'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Güz / Kış 2026" />
        </div>

        <div class="form-group">
          <label class="form-label" for="price">Fiyat</label>
          <input type="text" name="price" id="price" class="form-control" value="<?= htmlspecialchars( $mag['price'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: 120 ₺" />
        </div>

        <div class="form-group">
          <label class="form-label" for="order_num">Sıralama (Sıra No)</label>
          <input type="number" name="order_num" id="order_num" class="form-control" value="<?= (int) $mag['order_num']; ?>" placeholder="0" />
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="buy_url">Satın Alma Linki (Shopier, Bionluk, Mağaza vb.)</label>
        <input type="url" name="buy_url" id="buy_url" class="form-control" value="<?= htmlspecialchars( $mag['buy_url'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://shopier.com/..." />
      </div>

      <div class="form-group">
        <label class="form-label">Dergi Kapak Görseli</label>
        <?php if ( ! empty( $mag['cover_image'] ) ) : ?>
          <div style="margin-bottom:0.75rem;">
            <img src="<?= $mag['cover_image']; ?>" alt="Kapak" style="width:100px; height:140px; object-fit:cover; border:2px solid #000;" />
          </div>
        <?php endif; ?>
        <input type="file" name="cover_file" accept="image/*" class="form-control" style="padding:0.4rem;" />
        <input type="text" name="cover_image" class="form-control" value="<?= htmlspecialchars( $mag['cover_image'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="veya doğrudan görsel URL'si girin" style="margin-top:0.4rem; font-size:0.8rem;" />
      </div>

      <div style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary" style="padding:0.75rem 1.5rem;">
          <?= $action === 'edit' ? 'Sayıyı Güncelle ↗' : 'Dergi Sayısını Ekle ↗'; ?>
        </button>
      </div>
    </form>
  </div>

<?php else : ?>

  <div class="admin-header-row">
    <div>
      <h1 class="admin-title">Dergilerimizin Sayıları (Yatay Vitrin)</h1>
      <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
        Ana sayfada yatay şerit halinde kayan basılı dergi sayılarınızın yönetimi.
      </p>
    </div>
    <a href="<?= SITE_URL; ?>/admin/magazines?action=new" class="btn btn-primary">+ Yeni Dergi Sayısı Ekle</a>
  </div>

  <?php if ( ! empty( $msg ) ) : ?>
    <div class="alert alert-success">✦ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <?php
  $magazines = $db->query( "SELECT * FROM magazines ORDER BY order_num ASC, id DESC" )->fetchAll();
  ?>

  <div class="admin-card">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:60px;">Kapak</th>
          <th>Sayı</th>
          <th>Başlık</th>
          <th>Dönem</th>
          <th>Fiyat</th>
          <th>Satın Alma Linki</th>
          <th style="text-align:right;">İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $magazines as $m ) : ?>
          <tr>
            <td>
              <?php if ( ! empty( $m['cover_image'] ) ) : ?>
                <img src="<?= $m['cover_image']; ?>" alt="" style="width:40px; height:56px; object-fit:cover; border:1px solid #000;" />
              <?php else : ?>
                <div style="width:40px; height:56px; background:#ddd; display:flex; align-items:center; justify-content:center; font-size:0.6rem;">YOK</div>
              <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars( $m['issue_no'], ENT_QUOTES, 'UTF-8' ); ?></strong></td>
            <td><?= htmlspecialchars( $m['title'], ENT_QUOTES, 'UTF-8' ); ?></td>
            <td><?= htmlspecialchars( $m['period'] ?? '—', ENT_QUOTES, 'UTF-8' ); ?></td>
            <td style="font-family:var(--admin-mono); font-weight:700; color:var(--admin-accent);"><?= htmlspecialchars( $m['price'] ?? '—', ENT_QUOTES, 'UTF-8' ); ?></td>
            <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:0.8rem; font-family:var(--admin-mono);">
              <a href="<?= htmlspecialchars( $m['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>" target="_blank" style="color:var(--admin-blue);">
                <?= htmlspecialchars( $m['buy_url'] ?? '#', ENT_QUOTES, 'UTF-8' ); ?>
              </a>
            </td>
            <td style="text-align:right; white-space:nowrap;">
              <a href="<?= SITE_URL; ?>/admin/magazines?action=edit&id=<?= $m['id']; ?>" class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Düzenle</a>
              <a href="<?= SITE_URL; ?>/admin/magazines?action=delete&id=<?= $m['id']; ?>" onclick="return confirm('Bu dergi sayısını silmek istediğinize emin misiniz?')" class="btn btn-danger" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Sil</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
