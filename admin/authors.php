<?php
/**
 * Pratfall Standalone - Yazarlar Yönetimi (Kimler Yazmış?)
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Yazarlar Yönetimi';
$active_tab = 'authors';

$db = Database::get();
$action = $_GET['action'] ?? 'list';
$msg = '';
$err = '';

// 0. Hızlı AJAX Yazar Ekleme (Yazı Ekleme Ekranından Doğrudan Çağrılır)
if ( $action === 'ajax_create' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    header( 'Content-Type: application/json; charset=UTF-8' );
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        echo json_encode( [ 'success' => false, 'message' => 'Güvenlik doğrulaması başarısız oldu.' ] );
        exit;
    }
    $name  = trim( (string) ( $_POST['name'] ?? '' ) );
    $title = trim( (string) ( $_POST['title'] ?? '' ) );
    if ( empty( $title ) ) {
        $title = 'Yazar';
    }

    if ( empty( $name ) ) {
        echo json_encode( [ 'success' => false, 'message' => 'Yazar adı boş bırakılamaz.' ] );
        exit;
    }

    $slug = Database::slugify( $name );
    $check = $db->prepare( "SELECT id FROM authors WHERE slug = ?" );
    $check->execute( [ $slug ] );
    if ( $check->fetch() ) {
        $slug .= '-' . time();
    }

    try {
        $stmt = $db->prepare( "
            INSERT INTO authors (name, slug, title, quote, bio, photo, twitter, instagram, website)
            VALUES (?, ?, ?, '', '', '', '', '', '')
        " );
        $stmt->execute( [ $name, $slug, $title ] );
        $new_id = (int) $db->lastInsertId();

        echo json_encode( [
            'success' => true,
            'id'      => $new_id,
            'name'    => $name,
            'title'   => $title,
            'slug'    => $slug,
            'message' => 'Yazar başarıyla oluşturuldu.'
        ] );
        exit;
    } catch ( Throwable $e ) {
        echo json_encode( [ 'success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage() ] );
        exit;
    }
}

// 1. Silme
if ( $action === 'delete' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "DELETE FROM authors WHERE id = ?" );
    $stmt->execute( [ $id ] );
    header( 'Location: ' . SITE_URL . '/admin/authors.php?msg=deleted' );
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

        // Fotoğraf Yüklendiyse (Otomatik WebP & Optimizasyon)
        if ( isset( $_FILES['photo_file'] ) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK ) {
            $opt_name = ImageOptimizer::optimize_uploaded_file( $_FILES['photo_file'], PATH_UPLOADS, 'author' );
            if ( $opt_name ) {
                $photo = SITE_URL . '/uploads/' . $opt_name;
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
                header( 'Location: ' . SITE_URL . '/admin/authors.php?msg=updated' );
                exit;
            } else {
                $slug = Database::slugify( $name );
                $stmt = $db->prepare( "
                    INSERT INTO authors (name, slug, title, quote, bio, photo, twitter, instagram, website)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                " );
                $stmt->execute( [ $name, $slug, $title, $quote, $bio, $photo, $twitter, $instagram, $website ] );
                header( 'Location: ' . SITE_URL . '/admin/authors.php?msg=created' );
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
      <h1 class="admin-title">Kimler Yazmış? (Yazarlar & Şairler Masası)</h1>
      <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
        Platformda adı geçen tüm yazar ve şairlerimizin yoklama listesi, unvanları ve profilleri.
      </p>
    </div>
    <a href="<?= SITE_URL; ?>/admin/authors.php?action=new" class="btn btn-primary">+ Yeni Yazar Ekle</a>
  </div>

  <?php if ( ! empty( $msg ) ) : ?>
    <div class="alert alert-success">✦ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <?php
  // 1. Tüm Yazarları Çek
  $all_authors_raw = $db->query( "
      SELECT a.*, COUNT(p.id) AS post_count 
      FROM authors a 
      LEFT JOIN posts p ON a.id = p.author_id AND p.status = 'published'
      GROUP BY a.id
  " )->fetchAll();

  // 2. Türkçe Karakterlere Tam Uyumlu A'dan Z'ye Alfabetik Sıralama
  Helpers::sort_turkish_alphabetical( $all_authors_raw, 'name' );

  // Toplam İstatistikler
  $total_all_count   = count( $all_authors_raw );
  $poet_total_count  = count( array_filter( $all_authors_raw, fn( $a ) => Helpers::is_poet( $a ) ) );
  $writer_total_count = $total_all_count - $poet_total_count;

  // 3. Filtre Parametreleri
  $filter_role   = $_GET['role'] ?? 'all'; // 'all', 'poet', 'writer'
  $filter_letter = trim( (string) ( $_GET['letter'] ?? '' ) );
  $search_query  = trim( (string) ( $_GET['q'] ?? '' ) );
  $current_paged = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
  $per_page      = 20;

  // Filtreleme Uygula
  $filtered_authors = array_filter( $all_authors_raw, function( $a ) use ( $filter_role, $filter_letter, $search_query ) {
      // Rol Filtresi
      if ( $filter_role === 'poet' && ! Helpers::is_poet( $a ) ) {
          return false;
      }
      if ( $filter_role === 'writer' && Helpers::is_poet( $a ) ) {
          return false;
      }

      // Baş Harf Filtresi (Türkçe İ/I uyumlu)
      if ( $filter_letter !== '' ) {
          $first_char = mb_substr( trim( $a['name'] ), 0, 1, 'UTF-8' );
          if ( mb_strtoupper( $first_char, 'UTF-8' ) !== mb_strtoupper( $filter_letter, 'UTF-8' ) ) {
              return false;
          }
      }

      // Arama Metni
      if ( $search_query !== '' ) {
          $q = mb_strtolower( $search_query, 'UTF-8' );
          $name  = mb_strtolower( $a['name'] ?? '', 'UTF-8' );
          $title = mb_strtolower( $a['title'] ?? '', 'UTF-8' );
          $bio   = mb_strtolower( $a['bio'] ?? '', 'UTF-8' );
          $quote = mb_strtolower( $a['quote'] ?? '', 'UTF-8' );
          if ( mb_strpos( $name, $q ) === false && 
               mb_strpos( $title, $q ) === false && 
               mb_strpos( $bio, $q ) === false && 
               mb_strpos( $quote, $q ) === false ) {
              return false;
          }
      }

      return true;
  } );
  $filtered_authors = array_values( $filtered_authors );

  // 4. Sayfalama Hesaplamaları
  $total_items = count( $filtered_authors );
  $total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
  if ( $current_paged > $total_pages && $total_items > 0 ) {
      $current_paged = $total_pages;
  }
  $offset = ( $current_paged - 1 ) * $per_page;
  $paged_authors = array_slice( $filtered_authors, $offset, $per_page );

  $has_active_filters = ( $filter_role !== 'all' || ! empty( $filter_letter ) || ! empty( $search_query ) );

  // URL Üretici
  $build_author_url = function( $overrides = [] ) {
      $params = $_GET;
      foreach ( $overrides as $k => $v ) {
          if ( $v === null || $v === '' || $v === 'all' ) {
              unset( $params[$k] );
          } else {
              $params[$k] = $v;
          }
      }
      return SITE_URL . '/admin/authors.php' . ( ! empty( $params ) ? '?' . http_build_query( $params ) : '' );
  };

  $turkish_letters = ['A', 'B', 'C', 'Ç', 'D', 'E', 'F', 'G', 'Ğ', 'H', 'I', 'İ', 'J', 'K', 'L', 'M', 'N', 'O', 'Ö', 'P', 'R', 'S', 'Ş', 'T', 'U', 'Ü', 'V', 'Y', 'Z'];
  ?>

  <!-- Filtre & Sekme Çubuğu -->
  <div class="card" style="margin-bottom:1.25rem; padding:1.15rem 1.25rem; background:#ffffff; border:1px solid var(--adm-border, #e2e8f0); border-radius:8px;">
    
    <!-- Üst Sekmeler: Rol Filtreleri -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem; padding-bottom:0.85rem; border-bottom:1px solid #f1f5f9;">
      <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
        <a href="<?= $build_author_url(['role' => 'all', 'paged' => 1]); ?>" 
           class="btn btn-sm <?= $filter_role === 'all' ? 'btn-primary' : 'btn-secondary'; ?>" 
           style="font-size:0.8rem; padding:0.35rem 0.8rem; font-weight:<?= $filter_role === 'all' ? '700' : '500'; ?>;">
          👥 Tümü (<?= $total_all_count; ?>)
        </a>
        <a href="<?= $build_author_url(['role' => 'poet', 'paged' => 1]); ?>" 
           class="btn btn-sm <?= $filter_role === 'poet' ? 'btn-primary' : 'btn-secondary'; ?>" 
           style="font-size:0.8rem; padding:0.35rem 0.8rem; font-weight:<?= $filter_role === 'poet' ? '700' : '500'; ?>; <?= $filter_role === 'poet' ? 'background:#b45309; border-color:#b45309; color:#fff;' : ''; ?>">
          📜 Şairler (<?= $poet_total_count; ?>)
        </a>
        <a href="<?= $build_author_url(['role' => 'writer', 'paged' => 1]); ?>" 
           class="btn btn-sm <?= $filter_role === 'writer' ? 'btn-primary' : 'btn-secondary'; ?>" 
           style="font-size:0.8rem; padding:0.35rem 0.8rem; font-weight:<?= $filter_role === 'writer' ? '700' : '500'; ?>;">
          ✍️ Yazarlar (<?= $writer_total_count; ?>)
        </a>
      </div>

      <!-- Arama Formu -->
      <form method="GET" action="<?= SITE_URL; ?>/admin/authors.php" style="display:flex; gap:0.4rem; align-items:center; flex:1; max-width:380px; min-width:240px;">
        <?php if ( $filter_role !== 'all' ) : ?>
          <input type="hidden" name="role" value="<?= htmlspecialchars( $filter_role, ENT_QUOTES, 'UTF-8' ); ?>" />
        <?php endif; ?>
        <?php if ( ! empty( $filter_letter ) ) : ?>
          <input type="hidden" name="letter" value="<?= htmlspecialchars( $filter_letter, ENT_QUOTES, 'UTF-8' ); ?>" />
        <?php endif; ?>
        <input type="text" name="q" value="<?= htmlspecialchars( $search_query, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="İsim, unvan veya aforizma ara..." class="form-control" style="font-size:0.85rem; padding:0.4rem 0.75rem;" />
        <button type="submit" class="btn btn-primary btn-sm" style="padding:0.4rem 0.75rem; white-space:nowrap;">
          🔍 Ara
        </button>
        <?php if ( $has_active_filters ) : ?>
          <a href="<?= SITE_URL; ?>/admin/authors.php" class="btn btn-sm" style="padding:0.4rem 0.6rem; color:#ef4444; white-space:nowrap;" title="Filtreleri Temizle">
            ✕ Temizle
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Alt Harf İndeksi (A-Z Baş Harfine Göre Hızlı Filtre) -->
    <div style="display:flex; align-items:center; gap:0.3rem; flex-wrap:wrap; font-size:0.75rem;">
      <span style="font-weight:700; color:var(--admin-muted, #64748b); margin-right:0.3rem;">Baş Harf:</span>
      <a href="<?= $build_author_url(['letter' => '', 'paged' => 1]); ?>" 
         style="padding:0.18rem 0.45rem; border-radius:4px; text-decoration:none; font-weight:700; <?= empty( $filter_letter ) ? 'background:#1e293b; color:#fff;' : 'background:#f1f5f9; color:#475569;'; ?>">
        Hepsi
      </a>
      <?php foreach ( $turkish_letters as $char ) : ?>
        <a href="<?= $build_author_url(['letter' => $char, 'paged' => 1]); ?>" 
           style="padding:0.18rem 0.45rem; border-radius:4px; text-decoration:none; font-weight:600; <?= $filter_letter === $char ? 'background:var(--adm-primary, #d94e34); color:#fff;' : 'background:#f8fafc; color:#334155; border:1px solid #e2e8f0;'; ?>">
          <?= $char; ?>
        </a>
      <?php endforeach; ?>
    </div>

  </div>

  <div class="admin-card">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:55px;">Portre</th>
          <th>Yazar / Şair Adı (A-Z)</th>
          <th>Rol / Unvan</th>
          <th>Aforizma & Biyografi</th>
          <th style="text-align:center;">Yayınlanan Eser</th>
          <th style="text-align:right;">İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php if ( ! empty( $paged_authors ) ) : ?>
          <?php foreach ( $paged_authors as $a ) : ?>
            <?php 
            $is_poet = Helpers::is_poet( $a ); 
            $initial = mb_strtoupper( mb_substr( trim( $a['name'] ), 0, 1, 'UTF-8' ), 'UTF-8' );
            ?>
            <tr>
              <td>
                <?= Helpers::author_avatar( $a, 40 ); ?>
              </td>
              <td>
                <div style="display:flex; align-items:center; gap:0.45rem;">
                  <span style="display:inline-block; width:22px; height:22px; line-height:22px; text-align:center; background:#f1f5f9; color:#475569; font-size:0.72rem; font-weight:700; border-radius:3px; border:1px solid #cbd5e1;">
                    <?= $initial; ?>
                  </span>
                  <strong>
                    <a href="<?= SITE_URL; ?>/admin/authors.php?action=edit&id=<?= $a['id']; ?>" style="color:var(--admin-text, #0f172a); text-decoration:none; font-size:0.95rem;">
                      <?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?>
                    </a>
                  </strong>
                </div>
              </td>
              <td>
                <div style="display:flex; flex-direction:column; gap:0.25rem; align-items:flex-start;">
                  <?php if ( $is_poet ) : ?>
                    <span class="pill-badge" style="background:#fef3c7; color:#92400e; font-size:0.72rem; font-weight:700; border:1px solid #fde68a; padding:0.15rem 0.5rem; border-radius:4px;">
                      📜 Şair
                    </span>
                  <?php else : ?>
                    <span class="pill-badge" style="background:#e0f2fe; color:#0369a1; font-size:0.72rem; font-weight:700; border:1px solid #bae6fd; padding:0.15rem 0.5rem; border-radius:4px;">
                      ✍️ Yazar
                    </span>
                  <?php endif; ?>
                  <span style="font-size:0.8rem; color:var(--admin-muted, #64748b);">
                    <?= htmlspecialchars( $a['title'] ?? '—', ENT_QUOTES, 'UTF-8' ); ?>
                  </span>
                </div>
              </td>
              <td style="max-width:240px; font-size:0.8rem; color:var(--admin-muted); font-style:italic; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?= ! empty( $a['quote'] ) ? '"' . htmlspecialchars( $a['quote'], ENT_QUOTES, 'UTF-8' ) . '"' : ( ! empty( $a['bio'] ) ? htmlspecialchars( mb_strimwidth( strip_tags( $a['bio'] ), 0, 60, '...' ), ENT_QUOTES, 'UTF-8' ) : '—' ); ?>
              </td>
              <td style="text-align:center;">
                <span class="pill-badge <?= (int)$a['post_count'] > 0 ? 'pill-green' : 'pill-yellow'; ?>" style="font-size:0.75rem; font-weight:700;">
                  <?= (int) $a['post_count']; ?> eser
                </span>
              </td>
              <td style="text-align:right; white-space:nowrap;">
                <a href="<?= Helpers::author_url( $a ); ?>" target="_blank" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;">
                  <?= $is_poet ? 'Şair Masası ↗' : 'Yazar Masası ↗'; ?>
                </a>
                <a href="<?= SITE_URL; ?>/admin/authors.php?action=edit&id=<?= $a['id']; ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;">
                  Düzenle
                </a>
                <a href="<?= SITE_URL; ?>/admin/authors.php?action=delete&id=<?= $a['id']; ?>" onclick="return confirm('Bu yazarı silmek istediğinize emin misiniz?')" class="btn btn-sm btn-danger" style="padding:0.25rem 0.55rem; font-size:0.75rem; color:#ef4444;">
                  Sil
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else : ?>
          <tr>
            <td colspan="6" style="text-align:center; padding:3rem; color:var(--admin-muted);">
              <?= $has_active_filters ? 'Filtreleme kriterlerine uygun yazar / şair bulunamadı.' : 'Henüz kayıtlı bir yazar veya şair bulunmuyor.'; ?>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Sayfalama ve İstatistik Çubuğu -->
    <?php if ( $total_items > 0 ) : ?>
      <div style="padding:1rem 1.25rem; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; background:#fafafa;">
        <div style="font-size:0.82rem; color:var(--admin-muted, #64748b);">
          Toplam <strong><?= number_format( $total_items ); ?></strong> yazar/şair arasından <strong><?= $offset + 1; ?> - <?= min( $offset + $per_page, $total_items ); ?></strong> arası gösteriliyor (Sayfa <?= $current_paged; ?> / <?= $total_pages; ?>)
        </div>

        <?php if ( $total_pages > 1 ) : ?>
          <div style="display:flex; align-items:center; gap:0.3rem;">
            <?php if ( $current_paged > 1 ) : ?>
              <a href="<?= $build_author_url(['paged' => 1]); ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="İlk Sayfa">« İlk</a>
              <a href="<?= $build_author_url(['paged' => $current_paged - 1]); ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="Önceki Sayfa">‹ Önceki</a>
            <?php endif; ?>

            <?php
            $start_page = max( 1, $current_paged - 2 );
            $end_page   = min( $total_pages, $current_paged + 2 );

            if ( $start_page > 1 ) {
                echo '<span style="padding:0 0.25rem; color:var(--admin-muted); font-size:0.75rem;">…</span>';
            }

            for ( $p = $start_page; $p <= $end_page; $p++ ) :
                if ( $p === $current_paged ) : ?>
                  <span class="btn btn-sm" style="padding:0.25rem 0.65rem; font-size:0.75rem; font-weight:700; background:#1e293b; color:#fff; cursor:default; pointer-events:none;">
                    <?= $p; ?>
                  </span>
                <?php else : ?>
                  <a href="<?= $build_author_url(['paged' => $p]); ?>" class="btn btn-sm" style="padding:0.25rem 0.65rem; font-size:0.75rem;">
                    <?= $p; ?>
                  </a>
                <?php endif;
            endfor;

            if ( $end_page < $total_pages ) {
                echo '<span style="padding:0 0.25rem; color:var(--admin-muted); font-size:0.75rem;">…</span>';
            }
            ?>

            <?php if ( $current_paged < $total_pages ) : ?>
              <a href="<?= $build_author_url(['paged' => $current_paged + 1]); ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="Sonraki Sayfa">Sonraki ›</a>
              <a href="<?= $build_author_url(['paged' => $total_pages]); ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="Son Sayfa">Son »</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

<?php endif; ?>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
