<?php
/**
 * Pratfall Standalone - Eserler / Yazılar Yönetimi
 */

$page_title = 'Yazılar Yönetimi';
$active_tab = 'posts';

$db = Database::get();
$action = $_GET['action'] ?? 'list';
$msg = '';
$err = '';

// Yazar Listesi
$authors = $db->query( "SELECT id, name FROM authors ORDER BY name ASC" )->fetchAll();

// 1. Silme İşlemi
if ( $action === 'delete' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "DELETE FROM posts WHERE id = ?" );
    $stmt->execute( [ $id ] );
    header( 'Location: ' . SITE_URL . '/admin/posts?msg=deleted' );
    exit;
}

// 2. Kaydetme / Güncelleme İşlemi
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $id             = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $title          = trim( $_POST['title'] ?? '' );
        $author_id      = (int) ( $_POST['author_id'] ?? 0 );
        $category       = trim( $_POST['category'] ?? 'Deneme' );
        $excerpt        = trim( $_POST['excerpt'] ?? '' );
        $content        = trim( $_POST['content'] ?? '' );
        $featured_image = trim( $_POST['featured_image'] ?? '' );
        $status         = trim( $_POST['status'] ?? 'published' );

        // Görsel Yüklendiyse Kaydet
        if ( isset( $_FILES['image_file'] ) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK ) {
            $ext = strtolower( pathinfo( $_FILES['image_file']['name'], PATHINFO_EXTENSION ) );
            if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ] ) ) {
                $file_name = 'post_' . uniqid() . '.' . $ext;
                $save_path = PATH_UPLOADS . '/' . $file_name;
                if ( move_uploaded_file( $_FILES['image_file']['tmp_name'], $save_path ) ) {
                    $featured_image = SITE_URL . '/uploads/' . $file_name;
                }
            }
        }

        // Okuma Süresi ve Otomatik Özet
        $clean_words = preg_split( '/\s+/', trim( strip_tags( $content ) ) );
        $word_count  = count( $clean_words );
        $reading_time = max( 1, (int) ceil( $word_count / 180 ) );

        if ( empty( $excerpt ) ) {
            $excerpt = implode( ' ', array_slice( $clean_words, 0, 26 ) ) . '...';
        }

        if ( empty( $title ) ) {
            $err = 'Eser başlığı boş bırakılamaz.';
        } else {
            if ( $id > 0 ) {
                // Güncelle
                $stmt = $db->prepare( "
                    UPDATE posts SET 
                        author_id = ?, title = ?, category = ?, excerpt = ?, 
                        content = ?, featured_image = ?, reading_time = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                " );
                $stmt->execute( [ $author_id, $title, $category, $excerpt, $content, $featured_image, $reading_time, $status, $id ] );
                header( 'Location: ' . SITE_URL . '/admin/posts?msg=updated' );
                exit;
            } else {
                // Yeni Ekle
                $slug = Database::slugify( $title );
                // Benzersiz slug
                $base = $slug;
                $c = 1;
                while ( $db->query( "SELECT id FROM posts WHERE slug = '{$slug}'" )->fetch() ) {
                    $slug = $base . '-' . $c;
                    $c++;
                }

                $stmt = $db->prepare( "
                    INSERT INTO posts (author_id, title, slug, category, excerpt, content, featured_image, reading_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                " );
                $stmt->execute( [ $author_id, $title, $slug, $category, $excerpt, $content, $featured_image, $reading_time, $status ] );
                header( 'Location: ' . SITE_URL . '/admin/posts?msg=created' );
                exit;
            }
        }
    }
}

if ( isset( $_GET['msg'] ) ) {
    if ( $_GET['msg'] === 'created' ) $msg = 'Yeni eser başarıyla yayınlandı.';
    if ( $_GET['msg'] === 'updated' ) $msg = 'Eser başarıyla güncellendi.';
    if ( $_GET['msg'] === 'deleted' ) $msg = 'Eser silindi.';
}

require PATH_ADMIN . '/layout/header.php';
?>

<?php if ( $action === 'new' || $action === 'edit' ) : ?>
  <?php
  $post = [
      'id'             => 0,
      'title'          => '',
      'author_id'      => 0,
      'category'       => 'Deneme',
      'excerpt'        => '',
      'content'        => '',
      'featured_image' => '',
      'status'         => 'published',
  ];

  if ( $action === 'edit' && isset( $_GET['id'] ) ) {
      $id = (int) $_GET['id'];
      $stmt = $db->prepare( "SELECT * FROM posts WHERE id = ?" );
      $stmt->execute( [ $id ] );
      $found = $stmt->fetch();
      if ( $found ) $post = $found;
  }
  ?>

  <div class="admin-header-row">
    <div>
      <h1 class="admin-title"><?= $action === 'edit' ? 'Metni Düzenle' : '+ Yeni Metin Ekle'; ?></h1>
      <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
        Eser başlığı, yazarı ve editoryal içeriği hazırlayın.
      </p>
    </div>
    <a href="<?= SITE_URL; ?>/admin/posts" class="btn">← Listeye Dön</a>
  </div>

  <?php if ( ! empty( $err ) ) : ?>
    <div class="alert alert-error"><?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= SITE_URL; ?>/admin/posts" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
    <input type="hidden" name="id" value="<?= $post['id']; ?>" />

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem; align-items:start;">
      
      <!-- Sol: İçerik Alanları -->
      <div class="admin-card">
        <div class="form-group">
          <label class="form-label" for="title">Eser Başlığı *</label>
          <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>" required placeholder="Örn: Kelimelerin Tozu" style="font-size:1.1rem; font-weight:700;" />
        </div>

        <div class="form-group">
          <label class="form-label" for="excerpt">Kısa Özet (Excerpt)</label>
          <textarea name="excerpt" id="excerpt" class="form-control" rows="2" placeholder="Boş bırakırsanız ilk cümlelerden otomatik türetilir."><?= htmlspecialchars( $post['excerpt'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="content">Eser Gövde Metni (HTML / Düz Metin) *</label>
          <textarea name="content" id="content" class="form-control" rows="18" style="font-family:var(--admin-font); line-height:1.7; font-size:1rem;" required placeholder="Metninizi buraya yazın veya yapıştırın..."><?= htmlspecialchars( $post['content'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
        </div>
      </div>

      <!-- Sağ: Yan Meta Panel -->
      <div>
        <div class="admin-card">
          <div class="form-group">
            <label class="form-label" for="author_id">Yazar Seçin</label>
            <select name="author_id" id="author_id" class="form-control">
              <option value="0">✦ Yazarsız / Ortak Mecmua Metni</option>
              <?php foreach ( $authors as $a ) : ?>
                <option value="<?= $a['id']; ?>" <?= $post['author_id'] == $a['id'] ? 'selected' : ''; ?>>
                  <?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div style="margin-top:0.35rem; font-size:0.75rem; font-family:var(--admin-mono); color:var(--admin-muted);">
              Yeni yazar eklemek için: <a href="<?= SITE_URL; ?>/admin/authors?action=new">Yazar Ekle</a>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="category">Edebi Tür / Kategori</label>
            <input type="text" name="category" id="category" class="form-control" value="<?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Deneme, Şiir, İnceleme..." />
          </div>

          <div class="form-group">
            <label class="form-label" for="status">Yayın Durumu</label>
            <select name="status" id="status" class="form-control">
              <option value="published" <?= $post['status'] === 'published' ? 'selected' : ''; ?>>● Yayında</option>
              <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : ''; ?>>○ Taslak</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Öne Çıkan Görsel</label>
            <?php if ( ! empty( $post['featured_image'] ) ) : ?>
              <div style="margin-bottom:0.75rem;">
                <img src="<?= $post['featured_image']; ?>" alt="Kapak" style="width:100%; height:130px; object-fit:cover; border:1px solid #000;" />
              </div>
            <?php endif; ?>
            <input type="file" name="image_file" accept="image/*" class="form-control" style="padding:0.4rem;" />
            <input type="text" name="featured_image" class="form-control" value="<?= htmlspecialchars( $post['featured_image'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="veya doğrudan görsel URL'si girin" style="margin-top:0.4rem; font-size:0.8rem;" />
          </div>

          <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.75rem;">
              <?= $action === 'edit' ? 'Değişiklikleri Kaydet ↗' : 'Eseri Yayınla ↗'; ?>
            </button>
          </div>
        </div>
      </div>

    </div>
  </form>

<?php else : ?>

  <!-- Liste Görünümü -->
  <div class="admin-header-row">
    <div>
      <h1 class="admin-title">Yayınlanan Tüm Eserler</h1>
      <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
        Mecmuada kayıtlı tüm denemeler, şiirler ve metinler.
      </p>
    </div>
    <div style="display:flex; gap:0.5rem;">
      <a href="<?= SITE_URL; ?>/admin/import-docx" class="btn" style="background:#ea580c; color:#fff;">📥 Word İçe Aktar</a>
      <a href="<?= SITE_URL; ?>/admin/posts?action=new" class="btn btn-primary">+ Yeni Metin Ekle</a>
    </div>
  </div>

  <?php if ( ! empty( $msg ) ) : ?>
    <div class="alert alert-success">✦ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <?php
  $all_posts = $db->query( "
      SELECT p.*, a.name AS author_name, a.slug AS author_slug 
      FROM posts p 
      LEFT JOIN authors a ON p.author_id = a.id 
      ORDER BY p.published_at DESC
  " )->fetchAll();
  ?>

  <div class="admin-card">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:50px;">Görsel</th>
          <th>Başlık</th>
          <th>Yazar</th>
          <th>Kategori</th>
          <th>Tarih</th>
          <th>Okuma</th>
          <th>Durum</th>
          <th style="text-align:right;">İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php if ( ! empty( $all_posts ) ) : ?>
          <?php foreach ( $all_posts as $p ) : ?>
            <tr>
              <td>
                <?php if ( ! empty( $p['featured_image'] ) ) : ?>
                  <img src="<?= $p['featured_image']; ?>" alt="" style="width:40px; height:32px; object-fit:cover; border:1px solid #000;" />
                <?php else : ?>
                  <div style="width:40px; height:32px; background:#ddd; display:flex; align-items:center; justify-content:center; font-size:0.6rem;">YOK</div>
                <?php endif; ?>
              </td>
              <td>
                <strong><a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $p['id']; ?>" style="color:var(--admin-text); text-decoration:none;"><?= htmlspecialchars( $p['title'], ENT_QUOTES, 'UTF-8' ); ?></a></strong>
              </td>
              <td><?= htmlspecialchars( $p['author_name'] ?? 'Yazarsız', ENT_QUOTES, 'UTF-8' ); ?></td>
              <td><span class="stamp-tag" style="font-size:0.7rem;"><?= htmlspecialchars( $p['category'], ENT_QUOTES, 'UTF-8' ); ?></span></td>
              <td style="font-family:var(--admin-mono); font-size:0.8rem;"><?= date( 'd.m.Y', strtotime( $p['published_at'] ) ); ?></td>
              <td><?= $p['reading_time']; ?> dk</td>
              <td>
                <span style="font-family:var(--admin-mono); font-size:0.75rem; color:<?= $p['status'] === 'published' ? 'var(--admin-green)' : 'var(--admin-muted)'; ?>; font-weight:700;">
                  <?= $p['status'] === 'published' ? '● Yayında' : '○ Taslak'; ?>
                </span>
              </td>
              <td style="text-align:right; white-space:nowrap;">
                <a href="<?= SITE_URL; ?>/eser/<?= $p['slug']; ?>" target="_blank" class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Gör ↗</a>
                <a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $p['id']; ?>" class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Düzenle</a>
                <a href="<?= SITE_URL; ?>/admin/posts?action=delete&id=<?= $p['id']; ?>" onclick="return confirm('Bu eseri silmek istediğinize emin misiniz?')" class="btn btn-danger" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Sil</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else : ?>
          <tr>
            <td colspan="8" style="text-align:center; padding:2rem; color:var(--admin-muted);">
              Kayıtlı metin bulunamadı.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
