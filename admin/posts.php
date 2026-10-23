<?php
/**
 * Pratfall Standalone - Eserler / Yazılar Yönetimi & Zengin Editör
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Yazılar & Eserler';
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

// 2. Kaydet / Güncelle İşlemi
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu. Lütfen formu tekrar gönderin.';
    } else {
        $id              = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $title           = trim( $_POST['title'] ?? '' );
        $author_id       = (int) ( $_POST['author_id'] ?? 0 );
        $category        = trim( $_POST['category'] ?? 'Deneme' );
        $excerpt         = trim( $_POST['excerpt'] ?? '' );
        $content         = trim( $_POST['content'] ?? '' );
        $status          = trim( $_POST['status'] ?? 'published' );
        $featured_image  = trim( $_POST['featured_image'] ?? '' );

        if ( empty( $title ) ) {
            $err = 'Lütfen eser başlığını yazınız.';
        } else {
            // Slug üret
            $slug = Helpers::slugify( $title );
            
            // Slug çakışma kontrolü
            $stmt = $db->prepare( "SELECT id FROM posts WHERE slug = ? AND id != ?" );
            $stmt->execute( [ $slug, $id ] );
            if ( $stmt->fetch() ) {
                $slug .= '-' . time();
            }

            // Kısa özet otomatik oluşturma
            if ( empty( $excerpt ) ) {
                $excerpt = Helpers::excerpt( $content, 180 );
            }

            // Okuma süresi hesapla
            $reading_time = Helpers::reading_time( $content );

            // Öne çıkan görsel yükleme (Varsa)
            if ( isset( $_FILES['image_file'] ) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK ) {
                $upload_dir = PATH_UPLOADS . '/covers';
                if ( ! is_dir( $upload_dir ) ) {
                    @mkdir( $upload_dir, 0755, true );
                }
                $ext = strtolower( pathinfo( $_FILES['image_file']['name'], PATHINFO_EXTENSION ) );
                if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ] ) ) {
                    $new_name = 'post-' . time() . '-' . uniqid() . '.' . $ext;
                    $dest = $upload_dir . '/' . $new_name;
                    if ( move_uploaded_file( $_FILES['image_file']['tmp_name'], $dest ) ) {
                        $featured_image = SITE_URL . '/uploads/covers/' . $new_name;
                    }
                }
            }

            if ( $id > 0 ) {
                // Güncelle
                $stmt = $db->prepare( "
                    UPDATE posts 
                    SET title = ?, slug = ?, author_id = ?, category = ?, excerpt = ?, content = ?, featured_image = ?, reading_time = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                " );
                $stmt->execute( [
                    $title, $slug, ( $author_id > 0 ? $author_id : null ), $category, $excerpt, $content, $featured_image, $reading_time, $status, $id
                ] );
                $msg = 'Eser başarıyla güncellendi.';
            } else {
                // Yeni Ekle
                $stmt = $db->prepare( "
                    INSERT INTO posts (title, slug, author_id, category, excerpt, content, featured_image, reading_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                " );
                $stmt->execute( [
                    $title, $slug, ( $author_id > 0 ? $author_id : null ), $category, $excerpt, $content, $featured_image, $reading_time, $status
                ] );
                $id = (int) $db->lastInsertId();
                $msg = 'Yeni eser başarıyla yayınlandı.';
            }

            // Kaydettikten sonra listeye dön veya sayfada kal
            header( 'Location: ' . SITE_URL . '/admin/posts?msg=' . urlencode( $msg ) );
            exit;
        }
    }
}

// 3. Düzenleme için Eseri Getir
$post = [
    'id'             => 0,
    'title'          => '',
    'slug'           => '',
    'author_id'      => 0,
    'category'       => 'Deneme',
    'excerpt'        => '',
    'content'        => '',
    'featured_image' => '',
    'status'         => 'published',
];

if ( $action === 'edit' && isset( $_GET['id'] ) ) {
    $stmt = $db->prepare( "SELECT * FROM posts WHERE id = ?" );
    $stmt->execute( [ (int) $_GET['id'] ] );
    $found = $stmt->fetch();
    if ( $found ) {
        $post = $found;
    }
}

if ( isset( $_GET['msg'] ) ) {
    $msg = $_GET['msg'] === 'deleted' ? 'Eser başarıyla silindi.' : $_GET['msg'];
}

require PATH_ADMIN . '/layout/header.php';
?>

<?php if ( $action === 'new' || $action === 'edit' ) : ?>

  <!-- =========================================================================
       YENİ YAZI / YAZI DÜZENLEME FORMU & ZENGİN EDİTÖR
       ========================================================================= -->
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
    <div>
      <h2 style="margin:0; font-size:1.4rem; font-weight:700;">
        <?= $action === 'edit' ? 'Eseri Düzenle: ' . htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ) : 'Yeni Eser & Metin Ekle'; ?>
      </h2>
      <p style="margin:0.2rem 0 0 0; color:var(--adm-muted); font-size:0.85rem;">
        Görsel araç çubuğu ile alt başlıklar, altı çizili metinler, alıntılar ve edebi paragraflar ekleyebilirsiniz.
      </p>
    </div>
    <a href="<?= SITE_URL; ?>/admin/posts" class="btn btn-sm">← Yazı Listesine Dön</a>
  </div>

  <?php if ( ! empty( $err ) ) : ?>
    <div class="alert alert-danger">✕ <?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= SITE_URL; ?>/admin/posts" enctype="multipart/form-data" id="postForm">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
    <input type="hidden" name="id" value="<?= $post['id']; ?>" />

    <div style="display:grid; grid-template-columns: 2.2fr 1fr; gap:1.75rem; align-items:start;">
      
      <!-- SOL: BAŞLIK, ÖZET VE ZENGİN EDİTÖR -->
      <div>
        
        <div class="card">
          <div class="card-body">
            
            <div class="form-group">
              <label class="form-label" for="title">Eser Başlığı *</label>
              <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ); ?>" required placeholder="Örn: Kusursuzluğun Soğuk Yalnızlığı Üzerine" style="font-size:1.15rem; font-weight:700; padding:0.65rem 0.85rem;" />
            </div>

            <div class="form-group">
              <label class="form-label" for="excerpt">Kısa Giriş / Alt Başlık (Excerpt)</label>
              <textarea name="excerpt" id="excerpt" class="form-control" rows="2" placeholder="Giriş spotu veya kısa alıntı (Boş bırakırsanız ilk cümlelerden otomatik türetilir)"><?= htmlspecialchars( $post['excerpt'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
            </div>

            <!-- ZENGİN METİN EDİTÖRÜ (WYSIWYG TOOLBAR) -->
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Eser Gövde Metni (Zengin Biçimlendirme)</label>
              
              <div class="editor-wrapper">
                
                <!-- Araç Çubuğu -->
                <div class="editor-toolbar" id="editorToolbar">
                  
                  <!-- 1. Grup: Başlık Seviyeleri -->
                  <div class="editor-btn-group">
                    <button type="button" class="editor-btn" onclick="execCmd('formatBlock', '<h2>')" title="Alt Başlık (H2)">
                      <strong>H2</strong>
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('formatBlock', '<h3>')" title="Küçük Başlık (H3)">
                      <strong>H3</strong>
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('formatBlock', '<p>')" title="Normal Paragraf">
                      ¶ Paragraf
                    </button>
                  </div>

                  <!-- 2. Grup: Metin Biçimlendirmeleri (Kalın, İtalik, Altı Çizili) -->
                  <div class="editor-btn-group">
                    <button type="button" class="editor-btn" onclick="execCmd('bold')" title="Kalın (Ctrl+B)">
                      <strong>B</strong>
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('italic')" title="İtalik (Ctrl+I)">
                      <em>I</em>
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('underline')" title="Altı Çizili (Ctrl+U)" style="text-decoration:underline;">
                      <u>U</u>
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('strikeThrough')" title="Üstü Çizili">
                      <s>S</s>
                    </button>
                  </div>

                  <!-- 3. Grup: Edebi Alıntı & Listeler -->
                  <div class="editor-btn-group">
                    <button type="button" class="editor-btn" onclick="execCmd('formatBlock', '<blockquote>')" title="Alıntı / Manifesto Bloğu">
                      “ Alıntı
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('insertUnorderedList')" title="Madde İmleri (Liste)">
                      • Liste
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('insertOrderedList')" title="Numaralı Liste">
                      1. Liste
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('insertHorizontalRule')" title="Ayırıcı Edebi Çizgi">
                      — Çizgi
                    </button>
                  </div>

                  <!-- 4. Grup: Bağlantı & Temizle -->
                  <div class="editor-btn-group">
                    <button type="button" class="editor-btn" onclick="insertLink()" title="Bağlantı (Link) Ekle">
                      🔗 Link
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('unlink')" title="Bağlantıyı Kaldır">
                      ✕ Link Sil
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('removeFormat')" title="Tüm Biçimlendirmeyi Temizle">
                      🧹
                    </button>
                  </div>

                  <!-- 5. Grup: HTML / Görsel Mod Değiştirici -->
                  <div style="margin-left:auto;">
                    <button type="button" class="editor-btn" id="toggleCodeBtn" onclick="toggleHtmlMode()" title="Doğrudan HTML kaynak kodunu göster/düzenle">
                      &lt;/&gt; HTML Modu
                    </button>
                  </div>

                </div>

                <!-- Görsel Düzenleme Alanı (ContentEditable) -->
                <div id="editorVisual" class="editor-content-area" contenteditable="true"><?= $post['content']; ?></div>

                <!-- Gizli / Senkronize Form Alanı -->
                <textarea name="content" id="content" style="display:none; width:100%; min-height:420px; font-family:var(--adm-font-mono); font-size:0.9rem; padding:1.5rem; border:none; outline:none; resize:vertical; line-height:1.6; background:#fafbfc;"><?= htmlspecialchars( $post['content'], ENT_QUOTES, 'UTF-8' ); ?></textarea>

              </div>
              <div style="margin-top:0.4rem; font-size:0.75rem; color:var(--adm-muted);">
                ✦ İpucu: Seçtiğiniz bir metni üst araç çubuğundaki butonlarla anında alt başlığa, alıntıya veya altı çiziliye dönüştürebilirsiniz.
              </div>
            </div>

          </div>
        </div>

      </div>

      <!-- SAĞ: YAYIN AYARLARI, YAZAR, GÖRSEL -->
      <div>
        
        <!-- Yayın Kartı -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Yayın & Durum</h3>
          </div>
          <div class="card-body">
            
            <div class="form-group">
              <label class="form-label" for="status">Yayın Durumu</label>
              <select name="status" id="status" class="form-control">
                <option value="published" <?= $post['status'] === 'published' ? 'selected' : ''; ?>>● Yayında (Canlı)</option>
                <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : ''; ?>>○ Taslak (Gizli)</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="author_id">Yazar Seçin</label>
              <select name="author_id" id="author_id" class="form-control">
                <option value="0">✦ Ortak Mecmua Metni</option>
                <?php foreach ( $authors as $a ) : ?>
                  <option value="<?= $a['id']; ?>" <?= $post['author_id'] == $a['id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="category">Edebi Tür / Kategori</label>
              <input type="text" name="category" id="category" class="form-control" value="<?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Deneme, Şiir, Manifesto, İnceleme..." />
            </div>

            <div style="margin-top:1.5rem;">
              <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.65rem;">
                <?= $action === 'edit' ? '✓ Değişiklikleri Kaydet' : '✓ Eseri Yayınla'; ?>
              </button>
            </div>

          </div>
        </div>

        <!-- Öne Çıkan Görsel Kartı -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Öne Çıkan Görsel</h3>
          </div>
          <div class="card-body">
            
            <?php if ( ! empty( $post['featured_image'] ) ) : ?>
              <div style="margin-bottom:0.75rem; border-radius:var(--adm-radius-sm); overflow:hidden; border:1px solid var(--adm-border);">
                <img src="<?= $post['featured_image']; ?>" alt="Kapak" style="width:100%; height:130px; object-fit:cover; display:block;" />
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label class="form-label">Bilgisayardan Yükle</label>
              <input type="file" name="image_file" accept="image/*" class="form-control" style="padding:0.35rem;" />
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Veya Görsel URL'si</label>
              <input type="text" name="featured_image" class="form-control" value="<?= htmlspecialchars( $post['featured_image'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://..." style="font-size:0.8rem;" />
            </div>

          </div>
        </div>

      </div>

    </div>
  </form>

  <!-- Editör JavaScript Mantığı -->
  <script>
    function execCmd(command, value = null) {
      document.getElementById('editorVisual').focus();
      document.execCommand(command, false, value);
      syncContent();
    }

    function insertLink() {
      const url = prompt('Bağlantı adresini giriniz (örn: https://...):', 'https://');
      if (url && url !== 'https://') {
        execCmd('createLink', url);
      }
    }

    function syncContent() {
      const visual = document.getElementById('editorVisual');
      const textarea = document.getElementById('content');
      if (visual && textarea) {
        textarea.value = visual.innerHTML;
      }
    }

    let isHtmlMode = false;
    function toggleHtmlMode() {
      const visual = document.getElementById('editorVisual');
      const textarea = document.getElementById('content');
      const btn = document.getElementById('toggleCodeBtn');
      isHtmlMode = !isHtmlMode;

      if (isHtmlMode) {
        textarea.value = visual.innerHTML;
        visual.style.display = 'none';
        textarea.style.display = 'block';
        btn.classList.add('active');
        btn.innerHTML = '👁️ Görsel Mod';
      } else {
        visual.innerHTML = textarea.value;
        textarea.style.display = 'none';
        visual.style.display = 'block';
        btn.classList.remove('active');
        btn.innerHTML = '&lt;/&gt; HTML Modu';
      }
    }

    document.getElementById('editorVisual')?.addEventListener('input', syncContent);
    document.getElementById('editorVisual')?.addEventListener('blur', syncContent);

    document.getElementById('postForm')?.addEventListener('submit', function() {
      if (!isHtmlMode) {
        syncContent();
      }
    });
  </script>

<?php else : ?>

  <!-- =========================================================================
       YAZILAR LİSTESİ (SAKİN, TEMİZ VE FERAH TABLO GÖRÜNÜMÜ)
       ========================================================================= -->
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
      <h2 style="margin:0; font-size:1.4rem; font-weight:700;">Yayınlanan Tüm Eserler</h2>
      <p style="margin:0.2rem 0 0 0; color:var(--adm-muted); font-size:0.85rem;">
        Mecmuada kayıtlı tüm denemeler, şiirler ve edebi metinler.
      </p>
    </div>
    <div style="display:flex; gap:0.5rem;">
      <a href="<?= SITE_URL; ?>/admin/import-docx" class="btn btn-accent btn-sm">
        <span>📥</span> Word İçe Aktar
      </a>
      <a href="<?= SITE_URL; ?>/admin/posts?action=new" class="btn btn-primary btn-sm">
        <span>+</span> Yeni Eser Ekle
      </a>
    </div>
  </div>

  <?php if ( ! empty( $msg ) ) : ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <?php
  $all_posts = $db->query( "
      SELECT p.*, a.name AS author_name, a.slug AS author_slug 
      FROM posts p 
      LEFT JOIN authors a ON p.author_id = a.id 
      ORDER BY p.published_at DESC
  " )->fetchAll();
  ?>

  <div class="card">
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:48px;">Görsel</th>
            <th>Eser Başlığı</th>
            <th>Yazar</th>
            <th>Tür / Kategori</th>
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
                    <img src="<?= $p['featured_image']; ?>" alt="" style="width:36px; height:36px; object-fit:cover; border-radius:4px; display:block;" />
                  <?php else : ?>
                    <div style="width:36px; height:36px; background:#f1f5f9; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:0.65rem; color:#94a3b8; font-weight:700;">
                      YOK
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $p['id']; ?>" style="color:var(--adm-text); font-weight:600; text-decoration:none;">
                    <?= htmlspecialchars( $p['title'], ENT_QUOTES, 'UTF-8' ); ?>
                  </a>
                </td>
                <td style="color:var(--adm-muted);"><?= htmlspecialchars( $p['author_name'] ?? 'Yazarsız', ENT_QUOTES, 'UTF-8' ); ?></td>
                <td>
                  <span class="pill-badge pill-gray" style="font-size:0.72rem;">
                    <?= htmlspecialchars( $p['category'], ENT_QUOTES, 'UTF-8' ); ?>
                  </span>
                </td>
                <td style="color:var(--adm-muted); font-size:0.8rem;"><?= date( 'd.m.Y', strtotime( $p['published_at'] ) ); ?></td>
                <td style="color:var(--adm-muted); font-size:0.8rem;"><?= $p['reading_time']; ?> dk</td>
                <td>
                  <span class="pill-badge <?= $p['status'] === 'published' ? 'pill-green' : 'pill-yellow'; ?>" style="font-size:0.72rem;">
                    <?= $p['status'] === 'published' ? '● Yayında' : '○ Taslak'; ?>
                  </span>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= SITE_URL; ?>/eser/<?= $p['slug']; ?>" target="_blank" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem;" title="Sitede İncele">
                    ↗ Gör
                  </a>
                  <a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $p['id']; ?>" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem;">
                    Düzenle
                  </a>
                  <a href="<?= SITE_URL; ?>/admin/posts?action=delete&id=<?= $p['id']; ?>" onclick="return confirm('Bu eseri silmek istediğinize emin misiniz?')" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem; color:#ef4444;">
                    Sil
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:3rem; color:var(--adm-muted);">
                Henüz kayıtlı bir metin bulunmuyor.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
