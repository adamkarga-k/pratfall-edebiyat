<?php
/**
 * Pratfall Standalone - Yazarlar Yönetimi (Kimler Yazmış?)
 */

$page_title = 'Yazarlar Yönetimi';
$active_tab = 'authors';

$db = Database::get();
$action = $_GET['action'] ?? 'list';
$msg = '';
$err = '';

// 1. Silme
if ( $action === 'delete' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "DELETE FROM authors WHERE id = ?" );
    $stmt->execute( [ $id ] );
    header( 'Location: ' . SITE_URL . '/admin/authors?msg=deleted' );
    exit;
}

// 2. Kaydet / Güncelle
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $id        = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $name      = trim( $_POST['name'] ?? '' );
        $title     = trim( $_POST['title'] ?? '' );
        $quote     = trim( $_POST['quote'] ?? '' );
        $bio       = trim( $_POST['bio'] ?? '' );
        $photo     = trim( $_POST['photo'] ?? '' );
        $twitter   = trim( $_POST['twitter'] ?? '' );
        $instagram = trim( $_POST['instagram'] ?? '' );
        $website   = trim( $_POST['website'] ?? '' );

        // Fotoğraf Yüklendiyse
        if ( isset( $_FILES['photo_file'] ) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK ) {
            $ext = strtolower( pathinfo( $_FILES['photo_file']['name'], PATHINFO_EXTENSION ) );
            if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ] ) ) {
                $file_name = 'author_' . uniqid() . '.' . $ext;
                $save_path = PATH_UPLOADS . '/' . $file_name;
                if ( move_uploaded_file( $_FILES['photo_file']['tmp_name'], $save_path ) ) {
                    $photo = SITE_URL . '/uploads/' . $file_name;
                }
            }
        }

        if ( empty( $name ) ) {
            $err = 'Yazar adı boş bırakılamaz.';
        } else {
            if ( $id > 0 ) {
                $stmt = $db->prepare( "
                    UPDATE authors SET 
                        name = ?, title = ?, quote = ?, bio = ?, 
                        photo = ?, twitter = ?, instagram = ?, website = ?
                    WHERE id = ?
                " );
                $stmt->execute( [ $name, $title, $quote, $bio, $photo, $twitter, $instagram, $website, $id ] );
                header( 'Location: ' . SITE_URL . '/admin/authors?msg=updated' );
                exit;
            } else {
                $slug = Database::slugify( $name );
                $stmt = $db->prepare( "
                    INSERT INTO authors (name, slug, title, quote, bio, photo, twitter, instagram, website)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                " );
                $stmt->execute( [ $name, $slug, $title, $quote, $bio, $photo, $twitter, $instagram, $website ] );
                header( 'Location: ' . SITE_URL . '/admin/authors?msg=created' );
                exit;
            }
        }
    }
}

if ( isset( $_GET['msg'] ) ) {
    if ( $_GET['msg'] === 'created' ) $msg = 'Yeni yazar eklendi.';
    if ( $_GET['msg'] === 'updated' ) $msg = 'Yazar bilgileri güncellendi.';
    if ( $_GET['msg'] === 'deleted' ) $msg = 'Yazar silindi.';
}

require PATH_ADMIN . '/layout/header.php';
?>

<?php if ( $action === 'new' || $action === 'edit' ) : ?>
  <?php
  $author = [
      'id'        => 0,
      'name'      => '',
      'title'     => '',
      'quote'     => '',
      'bio'       => '',
      'photo'     => '',
      'twitter'   => '',
      'instagram' => '',
      'website'   => '',
  ];
  if ( $action === 'edit' && isset( $_GET['id'] ) ) {
      $stmt = $db->prepare( "SELECT * FROM authors WHERE id = ?" );
      $stmt->execute( [ (int) $_GET['id'] ] );
      $found = $stmt->fetch();
      if ( $found ) $author = $found;
  }
  ?>

  <div class="admin-header-row">
    <div>
      <h1 class="admin-title"><?= $action === 'edit' ? 'Yazarı Düzenle' : '+ Yeni Yazar Ekle'; ?></h1>
      <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
        Fotoğraf, aforizma ve biyografi zorunlu değildir. Fotoğraf eklenmezse otomatik editoryal monogram pulu oluşturulur.
      </p>
    </div>
    <a href="<?= SITE_URL; ?>/admin/authors" class="btn">← Listeye Dön</a>
  </div>

  <?php if ( ! empty( $err ) ) : ?>
    <div class="alert alert-error"><?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <div class="admin-card" style="max-width:800px;">
    <form method="POST" action="<?= SITE_URL; ?>/admin/authors" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
      <input type="hidden" name="id" value="<?= $author['id']; ?>" />

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem;">
        <div class="form-group">
          <label class="form-label" for="name">Yazar Adı Soyadı *</label>
          <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars( $author['name'], ENT_QUOTES, 'UTF-8' ); ?>" required placeholder="Örn: Nilgün Marmara" />
        </div>

        <div class="form-group">
          <label class="form-label" for="title">Unvan / Rol</label>
          <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars( $author['title'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Örn: Şair & Eleştirmen" />
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="quote">Yazarın Aforizması / Alıntısı (İsteğe Bağlı)</label>
        <textarea name="quote" id="quote" class="form-control" rows="2" placeholder="Yazarın masasında yer alacak vurucu bir edebi cümle..."><?= htmlspecialchars( $author['quote'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="bio">Biyografi (İsteğe Bağlı)</label>
        <textarea name="bio" id="bio" class="form-control" rows="4" placeholder="Yazar hakkında kısa editoryal not..."><?= htmlspecialchars( $author['bio'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Yazar Portresi (İsteğe Bağlı)</label>
        <?php if ( ! empty( $author['photo'] ) ) : ?>
          <div style="margin-bottom:0.75rem; display:flex; align-items:center; gap:1rem;">
            <img src="<?= $author['photo']; ?>" alt="" style="width:60px; height:60px; object-fit:cover; border:1px solid #000;" />
            <span style="font-size:0.8rem; color:var(--admin-muted);">Mevcut profil fotoğrafı</span>
          </div>
        <?php else : ?>
          <div style="margin-bottom:0.5rem; font-size:0.8rem; font-family:var(--admin-mono); color:var(--admin-muted);">
            ✦ Fotoğraf yüklemezseniz yazar adına özel Monogram Kaşe Pul otomatik gösterilir.
          </div>
        <?php endif; ?>
        <input type="file" name="photo_file" accept="image/*" class="form-control" style="padding:0.4rem;" />
        <input type="text" name="photo" class="form-control" value="<?= htmlspecialchars( $author['photo'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="veya görsel URL'si girin" style="margin-top:0.4rem; font-size:0.8rem;" />
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label class="form-label" for="twitter">𝕏 Twitter Kullanıcı Adı</label>
          <input type="text" name="twitter" id="twitter" class="form-control" value="<?= htmlspecialchars( $author['twitter'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="kullaniciadi" />
        </div>
        <div class="form-group">
          <label class="form-label" for="instagram">📷 Instagram</label>
          <input type="text" name="instagram" id="instagram" class="form-control" value="<?= htmlspecialchars( $author['instagram'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="kullaniciadi" />
        </div>
        <div class="form-group">
          <label class="form-label" for="website">🌐 Web / Substack</label>
          <input type="url" name="website" id="website" class="form-control" value="<?= htmlspecialchars( $author['website'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://..." />
        </div>
      </div>

      <div style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary" style="padding:0.75rem 1.5rem;">
          <?= $action === 'edit' ? 'Yazarı Güncelle ↗' : 'Yazarı Kaydet ↗'; ?>
        </button>
      </div>
    </form>
  </div>

<?php else : ?>

  <div class="admin-header-row">
    <div>
      <h1 class="admin-title">Kimler Yazmış? (Yazarlar Masası)</h1>
      <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
        Mecmuada adı geçen tüm yazarların yoklama listesi ve profilleri.
      </p>
    </div>
    <a href="<?= SITE_URL; ?>/admin/authors?action=new" class="btn btn-primary">+ Yeni Yazar Ekle</a>
  </div>

  <?php if ( ! empty( $msg ) ) : ?>
    <div class="alert alert-success">✦ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <?php
  $all_authors = $db->query( "
      SELECT a.*, COUNT(p.id) AS post_count 
      FROM authors a 
      LEFT JOIN posts p ON a.id = p.author_id AND p.status = 'published'
      GROUP BY a.id 
      ORDER BY a.name ASC
  " )->fetchAll();
  ?>

  <div class="admin-card">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:60px;">Portre</th>
          <th>Yazar Adı</th>
          <th>Unvan / Rol</th>
          <th>Aforizma</th>
          <th>Yayınlanan Eser</th>
          <th style="text-align:right;">İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $all_authors as $a ) : ?>
          <tr>
            <td>
              <?= Helpers::author_avatar( $a, 38 ); ?>
            </td>
            <td>
              <strong><a href="<?= SITE_URL; ?>/admin/authors?action=edit&id=<?= $a['id']; ?>" style="color:var(--admin-text); text-decoration:none;"><?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?></a></strong>
            </td>
            <td><?= htmlspecialchars( $a['title'] ?? '—', ENT_QUOTES, 'UTF-8' ); ?></td>
            <td style="max-width:240px; font-size:0.8rem; color:var(--admin-muted); font-style:italic; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              <?= ! empty( $a['quote'] ) ? '"' . htmlspecialchars( $a['quote'], ENT_QUOTES, 'UTF-8' ) . '"' : '—'; ?>
            </td>
            <td><strong><?= (int) $a['post_count']; ?></strong> eser</td>
            <td style="text-align:right; white-space:nowrap;">
              <a href="<?= SITE_URL; ?>/yazar/<?= $a['slug']; ?>" target="_blank" class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Masası ↗</a>
              <a href="<?= SITE_URL; ?>/admin/authors?action=edit&id=<?= $a['id']; ?>" class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Düzenle</a>
              <a href="<?= SITE_URL; ?>/admin/authors?action=delete&id=<?= $a['id']; ?>" onclick="return confirm('Bu yazarı silmek istediğinize emin misiniz?')" class="btn btn-danger" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Sil</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
