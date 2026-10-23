<?php
/**
 * Pratfall Standalone - Yazılar ve Edebi Metinler Yönetimi
 * 
 * Edebi tipografi araç çubuğu, şiir ve dipnot desteği,
 * manşet (öne çıkarma) yönetimi, yerel okunma sayacı ve sosyal medya alıntı kartı üreticisi içerir.
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Yazılar & Eserler';
$active_tab = 'posts';

$db = Database::get();
$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Yazar Listesi (A'dan Z'ye Türkçe Harf Sıralı)
$authors = $db->query( "SELECT id, name FROM authors" )->fetchAll();
Helpers::sort_turkish_alphabetical( $authors );

// Kategori Listesi
$all_categories = $db->query( "SELECT * FROM categories ORDER BY order_num ASC, name ASC" )->fetchAll();

// 0. AJAX Otomatik Üretilen Kapak Görselini Kaydet
if ( $action === 'ajax_save_cover' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    header( 'Content-Type: application/json; charset=UTF-8' );
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        echo json_encode( [ 'success' => false, 'message' => 'Güvenlik doğrulaması (CSRF) başarısız.' ] );
        exit;
    }
    $imageData = (string) ( $_POST['image_data'] ?? '' );
    if ( empty( $imageData ) ) {
        echo json_encode( [ 'success' => false, 'message' => 'Görsel verisi boş geldi.' ] );
        exit;
    }

    $opt_file = ImageOptimizer::optimize_base64_image( $imageData, PATH_UPLOADS . '/covers', 'kapak' );
    if ( $opt_file ) {
        $url = SITE_URL . '/uploads/covers/' . $opt_file;
        echo json_encode( [ 'success' => true, 'url' => $url, 'filename' => $opt_file ] );
    } else {
        echo json_encode( [ 'success' => false, 'message' => 'Görsel sunucuya kaydedilemedi.' ] );
    }
    exit;
}

// 1. Silme İşlemi
if ( $action === 'delete' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "DELETE FROM posts WHERE id = ?" );
    $stmt->execute( [ $id ] );
    header( 'Location: ' . SITE_URL . '/admin/posts.php?msg=' . urlencode( 'Eser başarıyla silindi.' ) );
    exit;
}

// 1.5. Manşet Durumunu Değiştir (1-Tıkla Öne Çıkar / İndir)
if ( $action === 'toggle_featured' && isset( $_GET['id'] ) ) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare( "SELECT is_featured FROM posts WHERE id = ?" );
    $stmt->execute( [ $id ] );
    $row = $stmt->fetch();
    $new_val = 0;
    if ( $row ) {
        $new_val = $row['is_featured'] ? 0 : 1;
        $db->prepare( "UPDATE posts SET is_featured = ? WHERE id = ?" )->execute( [ $new_val, $id ] );
    }
    header( 'Location: ' . SITE_URL . '/admin/posts.php?msg=' . urlencode( $new_val ? 'Eser ana sayfa manşetine taşındı.' : 'Eser manşetten kaldırıldı.' ) );
    exit;
}

// 2. Kaydet / Güncelle İşlemi
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $err = 'Güvenlik doğrulaması başarısız oldu. Lütfen formu tekrar gönderin.';
        $action = $id > 0 ? 'edit' : 'new';
    } else {
        $title           = trim( $_POST['title'] ?? '' );
        $author_id       = (int) ( $_POST['author_id'] ?? 0 );
        $category        = trim( $_POST['category'] ?? 'Deneme' );
        $tags            = trim( $_POST['tags'] ?? '' );
        $excerpt         = trim( $_POST['excerpt'] ?? '' );
        $content         = trim( $_POST['content'] ?? '' );
        if ( mb_stripos( $category, 'şiir' ) !== false || mb_stripos( $category, 'siir' ) !== false ) {
            $content = Helpers::format_poem_content( $content );
        }
        $status          = trim( $_POST['status'] ?? 'published' );
        $is_featured     = isset( $_POST['is_featured'] ) ? 1 : 0;
        $featured_image  = trim( $_POST['featured_image'] ?? '' );

        if ( empty( $title ) ) {
            $err = 'Lütfen eser başlığını yazınız.';
            $action = $id > 0 ? 'edit' : 'new';
        } else {
            try {
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

                // Öne çıkan görsel yükleme (Otomatik WebP & 1600px Optimizasyon)
                if ( isset( $_FILES['image_file'] ) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK ) {
                    $opt_file = ImageOptimizer::optimize_uploaded_file( $_FILES['image_file'], PATH_UPLOADS . '/covers', 'post' );
                    if ( $opt_file ) {
                        $featured_image = SITE_URL . '/uploads/covers/' . $opt_file;
                    }
                }

                if ( $id > 0 ) {
                    // Güncelle
                    $stmt = $db->prepare( "
                        UPDATE posts 
                        SET title = ?, slug = ?, author_id = ?, category = ?, tags = ?, excerpt = ?, content = ?, featured_image = ?, reading_time = ?, is_featured = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    " );
                    $stmt->execute( [
                        $title, $slug, ( $author_id > 0 ? $author_id : null ), $category, $tags, $excerpt, $content, $featured_image, $reading_time, $is_featured, $status, $id
                    ] );
                    $msg = 'Eser başarıyla güncellendi.';
                } else {
                    // Yeni Ekle
                    $stmt = $db->prepare( "
                        INSERT INTO posts (title, slug, author_id, category, tags, excerpt, content, featured_image, reading_time, is_featured, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    " );
                    $stmt->execute( [
                        $title, $slug, ( $author_id > 0 ? $author_id : null ), $category, $tags, $excerpt, $content, $featured_image, $reading_time, $is_featured, $status
                    ] );
                    $id = (int) $db->lastInsertId();
                    $msg = 'Yeni eser başarıyla yayınlandı.';
                }

                // Düzenleme ekranında kal ve yeşil başarı mesajını göster
                header( 'Location: ' . SITE_URL . '/admin/posts.php?action=edit&id=' . $id . '&msg=' . urlencode( $msg ) );
                exit;
            } catch ( Throwable $e ) {
                $err = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
                $action = $id > 0 ? 'edit' : 'new';
            }
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
    'tags'           => '',
    'excerpt'        => '',
    'content'        => '',
    'featured_image' => '',
    'is_featured'    => 0,
    'views_count'    => 0,
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

require PATH_ADMIN . '/layout/header.php';
?>

<?php if ( $action === 'new' || $action === 'edit' ) : ?>

  <!-- =========================================================================
       YAZI EKLE / DÜZENLE FORMU (ZENGİN EDİTÖR & GELİŞMİŞ TİPOGRAFİ)
       ========================================================================= -->
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
      <h2 style="margin:0; font-size:1.4rem; font-weight:700;">
        <?= $action === 'edit' ? 'Eseri Düzenle: ' . htmlspecialchars( $post['title'], ENT_QUOTES, 'UTF-8' ) : 'Yeni Eser / Metin Ekle'; ?>
      </h2>
      <p style="margin:0.2rem 0 0 0; color:var(--adm-muted); font-size:0.85rem;">
        Zengin biçimlendirme araç çubuğu, şiir bloğu ve dizgi seçenekleriyle edebi içeriğinizi oluşturun.
      </p>
    </div>
    <div style="display:flex; gap:0.5rem;">
      <?php if ( $action === 'edit' && ! empty( $post['slug'] ) ) : ?>
        <a href="<?= SITE_URL; ?>/eser/<?= $post['slug']; ?>" target="_blank" class="btn btn-secondary btn-sm">
          <span>↗</span> Sitede Gör
        </a>
      <?php endif; ?>
      <a href="<?= SITE_URL; ?>/admin/posts.php" class="btn btn-secondary btn-sm">
        <span>←</span> Yazılar Listesine Dön
      </a>
    </div>
  </div>

  <?php if ( ! empty( $msg ) ) : ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <?php if ( ! empty( $err ) ) : ?>
    <div class="alert alert-danger">✕ <?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= SITE_URL; ?>/admin/posts.php" enctype="multipart/form-data" id="postForm">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
    <input type="hidden" name="id" value="<?= $post['id']; ?>" />

    <div style="display:grid; grid-template-columns:1fr 320px; gap:1.5rem; align-items:start;">
      
      <!-- SOL BÖLÜM: BAŞLIK, ÖZET VE ZENGİN EDİTÖR -->
      <div>
        
        <div class="card" style="margin-bottom:1.5rem;">
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
              <label class="form-label">Eser Gövde Metni (Zengin Biçimlendirme & Edebi Tipografi)</label>
              
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

                  <!-- 2. Grup: Metin Biçimlendirmeleri -->
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
                    <button type="button" class="editor-btn" onclick="toggleAltUstCizgi()" title="Alt-Üst Çizme Efekti (Metnin hem altına hem üstüne editoryal çizgi çeker)" style="text-decoration:underline overline; text-decoration-thickness:1.5px; font-weight:700;">
                      ≍ Alt-Üst Çiz
                    </button>
                  </div>

                  <!-- 3. Grup: Edebi Hizalama & Dizgi -->
                  <div class="editor-btn-group">
                    <button type="button" class="editor-btn" onclick="applyAlignment('left')" title="Sola Yasla">
                      ⬅ Sola
                    </button>
                    <button type="button" class="editor-btn" onclick="applyAlignment('center')" title="Ortala">
                      ⬌ Ortala
                    </button>
                    <button type="button" class="editor-btn" onclick="applyAlignment('right')" title="Sağa Yasla">
                      ➡ Sağa
                    </button>
                    <button type="button" class="editor-btn" onclick="applyAlignment('justify')" title="İki Kenara Yasla (Justify)">
                      ☰ İki Yana
                    </button>
                    <button type="button" class="editor-btn" onclick="applyAlignment('justify', 'left')" title="İki Kenara Yaslı, Son Satır Solda (Kitap/Dergi Dizgisi)">
                      ☵ Son Sol
                    </button>
                    <button type="button" class="editor-btn" onclick="toggleIndent()" title="Paragraf Başı Girintisi">
                      ⇥ Girinti
                    </button>
                  </div>

                  <!-- 4. Grup: Edebi Alıntı, Şiir & Listeler -->
                  <div class="editor-btn-group">
                    <button type="button" class="editor-btn" onclick="execCmd('formatBlock', '<blockquote>')" title="Alıntı / Manifesto Bloğu">
                      “ Alıntı
                    </button>
                    <button type="button" class="editor-btn" onclick="insertPoemBlock()" title="Şiir Dizesi Bloğu (Mısra düzeni ve edebi girinti)">
                      ✦ Şiir Bloğu
                    </button>
                    <button type="button" class="editor-btn" onclick="insertFootnote()" title="Dipnot Açıklaması Ekle">
                      ¹ Dipnot
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

                  <!-- 5. Grup: Bağlantı & Temizle -->
                  <div class="editor-btn-group">
                    <button type="button" class="editor-btn" onclick="insertLink()" title="Bağlantı (Link) Ekle">
                      🔗 Link
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('unlink')" title="Bağlantıyı Kaldır">
                      ✕ Link Sil
                    </button>
                    <button type="button" class="editor-btn" onclick="execCmd('removeFormat')" title="Tüm Biçimlendirmeyi Temizle">
                      🧹 Temizle
                    </button>
                  </div>

                  <!-- 6. Grup: HTML / Görsel Mod Değiştirici -->
                  <div style="margin-left:auto;">
                    <button type="button" class="editor-btn" id="toggleCodeBtn" onclick="toggleHtmlMode()" title="Doğrudan HTML kaynak kodunu göster/düzenle">
                      &lt;/&gt; HTML Modu
                    </button>
                  </div>

                </div>

                <!-- Görsel Düzenleme Alanı (ContentEditable) -->
                <?php $is_post_poem = ( mb_stripos( $post['category'] ?? '', 'şiir' ) !== false || mb_stripos( $post['category'] ?? '', 'siir' ) !== false ); ?>
                <div id="editorVisual" class="editor-content-area <?= $is_post_poem ? 'is-poem' : ''; ?>" contenteditable="true"><?= $post['content']; ?></div>

                <!-- Gizli / Senkronize Form Alanı -->
                <textarea name="content" id="content" style="display:none; width:100%; min-height:420px; font-family:var(--adm-font-mono); font-size:0.9rem; padding:1.5rem; border:none; outline:none; resize:vertical; line-height:1.6; background:#fafbfc;"><?= htmlspecialchars( $post['content'], ENT_QUOTES, 'UTF-8' ); ?></textarea>

              </div>
              <div style="margin-top:0.4rem; font-size:0.75rem; color:var(--adm-muted);">
                ✦ İpucu: Seçtiğiniz bir metni üst araç çubuğundaki butonlarla anında alt başlığa, alıntıya veya şiir dizesine dönüştürebilirsiniz.
              </div>
            </div>

          </div>
        </div>

      </div>

      <!-- SAĞ SÜTUN: YAYIN AYARLARI, MANŞET, GÖRSEL -->
      <div>
        
        <!-- Yayın Kartı -->
        <div class="card" style="margin-bottom:1.5rem;">
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
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                <label class="form-label" for="author_id" style="margin:0;">Yazar Seçin</label>
                <button type="button" onclick="toggleQuickAuthorBox()" class="btn btn-sm" style="padding:0.15rem 0.5rem; font-size:0.72rem; color:var(--adm-primary); background:rgba(0,0,0,0.03); border:1px dashed var(--adm-primary); cursor:pointer;">
                  + Yeni Yazar Ekle
                </button>
              </div>

              <select name="author_id" id="author_id" class="form-control" onchange="updateAuthorEditLink()">
                <option value="0">✦ Ortak Platform Metni</option>
                <?php foreach ( $authors as $a ) : ?>
                  <option value="<?= $a['id']; ?>" <?= $post['author_id'] == $a['id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <!-- Hızlı Yazar Ekleme Açılır Kutusu (Inline Quick Add) -->
              <div id="quickAuthorBox" style="display:none; margin-top:0.75rem; padding:0.85rem; background:#f8fafc; border:1.5px solid var(--adm-border); border-radius:6px;">
                <div style="font-size:0.8rem; font-weight:700; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                  <span>👤 Hızlı Yeni Yazar Tanımla</span>
                  <button type="button" onclick="toggleQuickAuthorBox()" style="background:none; border:none; cursor:pointer; color:var(--adm-muted); font-size:1.1rem; line-height:1;">&times;</button>
                </div>
                
                <div style="margin-bottom:0.5rem;">
                  <input type="text" id="quickAuthorName" placeholder="Yazar Adı Soyadı (Örn: Cemal Süreya)" class="form-control" style="font-size:0.82rem; padding:0.4rem 0.6rem;" />
                </div>
                <div style="margin-bottom:0.6rem;">
                  <input type="text" id="quickAuthorTitle" placeholder="Ünvan / Alan (Örn: Şair & Deneme)" class="form-control" style="font-size:0.82rem; padding:0.4rem 0.6rem;" />
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center;">
                  <span id="quickAuthorMsg" style="font-size:0.72rem; color:var(--adm-muted);"></span>
                  <button type="button" onclick="saveQuickAuthor()" id="quickAuthorBtn" class="btn btn-primary btn-sm" style="padding:0.3rem 0.75rem; font-size:0.75rem;">
                    Kaydet & Seç ↗
                  </button>
                </div>
                <div style="font-size:0.7rem; color:var(--adm-muted); margin-top:0.4rem; line-height:1.3;">
                  ✦ Eklediğiniz yazar anında seçilecek; fotoğraf ve biyografisini dilediğiniz zaman <strong>Yazarlar</strong> sayfasından detaylandırabilirsiniz.
                </div>
              </div>

              <!-- Seçili Yazar Düzenleme Linki -->
              <div id="authorEditLinkContainer" style="margin-top:0.35rem; font-size:0.75rem;">
                <?php if ( ! empty( $post['author_id'] ) ) : ?>
                  <a href="<?= SITE_URL; ?>/admin/authors.php?action=edit&id=<?= $post['author_id']; ?>" target="_blank" id="authorEditLink" style="color:var(--adm-muted); text-decoration:none;">
                    ✎ Seçili yazarın detaylarını Yazarlar sayfasında düzenle ↗
                  </a>
                <?php else : ?>
                  <a href="#" target="_blank" id="authorEditLink" style="display:none; color:var(--adm-muted); text-decoration:none;">
                    ✎ Seçili yazarın detaylarını Yazarlar sayfasında düzenle ↗
                  </a>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                <label class="form-label" for="category" style="margin:0;">Edebi Tür / Kategori</label>
                <a href="<?= SITE_URL; ?>/admin/categories.php" target="_blank" style="font-size:0.75rem; color:var(--adm-primary); text-decoration:none;">+ Yönet / Ekle ↗</a>
              </div>
              <select name="category" id="category" class="form-control">
                <?php foreach ( $all_categories as $c ) : ?>
                  <option value="<?= htmlspecialchars( $c['name'], ENT_QUOTES, 'UTF-8' ); ?>" <?= strtolower( $post['category'] ) === strtolower( $c['name'] ) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars( $c['name'], ENT_QUOTES, 'UTF-8' ); ?>
                  </option>
                <?php endforeach; ?>
                <?php if ( ! empty( $post['category'] ) && ! in_array( strtolower( $post['category'] ), array_map( 'strtolower', array_column( $all_categories, 'name' ) ) ) ) : ?>
                  <option value="<?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?>" selected>
                    <?= htmlspecialchars( $post['category'], ENT_QUOTES, 'UTF-8' ); ?>
                  </option>
                <?php endif; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="tags">Etiketler</label>
              <input type="text" name="tags" id="tags" class="form-control" value="<?= htmlspecialchars( $post['tags'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>" placeholder="edebiyat, felsefe, şiir (virgülle ayırın)" style="font-size:0.85rem;" />
              <div style="font-size:0.72rem; color:var(--adm-muted); margin-top:0.25rem;">Eserle ilgili anahtar kavramlar.</div>
            </div>

            <!-- Manşet (Öne Çıkarılan Eser) Seçimi -->
            <div style="margin-top:1rem; padding:0.85rem; background:rgba(217,119,6,0.06); border:1px solid rgba(217,119,6,0.25); border-radius:6px;">
              <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-weight:600; font-size:0.85rem; margin:0;">
                <input type="checkbox" name="is_featured" value="1" <?= ! empty( $post['is_featured'] ) ? 'checked' : ''; ?> />
                <span style="color:#b45309;">✦ Ana Sayfa Manşeti Yap</span>
              </label>
              <div style="font-size:0.72rem; color:var(--adm-muted); margin-top:0.3rem; line-height:1.4;">
                Bu eser ana sayfanın en üstündeki dev vitrinde başyazı olarak sergilenir.
              </div>
            </div>

            <div style="margin-top:1.5rem;">
              <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.65rem;">
                <?= $action === 'edit' ? '✓ Değişiklikleri Kaydet' : '✓ Eseri Yayınla'; ?>
              </button>
            </div>

            <?php if ( $action === 'edit' ) : ?>
              <div style="margin-top:0.75rem;">
                <button type="button" onclick="openStoryModal(<?= htmlspecialchars(json_encode([ 'title' => $post['title'], 'author' => $authors[array_search($post['author_id'], array_column($authors, 'id'))]['name'] ?? 'Pratfall Yazarı', 'excerpt' => $post['excerpt'] ]), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-secondary" style="width:100%; justify-content:center; font-size:0.8rem;">
                  📱 Sosyal Medya / Alıntı Kartı Üret
                </button>
              </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Öne Çıkan Görsel Kartı -->
        <div class="card">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 class="card-title">Öne Çıkan Görsel</h3>
            <span class="pill-badge pill-green" style="font-size:0.65rem;">WebP Optimize</span>
          </div>
          <div class="card-body">
            
            <div id="featured_image_preview_box" style="<?= empty( $post['featured_image'] ) ? 'display:none;' : ''; ?> margin-bottom:0.75rem; border-radius:var(--adm-radius-sm); overflow:hidden; border:1px solid var(--adm-border); position:relative;">
              <img id="featured_image_preview_img" src="<?= $post['featured_image']; ?>" alt="Kapak" style="width:100%; height:130px; object-fit:cover; display:block;" />
            </div>

            <!-- 1. YOL: OTOMATİK KAPAK ÜRETİCİ BUTONU (YENİ) -->
            <div style="margin-bottom:1rem; padding:0.85rem; background:rgba(217, 78, 52, 0.05); border:1.5px dashed var(--adm-primary); border-radius:6px; text-align:center;">
              <button type="button" onclick="openCoverGeneratorModal()" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.55rem; font-size:0.84rem; font-weight:700;">
                🎨 Otomatik Kapak Görseli Üret
              </button>
              <div style="font-size:0.72rem; color:var(--adm-muted); margin-top:0.35rem; line-height:1.35;">
                Başlık ve yazar bilgisinden siteye tam uyumlu (16:9) edebi kapak görseli tasarlar.
              </div>
            </div>

            <!-- 2. YOL: BİLGİSAYARDAN DOSYA YÜKLEME -->
            <div class="form-group">
              <label class="form-label">Bilgisayardan Yükle (Otomatik Sıkıştırılır)</label>
              <input type="file" name="image_file" accept="image/*" class="form-control" style="padding:0.35rem;" />
            </div>

            <!-- 3. YOL: GÖRSEL URL'Sİ GİRME -->
            <div class="form-group" style="margin-bottom:0.75rem;">
              <label class="form-label">Veya Görsel URL'si</label>
              <input type="text" name="featured_image" id="featured_image_input" class="form-control" value="<?= htmlspecialchars( $post['featured_image'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://..." style="font-size:0.8rem;" oninput="updateCoverPreview(this.value)" />
            </div>

            <div>
              <a href="<?= SITE_URL; ?>/admin/media.php" target="_blank" class="btn btn-sm" style="width:100%; justify-content:center; font-size:0.76rem;">
                🖼️ Medya Kütüphanesini Aç ↗
              </a>
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

    function toggleAltUstCizgi() {
      const visual = document.getElementById('editorVisual');
      visual.focus();
      const selection = window.getSelection();
      if (!selection.rangeCount || selection.isCollapsed) {
        alert('Lütfen alt-üst çizgi efekti uygulamak istediğiniz kelime veya cümleyi seçin.');
        return;
      }

      const range = selection.getRangeAt(0);
      let container = selection.anchorNode;
      if (container.nodeType === 3) container = container.parentNode;

      const existing = container.closest('.alt-ust-cizgi');
      if (existing) {
        const parent = existing.parentNode;
        while (existing.firstChild) {
          parent.insertBefore(existing.firstChild, existing);
        }
        parent.removeChild(existing);
      } else {
        const span = document.createElement('span');
        span.className = 'alt-ust-cizgi';
        span.style.textDecoration = 'underline overline';
        span.style.textDecorationThickness = '1.5px';
        span.style.textUnderlineOffset = '3px';
        try {
          span.appendChild(range.extractContents());
          range.insertNode(span);
          selection.removeAllRanges();
          const newRange = document.createRange();
          newRange.selectNodeContents(span);
          selection.addRange(newRange);
        } catch(e) {
          document.execCommand('insertHTML', false, '<span class="alt-ust-cizgi" style="text-decoration:underline overline; text-decoration-thickness:1.5px; text-underline-offset:3px;">' + selection.toString() + '</span>');
        }
      }
      syncContent();
    }

    function applyAlignment(textAlign, textAlignLast = 'auto') {
      const visual = document.getElementById('editorVisual');
      visual.focus();
      const selection = window.getSelection();
      if (!selection.rangeCount) return;

      let node = selection.anchorNode;
      if (node.nodeType === 3) node = node.parentNode;

      let target = null;
      while (node && node !== visual) {
        if (/^(P|DIV|H1|H2|H3|H4|BLOCKQUOTE|LI)$/i.test(node.tagName)) {
          target = node;
          break;
        }
        node = node.parentNode;
      }

      if (!target) {
        document.execCommand('formatBlock', false, '<p>');
        node = window.getSelection().anchorNode;
        if (node.nodeType === 3) node = node.parentNode;
        while (node && node !== visual) {
          if (/^(P|DIV|H1|H2|H3|H4|BLOCKQUOTE|LI)$/i.test(node.tagName)) {
            target = node;
            break;
          }
          node = node.parentNode;
        }
      }

      if (target) {
        target.style.textAlign = textAlign;
        if (textAlign === 'center') {
          target.style.textAlignLast = 'center';
        } else if (textAlign === 'right') {
          target.style.textAlignLast = 'right';
        } else if (textAlign === 'left') {
          target.style.textAlignLast = 'left';
        } else if (textAlign === 'justify') {
          if (textAlignLast && textAlignLast !== 'auto') {
            target.style.textAlignLast = textAlignLast;
          } else {
            target.style.removeProperty('text-align-last');
          }
        }
      }
      syncContent();
    }

    function toggleIndent() {
      const visual = document.getElementById('editorVisual');
      visual.focus();
      const selection = window.getSelection();
      if (!selection.rangeCount) return;

      let node = selection.anchorNode;
      if (node.nodeType === 3) node = node.parentNode;

      let target = null;
      while (node && node !== visual) {
        if (/^(P|DIV|BLOCKQUOTE)$/i.test(node.tagName)) {
          target = node;
          break;
        }
        node = node.parentNode;
      }

      if (target) {
        if (target.style.textIndent === '2.5rem') {
          target.style.removeProperty('text-indent');
        } else {
          target.style.textIndent = '2.5rem';
        }
      } else {
        document.execCommand('formatBlock', false, '<p>');
        const p = window.getSelection().anchorNode.parentNode;
        if (p && p.style) {
          p.style.textIndent = '2.5rem';
        }
      }
      syncContent();
    }

    function insertLink() {
      const url = prompt('Bağlantı adresini giriniz (örn: https://...):', 'https://');
      if (url && url !== 'https://') {
        execCmd('createLink', url);
      }
    }

    function insertPoemBlock() {
      const visual = document.getElementById('editorVisual');
      visual.focus();
      const selection = window.getSelection();
      const text = selection.toString() || 'Bir mısra düşer geceye,\nKelimelerin tereddüdü kalır geriye...';
      const poemHtml = '<div class="poem-block" style="margin:1.75rem 0; padding:1.25rem 2rem; background:rgba(0,0,0,0.02); border-left:3px solid #8b1e0f; font-family:Georgia, serif; font-style:italic; line-height:2.1; white-space:pre-line;">' + text + '</div><p><br></p>';
      document.execCommand('insertHTML', false, poemHtml);
      syncContent();
    }

    function insertFootnote() {
      const visual = document.getElementById('editorVisual');
      visual.focus();
      const selection = window.getSelection();
      const text = selection.toString() || 'metin';
      const note = prompt('Bu kelime veya cümle için dipnot açıklaması yazın:', 'Örnek: Bu kavram 1928 tarihli ilk fanzinde geçmektedir.');
      if (note) {
        const fnHtml = '<span class="footnote-word" style="text-decoration:underline dotted #8b1e0f; cursor:help;" title="' + note.replace(/"/g, '&quot;') + '">' + text + '<sup style="color:#8b1e0f; font-weight:bold; font-size:0.75em;">[i]</sup></span>';
        document.execCommand('insertHTML', false, fnHtml);
        syncContent();
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

    try {
      document.execCommand('defaultParagraphSeparator', false, 'p');
    } catch(e) {}

    // Kategori Şiir seçildiğinde editör alanını otomatik Şiir Tipografisine (alt alta sıkı mısralar) uyarla
    const catSelect = document.getElementById('category');
    const visualEditor = document.getElementById('editorVisual');
    function updatePoemEditorMode() {
      if (!catSelect || !visualEditor) return;
      const catVal = catSelect.value.toLowerCase();
      if (catVal.includes('şiir') || catVal.includes('siir')) {
        visualEditor.classList.add('is-poem');
      } else {
        visualEditor.classList.remove('is-poem');
      }
    }
    catSelect?.addEventListener('change', updatePoemEditorMode);
    updatePoemEditorMode();

    document.getElementById('editorVisual')?.addEventListener('input', syncContent);
    document.getElementById('editorVisual')?.addEventListener('blur', syncContent);

    document.getElementById('postForm')?.addEventListener('submit', function() {
      if (!isHtmlMode) {
        syncContent();
      }
    });

    // Hızlı Yazar Ekleme Fonksiyonları
    function sortSelectOptions(selectEl, selectedId) {
      if (!selectEl) return;
      var options = Array.from(selectEl.options);
      var firstOpt = (options[0] && (options[0].value === '0' || options[0].value === '')) ? options.shift() : null;

      options.sort(function(a, b) {
        return a.text.localeCompare(b.text, 'tr');
      });

      selectEl.innerHTML = '';
      if (firstOpt) selectEl.appendChild(firstOpt);
      options.forEach(function(o) {
        if (selectedId && String(o.value) === String(selectedId)) {
          o.selected = true;
        }
        selectEl.appendChild(o);
      });
    }

    function toggleQuickAuthorBox() {
      var box = document.getElementById('quickAuthorBox');
      if (box.style.display === 'none' || box.style.display === '') {
        box.style.display = 'block';

        // Seçili kategoriye göre edebi unvanı otomatik belirle (Şiir -> Şair, diğerleri -> Yazar)
        var catSelect = document.getElementById('category');
        var currentCat = catSelect ? catSelect.value.trim().toLowerCase() : '';
        var titleInput = document.getElementById('quickAuthorTitle');
        if (titleInput) {
          if (currentCat.includes('şiir') || currentCat === 'siir') {
            titleInput.value = 'Şair';
          } else {
            titleInput.value = 'Yazar';
          }
        }

        document.getElementById('quickAuthorName').focus();
      } else {
        box.style.display = 'none';
      }
    }

    // Kategori değiştiğinde açık olan hızlı yazar unvanını da dinamik güncelle
    var catSelectEl = document.getElementById('category');
    if (catSelectEl) {
      catSelectEl.addEventListener('change', function() {
        var box = document.getElementById('quickAuthorBox');
        if (box && box.style.display === 'block') {
          var val = this.value.trim().toLowerCase();
          var titleInput = document.getElementById('quickAuthorTitle');
          if (titleInput) {
            titleInput.value = (val.includes('şiir') || val === 'siir') ? 'Şair' : 'Yazar';
          }
        }
      });
    }

    function updateAuthorEditLink() {
      var sel = document.getElementById('author_id');
      var link = document.getElementById('authorEditLink');
      if (!sel || !link) return;
      var val = sel.value;
      if (val && val !== '0') {
        link.href = '<?= SITE_URL; ?>/admin/authors.php?action=edit&id=' + val;
        link.style.display = 'inline-block';
      } else {
        link.style.display = 'none';
      }
    }

    function saveQuickAuthor() {
      var nameInput = document.getElementById('quickAuthorName');
      var titleInput = document.getElementById('quickAuthorTitle');
      var btn = document.getElementById('quickAuthorBtn');
      var msg = document.getElementById('quickAuthorMsg');

      var name = nameInput.value.trim();
      var catSelect = document.getElementById('category');
      var currentCat = catSelect ? catSelect.value.trim().toLowerCase() : '';
      var defaultTitle = (currentCat.includes('şiir') || currentCat === 'siir') ? 'Şair' : 'Yazar';
      var title = titleInput.value.trim() || defaultTitle;

      if (!name) {
        alert('Lütfen yazar adı ve soyadı giriniz.');
        nameInput.focus();
        return;
      }

      var origBtnText = btn.innerHTML;
      btn.innerHTML = 'Ekleniyor...';
      btn.disabled = true;
      msg.innerText = '';

      var formData = new FormData();
      formData.append('csrf_token', '<?= Auth::csrf_token(); ?>');
      formData.append('name', name);
      formData.append('title', title);

      fetch('<?= SITE_URL; ?>/admin/authors.php?action=ajax_create', {
        method: 'POST',
        body: formData
      })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          // 1. Yeni yazarı dropdown listesine ekle ve A-Z harf sırasına göre dizip seç
          var sel = document.getElementById('author_id');
          var opt = document.createElement('option');
          opt.value = data.id;
          opt.textContent = data.name + (data.title ? ' (' + data.title + ')' : '');
          sel.appendChild(opt);

          // A'dan Z'ye sırala ve yeni ekleneni seç
          sortSelectOptions(sel, data.id);

          // 2. Düzenleme bağlantısını anında aktif et
          updateAuthorEditLink();

          // 3. Başarı bildirimi ver ve kutuyu kapat
          msg.style.color = '#10b981';
          msg.innerText = '✓ Yazar eklendi & seçildi!';
          nameInput.value = '';
          titleInput.value = '';

          setTimeout(function() {
            toggleQuickAuthorBox();
            msg.innerText = '';
          }, 900);
        } else {
          msg.style.color = '#ef4444';
          msg.innerText = data.message || 'Hata oluştu.';
        }
      })
      .catch(function(err) {
        msg.style.color = '#ef4444';
        msg.innerText = 'Bağlantı hatası oluştu.';
      })
      .finally(function() {
        btn.innerHTML = origBtnText;
        btn.disabled = false;
      });
    }
  </script>

<?php else : ?>

  <!-- =========================================================================
       YAZILAR LİSTESİ (SAKİN, TEMİZ VE FERAH TABLO GÖRÜNÜMÜ)
       ========================================================================= -->
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
      <h2 style="margin:0; font-size:1.4rem; font-weight:700;">Yayınlanan Tüm Eserler</h2>
      <p style="margin:0.2rem 0 0 0; color:var(--adm-muted); font-size:0.85rem;">
        Platformda kayıtlı tüm denemeler, şiirler ve edebi metinler.
      </p>
    </div>
    <div style="display:flex; gap:0.5rem;">
      <a href="<?= SITE_URL; ?>/admin/import-docx.php" class="btn btn-accent btn-sm">
        <span>📥</span> Word İçe Aktar
      </a>
      <a href="<?= SITE_URL; ?>/admin/posts.php?action=new" class="btn btn-primary btn-sm">
        <span>+</span> Yeni Eser Ekle
      </a>
    </div>
  </div>

  <?php if ( ! empty( $msg ) ) : ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ); ?></div>
  <?php endif; ?>

  <?php
  // 1. Filtreler ve Sayfalama Parametreleri
  $search_query   = trim( $_GET['q'] ?? '' );
  $filter_cat     = trim( $_GET['category'] ?? '' );
  $filter_author  = (int) ( $_GET['author_id'] ?? 0 );
  $filter_status  = trim( $_GET['status'] ?? '' );
  $current_paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
  $per_page       = 20;

  // 2. Dinamik SQL Filtresi
  $where_parts = [ '1=1' ];
  $params = [];

  if ( ! empty( $search_query ) ) {
      $where_parts[] = "(p.title LIKE ? OR p.excerpt LIKE ? OR p.tags LIKE ?)";
      $term = '%' . $search_query . '%';
      $params[] = $term;
      $params[] = $term;
      $params[] = $term;
  }

  if ( ! empty( $filter_cat ) ) {
      $where_parts[] = "p.category = ?";
      $params[] = $filter_cat;
  }

  if ( $filter_author > 0 ) {
      $where_parts[] = "p.author_id = ?";
      $params[] = $filter_author;
  }

  if ( in_array( $filter_status, [ 'published', 'draft' ], true ) ) {
      $where_parts[] = "p.status = ?";
      $params[] = $filter_status;
  }

  $where_sql = implode( ' AND ', $where_parts );

  // 3. Toplam Kayıt Sayısı
  $count_stmt = $db->prepare( "SELECT COUNT(*) FROM posts p WHERE {$where_sql}" );
  $count_stmt->execute( $params );
  $total_posts = (int) $count_stmt->fetchColumn();
  $total_pages = max( 1, (int) ceil( $total_posts / $per_page ) );

  if ( $current_paged > $total_pages && $total_posts > 0 ) {
      $current_paged = $total_pages;
  }
  $offset = ( $current_paged - 1 ) * $per_page;

  // 4. Sayfalı Veriyi Çek
  $list_sql = "
      SELECT p.*, a.name AS author_name, a.slug AS author_slug 
      FROM posts p 
      LEFT JOIN authors a ON p.author_id = a.id 
      WHERE {$where_sql}
      ORDER BY p.published_at DESC, p.id DESC
      LIMIT {$per_page} OFFSET {$offset}
  ";
  $posts_stmt = $db->prepare( $list_sql );
  $posts_stmt->execute( $params );
  $all_posts = $posts_stmt->fetchAll();

  $has_active_filters = ( ! empty( $search_query ) || ! empty( $filter_cat ) || $filter_author > 0 || ! empty( $filter_status ) );

  // Sayfalama Linki Üretici
  $build_page_url = function( $p ) {
      $query = $_GET;
      $query['paged'] = $p;
      return SITE_URL . '/admin/posts.php?' . http_build_query( $query );
  };
  ?>

  <!-- Arama ve Filtreleme Araç Çubuğu -->
  <div class="card" style="margin-bottom:1.25rem; padding:1rem 1.25rem;">
    <form method="GET" action="<?= SITE_URL; ?>/admin/posts.php" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:center;">
      
      <!-- Arama Kutusu -->
      <div style="flex:1; min-width:200px;">
        <input type="text" name="q" value="<?= htmlspecialchars( $search_query, ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Başlık, etiket veya özet ara..." class="form-control" style="font-size:0.85rem;" />
      </div>

      <!-- Kategori Filtresi -->
      <div style="min-width:140px;">
        <select name="category" class="form-control" style="font-size:0.85rem;">
          <option value="">Tüm Kategoriler</option>
          <?php foreach ( $all_categories as $cat ) : ?>
            <option value="<?= htmlspecialchars( $cat['name'] ); ?>" <?= $filter_cat === $cat['name'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars( $cat['name'] ); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Yazar Filtresi -->
      <div style="min-width:140px;">
        <select name="author_id" class="form-control" style="font-size:0.85rem;">
          <option value="0">Tüm Yazarlar</option>
          <?php foreach ( $authors as $auth ) : ?>
            <option value="<?= $auth['id']; ?>" <?= $filter_author === (int) $auth['id'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars( $auth['name'] ); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Durum Filtresi -->
      <div style="min-width:110px;">
        <select name="status" class="form-control" style="font-size:0.85rem;">
          <option value="">Tüm Durumlar</option>
          <option value="published" <?= $filter_status === 'published' ? 'selected' : ''; ?>>● Yayında</option>
          <option value="draft" <?= $filter_status === 'draft' ? 'selected' : ''; ?>>○ Taslak</option>
        </select>
      </div>

      <!-- Butonlar -->
      <div style="display:flex; gap:0.4rem;">
        <button type="submit" class="btn btn-primary btn-sm" style="padding:0.45rem 0.9rem;">
          <span>🔍</span> Filtrele
        </button>
        <?php if ( $has_active_filters ) : ?>
          <a href="<?= SITE_URL; ?>/admin/posts.php" class="btn btn-sm" style="padding:0.45rem 0.75rem; color:#ef4444;" title="Filtreleri Temizle">
            ✕ Temizle
          </a>
        <?php endif; ?>
      </div>

    </form>
  </div>

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
            <th>Okunma</th>
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
                  <div style="display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap;">
                    <?php if ( ! empty( $p['is_featured'] ) ) : ?>
                      <span class="pill-badge pill-yellow" style="font-size:0.65rem; background:#fef3c7; color:#92400e; border:1px solid #f59e0b; padding:0.1rem 0.4rem; font-weight:700;">✦ MANŞET</span>
                    <?php endif; ?>
                    <a href="<?= SITE_URL; ?>/admin/posts.php?action=edit&id=<?= $p['id']; ?>" style="color:var(--adm-text); font-weight:600; text-decoration:none;">
                      <?= htmlspecialchars( $p['title'], ENT_QUOTES, 'UTF-8' ); ?>
                    </a>
                  </div>
                </td>
                <td style="color:var(--adm-muted);"><?= htmlspecialchars( $p['author_name'] ?? 'Yazarsız', ENT_QUOTES, 'UTF-8' ); ?></td>
                <td>
                  <span class="pill-badge pill-gray" style="font-size:0.72rem;">
                    <?= htmlspecialchars( $p['category'], ENT_QUOTES, 'UTF-8' ); ?>
                  </span>
                </td>
                <td style="color:var(--adm-muted); font-size:0.8rem;"><?= date( 'd.m.Y', strtotime( $p['published_at'] ) ); ?></td>
                <td style="font-family:var(--adm-font-mono); font-size:0.8rem; font-weight:600; color:var(--adm-muted);">
                  👁️ <?= number_format( (int) ($p['views_count'] ?? 0) ); ?>
                </td>
                <td>
                  <span class="pill-badge <?= $p['status'] === 'published' ? 'pill-green' : 'pill-yellow'; ?>" style="font-size:0.72rem;">
                    <?= $p['status'] === 'published' ? '● Yayında' : '○ Taslak'; ?>
                  </span>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= SITE_URL; ?>/admin/posts.php?action=toggle_featured&id=<?= $p['id']; ?>" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem; <?= ! empty( $p['is_featured'] ) ? 'color:#b45309; font-weight:bold; background:#fef3c7; border:1px solid #f59e0b;' : ''; ?>" title="Ana Sayfa Manşeti Yap / Kaldır">
                    <?= ! empty( $p['is_featured'] ) ? '★ Manşette' : '☆ Manşet Yap'; ?>
                  </a>
                  <button type="button" onclick="openStoryModal(<?= htmlspecialchars(json_encode([ 'title' => $p['title'], 'author' => $p['author_name'] ?? 'Pratfall Yazarı', 'excerpt' => $p['excerpt'] ]), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem;" title="Instagram / X için Alıntı Kartı Üret">
                    📱 Kart
                  </button>
                  <a href="<?= SITE_URL; ?>/eser/<?= $p['slug']; ?>" target="_blank" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem;" title="Sitede İncele">
                    ↗ Gör
                  </a>
                  <a href="<?= SITE_URL; ?>/admin/posts.php?action=edit&id=<?= $p['id']; ?>" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem;">
                    Düzenle
                  </a>
                  <a href="<?= SITE_URL; ?>/admin/posts.php?action=delete&id=<?= $p['id']; ?>" onclick="return confirm('Bu eseri silmek istediğinize emin misiniz?')" class="btn btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem; color:#ef4444;">
                    Sil
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:3rem; color:var(--adm-muted);">
                <?= $has_active_filters ? 'Arama kriterlerinize uygun eser bulunamadı.' : 'Henüz kayıtlı bir metin bulunmuyor.'; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Sayfalama ve İstatistik Çubuğu -->
    <?php if ( $total_posts > 0 ) : ?>
      <div style="padding:1rem 1.25rem; border-top:1px solid var(--adm-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; background:#fafafa;">
        <div style="font-size:0.82rem; color:var(--adm-muted);">
          Toplam <strong><?= number_format( $total_posts ); ?></strong> eserden <strong><?= $offset + 1; ?> - <?= min( $offset + $per_page, $total_posts ); ?></strong> arası gösteriliyor (Sayfa <?= $current_paged; ?> / <?= $total_pages; ?>)
        </div>

        <?php if ( $total_pages > 1 ) : ?>
          <div style="display:flex; align-items:center; gap:0.3rem;">
            <?php if ( $current_paged > 1 ) : ?>
              <a href="<?= $build_page_url( 1 ); ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="İlk Sayfa">« İlk</a>
              <a href="<?= $build_page_url( $current_paged - 1 ); ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="Önceki Sayfa">‹ Önceki</a>
            <?php endif; ?>

            <?php
            $start_page = max( 1, $current_paged - 2 );
            $end_page   = min( $total_pages, $current_paged + 2 );

            if ( $start_page > 1 ) {
                echo '<span style="padding:0 0.25rem; color:var(--adm-muted); font-size:0.75rem;">…</span>';
            }

            for ( $p = $start_page; $p <= $end_page; $p++ ) :
                if ( $p === $current_paged ) : ?>
                  <span class="btn btn-sm btn-accent" style="padding:0.25rem 0.65rem; font-size:0.75rem; font-weight:700; cursor:default; pointer-events:none;">
                    <?= $p; ?>
                  </span>
                <?php else : ?>
                  <a href="<?= $build_page_url( $p ); ?>" class="btn btn-sm" style="padding:0.25rem 0.65rem; font-size:0.75rem;">
                    <?= $p; ?>
                  </a>
                <?php endif;
            endfor;

            if ( $end_page < $total_pages ) {
                echo '<span style="padding:0 0.25rem; color:var(--adm-muted); font-size:0.75rem;">…</span>';
            }
            ?>

            <?php if ( $current_paged < $total_pages ) : ?>
              <a href="<?= $build_page_url( $current_paged + 1 ); ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="Sonraki Sayfa">Sonraki ›</a>
              <a href="<?= $build_page_url( $total_pages ); ?>" class="btn btn-sm" style="padding:0.25rem 0.55rem; font-size:0.75rem;" title="Son Sayfa">Son »</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

<?php endif; ?>

<!-- SOSYAL MEDYA ALINTI KARTI MODAL (CANVAS MOTORU) -->
<div id="storyModal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1.5rem;">
  <div style="background:var(--adm-card, #fff); max-width:850px; width:100%; border-radius:12px; border:1px solid var(--adm-border); box-shadow:0 25px 50px rgba(0,0,0,0.3); overflow:hidden; max-height:92vh; display:flex; flex-direction:column;">
    
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--adm-border); display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0; font-size:1.15rem; font-weight:700;">📱 Edebi Alıntı & Story Kartı Üretici</h3>
      <button type="button" onclick="closeStoryModal()" style="background:transparent; border:none; font-size:1.4rem; cursor:pointer; color:var(--adm-muted);">&times;</button>
    </div>

    <div style="display:grid; grid-template-columns:300px 1fr; gap:1.5rem; padding:1.5rem; overflow-y:auto;">
      
      <!-- Önizleme Kartı (Canvas) -->
      <div style="display:flex; flex-direction:column; align-items:center;">
        <canvas id="quoteCanvas" width="1080" height="1920" style="max-width:270px; width:100%; height:auto; box-shadow:0 8px 24px rgba(0,0,0,0.3); border:1px solid #ccc; border-radius:6px; background:#fff;"></canvas>
        <button type="button" onclick="downloadQuoteCard()" class="btn btn-primary" style="margin-top:1rem; width:100%; max-width:270px; justify-content:center; padding:0.65rem;">
          💾 PNG Kartını İndir
        </button>
      </div>

      <!-- Kart Ayarları -->
      <div style="display:flex; flex-direction:column; gap:1rem;">
        
        <div>
          <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Kart Formatı & Oranı:</label>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.45rem;">
            <button type="button" class="btn btn-sm" id="fmtPortraitBtn" onclick="setCardFormat('portrait')" style="justify-content:center; font-weight:bold; background:#1a1a1a; color:#fff; font-size:0.78rem;">📷 Instagram (4:5)</button>
            <button type="button" class="btn btn-sm btn-secondary" id="fmtTwitterBtn" onclick="setCardFormat('twitter')" style="justify-content:center; font-size:0.78rem;">🐦 Twitter/X (16:9)</button>
            <button type="button" class="btn btn-sm btn-secondary" id="fmtStoryBtn" onclick="setCardFormat('story')" style="justify-content:center; font-size:0.78rem;">📱 Hikaye (9:16)</button>
            <button type="button" class="btn btn-sm btn-secondary" id="fmtSquareBtn" onclick="setCardFormat('square')" style="justify-content:center; font-size:0.78rem;">⏹️ Kare (1:1)</button>
          </div>
        </div>

        <div>
          <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Edebi Doku & Kusurluluk Teması:</label>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.45rem;" id="themeButtonsGroup">
            <button type="button" class="btn btn-sm" id="thmPaperBtn" onclick="setCardTheme('paper')" style="justify-content:center; background:#1a1a1a; color:#fff; font-weight:700; font-size:0.75rem;">📜 Ham Kağıt & Bant</button>
            <button type="button" class="btn btn-sm btn-secondary" id="thmFanzineBtn" onclick="setCardTheme('fanzine')" style="justify-content:center; font-size:0.75rem;">🖨️ Kusurlu Fanzin</button>
            <button type="button" class="btn btn-sm btn-secondary" id="thmBlueprintBtn" onclick="setCardTheme('blueprint')" style="justify-content:center; font-size:0.75rem;">📐 Milimetrik Defter</button>
            <button type="button" class="btn btn-sm btn-secondary" id="thmDarkBtn" onclick="setCardTheme('dark')" style="justify-content:center; font-size:0.75rem;">🖤 Gece Daktilosu</button>
            <button type="button" class="btn btn-sm btn-secondary" id="thmBordeauxBtn" onclick="setCardTheme('bordeaux')" style="justify-content:center; font-size:0.75rem;">🍷 Matbaa Bordo</button>
            <button type="button" class="btn btn-sm btn-secondary" id="thmPostageBtn" onclick="setCardTheme('postage')" style="justify-content:center; font-size:0.75rem;">✉️ Posta Pulu & Zarf</button>
          </div>
        </div>

        <div>
          <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Kusurlu Edebi Katmanlar (Detaylar):</label>
          <div style="display:flex; flex-wrap:wrap; gap:0.75rem; background:var(--adm-bg); padding:0.6rem 0.8rem; border-radius:6px; font-size:0.78rem;">
            <label style="display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer;">
              <input type="checkbox" id="cardOptTape" checked onchange="drawQuoteCard()" /> Koli Bandı Şeritleri
            </label>
            <label style="display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer;">
              <input type="checkbox" id="cardOptStamp" checked onchange="drawQuoteCard()" /> Kusurlu Matbaa Kaşesi
            </label>
            <label style="display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer;">
              <input type="checkbox" id="cardOptTexture" checked onchange="drawQuoteCard()" /> Kağıt Lif & Doku
            </label>
          </div>
        </div>

        <div>
          <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Alıntı / Vurucu Cümle:</label>
          <textarea id="cardQuoteText" rows="5" class="form-control" oninput="drawQuoteCard()" style="font-family:Georgia, serif; font-size:0.92rem; line-height:1.5;"></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div>
            <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Yazar Adı:</label>
            <input type="text" id="cardAuthor" class="form-control" oninput="drawQuoteCard()" style="font-size:0.85rem;" />
          </div>
          <div>
            <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Eser Başlığı:</label>
            <input type="text" id="cardTitle" class="form-control" oninput="drawQuoteCard()" style="font-size:0.85rem;" />
          </div>
        </div>

        <div style="font-size:0.75rem; color:var(--adm-muted); line-height:1.4; background:var(--adm-bg); padding:0.6rem 0.8rem; border-radius:6px;">
          ✦ İpucu: Bu görseli Instagram Story, Reels kapağı veya Twitter (X) paylaşımı olarak doğrudan paylaşarak organik edebiyat okuru çekebilirsiniz.
        </div>

      </div>

    </div>

  </div>
</div>

<script>
let currentFormat = 'portrait'; // 'portrait' (4:5), 'twitter' (16:9), 'story' (9:16), 'square' (1:1)
let currentTheme = 'paper';     // 'paper', 'dark', 'red'

function openStoryModal(data) {
  document.getElementById('cardQuoteText').value = data.excerpt || data.title || '';
  document.getElementById('cardAuthor').value = data.author || 'Pratfall Yazarı';
  document.getElementById('cardTitle').value = data.title || '';
  
  var modal = document.getElementById('storyModal');
  modal.style.display = 'flex';
  
  setCardFormat('portrait');
  setCardTheme(currentTheme || 'paper');
}

function closeStoryModal() {
  document.getElementById('storyModal').style.display = 'none';
}

function setCardFormat(fmt) {
  currentFormat = fmt;
  const canvas = document.getElementById('quoteCanvas');
  const btnPortrait = document.getElementById('fmtPortraitBtn');
  const btnTwitter  = document.getElementById('fmtTwitterBtn');
  const btnStory    = document.getElementById('fmtStoryBtn');
  const btnSquare   = document.getElementById('fmtSquareBtn');

  [btnPortrait, btnTwitter, btnStory, btnSquare].forEach(btn => {
    if (btn) {
      btn.style.background = '';
      btn.style.color = '';
      btn.classList.add('btn-secondary');
      btn.classList.remove('btn-primary');
    }
  });

  if (fmt === 'portrait') {
    canvas.width = 1080;
    canvas.height = 1350;
    if (btnPortrait) {
      btnPortrait.style.background = '#1a1a1a';
      btnPortrait.style.color = '#fff';
    }
  } else if (fmt === 'twitter') {
    canvas.width = 1200;
    canvas.height = 675;
    if (btnTwitter) {
      btnTwitter.style.background = '#1a1a1a';
      btnTwitter.style.color = '#fff';
    }
  } else if (fmt === 'story') {
    canvas.width = 1080;
    canvas.height = 1920;
    if (btnStory) {
      btnStory.style.background = '#1a1a1a';
      btnStory.style.color = '#fff';
    }
  } else if (fmt === 'square') {
    canvas.width = 1080;
    canvas.height = 1080;
    if (btnSquare) {
      btnSquare.style.background = '#1a1a1a';
      btnSquare.style.color = '#fff';
    }
  }
  drawQuoteCard();
}

function setCardTheme(thm) {
  currentTheme = thm;
  const themeIds = ['thmPaperBtn', 'thmFanzineBtn', 'thmBlueprintBtn', 'thmDarkBtn', 'thmBordeauxBtn', 'thmPostageBtn'];
  themeIds.forEach(id => {
    const btn = document.getElementById(id);
    if (btn) {
      btn.style.background = '';
      btn.style.color = '';
      btn.classList.add('btn-secondary');
    }
  });

  const activeMap = {
    'paper': 'thmPaperBtn',
    'fanzine': 'thmFanzineBtn',
    'blueprint': 'thmBlueprintBtn',
    'dark': 'thmDarkBtn',
    'bordeaux': 'thmBordeauxBtn',
    'postage': 'thmPostageBtn'
  };

  const activeBtn = document.getElementById(activeMap[thm]);
  if (activeBtn) {
    activeBtn.style.background = '#1a1a1a';
    activeBtn.style.color = '#fff';
    activeBtn.classList.remove('btn-secondary');
  }

  drawQuoteCard();
}

// ✦ Kusurluluk & Edebi Doku Çizim Yardımcıları ✦
function drawNoiseTexture(ctx, w, h, count, color) {
  ctx.save();
  ctx.fillStyle = color || 'rgba(0, 0, 0, 0.04)';
  for (let i = 0; i < count; i++) {
    const rx = Math.random() * w;
    const ry = Math.random() * h;
    const s = Math.random() * 2.2 + 0.8;
    ctx.fillRect(rx, ry, s, s);
  }
  ctx.restore();
}

function drawWashiTapeStrip(ctx, x, y, width, height, angle, tapeColor) {
  ctx.save();
  ctx.translate(x, y);
  ctx.rotate((angle * Math.PI) / 180);
  ctx.fillStyle = tapeColor || 'rgba(245, 230, 200, 0.82)';

  const teeth = 8;
  ctx.beginPath();
  ctx.moveTo(0, 0);
  ctx.lineTo(width, 0);
  
  // Sağ tırtıklı/yırtık uç
  for (let i = 0; i <= teeth; i++) {
    const ty = (i / teeth) * height;
    const tx = width + (i % 2 === 0 ? 5 : -4);
    ctx.lineTo(tx, ty);
  }
  ctx.lineTo(0, height);
  
  // Sol tırtıklı/yırtık uç
  for (let i = teeth; i >= 0; i--) {
    const ty = (i / teeth) * height;
    const tx = 0 - (i % 2 === 0 ? 5 : -4);
    ctx.lineTo(tx, ty);
  }
  ctx.closePath();
  ctx.fill();

  // Bant kenar gölgesi
  ctx.strokeStyle = 'rgba(0, 0, 0, 0.12)';
  ctx.lineWidth = 1;
  ctx.stroke();

  // Bant lif çizgileri
  ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(10, height * 0.4);
  ctx.lineTo(width - 10, height * 0.4);
  ctx.stroke();

  ctx.restore();
}

function drawRubberStamp(ctx, x, y, radius, angle, color, subText) {
  ctx.save();
  ctx.translate(x, y);
  ctx.rotate((angle * Math.PI) / 180);
  ctx.strokeStyle = color;
  ctx.fillStyle = color;

  // Dış kalın halka
  ctx.lineWidth = 4;
  ctx.beginPath();
  ctx.arc(0, 0, radius, 0, Math.PI * 2);
  ctx.stroke();

  // İç kesik çizgili halka
  ctx.lineWidth = 2;
  ctx.setLineDash([8, 6]);
  ctx.beginPath();
  ctx.arc(0, 0, radius - 10, 0, Math.PI * 2);
  ctx.stroke();
  ctx.setLineDash([]);

  // Kaşe içi metinler
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  ctx.font = 'bold ' + Math.round(radius * 0.20) + 'px "Courier New", monospace';
  ctx.fillText('PRATFALL', 0, -radius * 0.34);

  ctx.font = '900 ' + Math.round(radius * 0.14) + 'px "Courier New", monospace';
  ctx.fillText('EDİTORYA', 0, -radius * 0.13);

  ctx.font = 'bold ' + Math.round(radius * 0.14) + 'px "Courier New", monospace';
  ctx.fillText('✦ ✦ ✦', 0, radius * 0.08);

  ctx.font = 'bold ' + Math.round(radius * 0.11) + 'px "Courier New", monospace';
  ctx.fillText(subText || 'KUSURLU KALMA ÇABASI', 0, radius * 0.31);

  ctx.font = 'italic ' + Math.round(radius * 0.11) + 'px Georgia, serif';
  ctx.fillText('NO: 1984 / ONAYLI', 0, radius * 0.52);

  ctx.restore();
}

function drawCornerCross(ctx, x, y, size, color) {
  ctx.save();
  ctx.strokeStyle = color;
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(x - size, y);
  ctx.lineTo(x + size, y);
  ctx.moveTo(x, y - size);
  ctx.lineTo(x, y + size);
  ctx.stroke();
  ctx.beginPath();
  ctx.arc(x, y, size * 0.45, 0, Math.PI * 2);
  ctx.stroke();
  ctx.restore();
}

function drawAirmailBorders(ctx, w, h, thickness) {
  ctx.save();
  const stripeW = thickness * 1.5;
  const colors = ['#b91c1c', '#ffffff', '#1e40af', '#ffffff'];

  ctx.beginPath();
  ctx.rect(0, 0, w, h);
  ctx.rect(thickness, thickness, w - thickness * 2, h - thickness * 2);
  ctx.clip('evenodd');

  for (let x = -h; x < w + h; x += stripeW) {
    const col = colors[Math.floor(Math.abs(x) / stripeW) % colors.length];
    ctx.fillStyle = col;
    ctx.beginPath();
    ctx.moveTo(x, 0);
    ctx.lineTo(x + stripeW, 0);
    ctx.lineTo(x + stripeW - h, h);
    ctx.lineTo(x - h, h);
    ctx.closePath();
    ctx.fill();
  }
  ctx.restore();
}

function drawQuoteCard() {
  const canvas = document.getElementById('quoteCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width;
  const h = canvas.height;

  const isTwitter = currentFormat === 'twitter';
  const isStory = currentFormat === 'story';
  const isSquare = currentFormat === 'square';
  const margin = isTwitter ? 36 : 48;

  // Katman Seçenekleri
  const optTape = document.getElementById('cardOptTape') ? document.getElementById('cardOptTape').checked : true;
  const optStamp = document.getElementById('cardOptStamp') ? document.getElementById('cardOptStamp').checked : true;
  const optTexture = document.getElementById('cardOptTexture') ? document.getElementById('cardOptTexture').checked : true;

  // Tema Renk Değişkenleri
  let bgColor = '#fbf7ee';
  let cardBg = '#fdfbf7';
  let textColor = '#1a1a1a';
  let accentColor = '#8b1e0f';
  let frameColor = '#1a1a1a';
  let mutedColor = '#555555';
  let watermarkColor = 'rgba(139, 30, 15, 0.05)';
  let stampColor = 'rgba(139, 30, 15, 0.78)';
  let tapeColor = 'rgba(240, 225, 195, 0.85)';

  if (currentTheme === 'paper') {
    bgColor = '#ede5d5';
    cardBg = '#faf6ed';
    textColor = '#1e1c19';
    accentColor = '#8b1e0f';
    frameColor = '#2b2621';
    mutedColor = '#5c544a';
    watermarkColor = 'rgba(139, 30, 15, 0.05)';
    stampColor = 'rgba(139, 30, 15, 0.82)';
    tapeColor = 'rgba(235, 218, 185, 0.88)';
  } else if (currentTheme === 'fanzine') {
    bgColor = '#e5e3dc';
    cardBg = '#ffffff';
    textColor = '#050505';
    accentColor = '#000000';
    frameColor = '#000000';
    mutedColor = '#333333';
    watermarkColor = 'rgba(0, 0, 0, 0.05)';
    stampColor = 'rgba(0, 0, 0, 0.88)';
    tapeColor = 'rgba(254, 240, 138, 0.9)'; // Sarı fanzin koli bandı
  } else if (currentTheme === 'blueprint') {
    bgColor = '#edf3f8';
    cardBg = '#f8fbfe';
    textColor = '#0f172a';
    accentColor = '#1e40af';
    frameColor = '#1e3a8a';
    mutedColor = '#475569';
    watermarkColor = 'rgba(30, 64, 175, 0.06)';
    stampColor = 'rgba(30, 64, 175, 0.82)';
    tapeColor = 'rgba(219, 234, 254, 0.88)';
  } else if (currentTheme === 'dark') {
    bgColor = '#0f0f10';
    cardBg = '#18181b';
    textColor = '#f4f4f5';
    accentColor = '#f59e0b'; // Amber altın
    frameColor = '#3f3f46';
    mutedColor = '#a1a1aa';
    watermarkColor = 'rgba(255, 255, 255, 0.04)';
    stampColor = 'rgba(245, 158, 11, 0.85)';
    tapeColor = 'rgba(63, 63, 70, 0.8)';
  } else if (currentTheme === 'bordeaux') {
    bgColor = '#380a0f';
    cardBg = '#4c0d14';
    textColor = '#fdf8f0';
    accentColor = '#fbbf24'; // Matbaa yaldız
    frameColor = '#d97706';
    mutedColor = '#e2c5b0';
    watermarkColor = 'rgba(251, 191, 36, 0.06)';
    stampColor = 'rgba(251, 191, 36, 0.88)';
    tapeColor = 'rgba(180, 83, 9, 0.7)';
  } else if (currentTheme === 'postage') {
    bgColor = '#f4efe6';
    cardBg = '#fcf9f2';
    textColor = '#1c1917';
    accentColor = '#b91c1c';
    frameColor = '#1e3a8a';
    mutedColor = '#57534e';
    watermarkColor = 'rgba(185, 28, 28, 0.05)';
    stampColor = 'rgba(185, 28, 28, 0.82)';
    tapeColor = 'rgba(245, 230, 205, 0.85)';
  }

  // 1. Ana Dış Zemin
  ctx.fillStyle = bgColor;
  ctx.fillRect(0, 0, w, h);

  // 1.b Tema Özel Arka Plan Desenleri
  if (currentTheme === 'blueprint') {
    // Milimetrik çizgiler
    ctx.save();
    ctx.strokeStyle = 'rgba(30, 64, 175, 0.12)';
    ctx.lineWidth = 1;
    const gridStep = 32;
    for (let gx = 0; gx < w; gx += gridStep) {
      ctx.beginPath();
      ctx.moveTo(gx, 0);
      ctx.lineTo(gx, h);
      ctx.stroke();
    }
    for (let gy = 0; gy < h; gy += gridStep) {
      ctx.beginPath();
      ctx.moveTo(0, gy);
      ctx.lineTo(w, gy);
      ctx.stroke();
    }
    // Defter marjin kırmızı çizgisi
    ctx.strokeStyle = 'rgba(239, 68, 68, 0.45)';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    ctx.moveTo(margin + 70, 0);
    ctx.lineTo(margin + 70, h);
    ctx.stroke();
    ctx.restore();
  } else if (currentTheme === 'postage') {
    // Posta çavuş şeritleri
    drawAirmailBorders(ctx, w, h, isTwitter ? 24 : 32);
  }

  // 2. İç Kart / Kusurlu Edebi Blok (Hard Shadow & Container)
  const cardPad = isTwitter ? 24 : (currentTheme === 'postage' ? 44 : 36);
  const cardX = cardPad;
  const cardY = cardPad;
  const cardW = w - (cardPad * 2);
  const cardH = h - (cardPad * 2);

  // Neo-Brutalist Sert Gölge (Paper, Fanzine temalarında kusurlu kutu kayması)
  if (currentTheme === 'paper' || currentTheme === 'fanzine') {
    ctx.save();
    ctx.fillStyle = currentTheme === 'fanzine' ? '#000000' : 'rgba(0, 0, 0, 0.22)';
    const shadowOffset = isTwitter ? 8 : 14;
    ctx.fillRect(cardX + shadowOffset, cardY + shadowOffset, cardW, cardH);
    ctx.restore();
  }

  // İç Kart Gövdesi
  ctx.fillStyle = cardBg;
  ctx.fillRect(cardX, cardY, cardW, cardH);

  // Kart Çerçevesi
  ctx.strokeStyle = frameColor;
  ctx.lineWidth = currentTheme === 'fanzine' ? 5 : (isTwitter ? 3 : 4);

  if (currentTheme === 'fanzine') {
    ctx.strokeRect(cardX, cardY, cardW, cardH);
    // Üst kesik/perfore çizgi süsü
    ctx.save();
    ctx.setLineDash([12, 8]);
    ctx.lineWidth = 2;
    ctx.strokeStyle = '#000';
    ctx.beginPath();
    ctx.moveTo(cardX + 20, cardY + 28);
    ctx.lineTo(cardX + cardW - 20, cardY + 28);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.font = 'bold 13px monospace';
    ctx.fillStyle = '#000';
    ctx.fillText('✂ [ KUSURLU FANZİN SAYFASI - KESİK HATTI ]', cardX + 35, cardY + 24);
    ctx.restore();
  } else if (currentTheme === 'bordeaux') {
    // Çift yaldız çizgi & köşe süsleri
    ctx.strokeRect(cardX, cardY, cardW, cardH);
    ctx.strokeStyle = 'rgba(251, 191, 36, 0.45)';
    ctx.lineWidth = 1.5;
    ctx.strokeRect(cardX + 12, cardY + 12, cardW - 24, cardH - 24);
  } else if (currentTheme === 'dark') {
    ctx.strokeRect(cardX, cardY, cardW, cardH);
    // 4 köşeye matbaa registration haçları
    const crossOffset = 22;
    drawCornerCross(ctx, cardX + crossOffset, cardY + crossOffset, 12, accentColor);
    drawCornerCross(ctx, cardX + cardW - crossOffset, cardY + crossOffset, 12, accentColor);
    drawCornerCross(ctx, cardX + crossOffset, cardY + cardH - crossOffset, 12, accentColor);
    drawCornerCross(ctx, cardX + cardW - crossOffset, cardY + cardH - crossOffset, 12, accentColor);
  } else {
    ctx.strokeRect(cardX, cardY, cardW, cardH);
  }

  // Posta Pulu Figürü (Postage Teması için)
  if (currentTheme === 'postage') {
    ctx.save();
    const stampW = isTwitter ? 90 : 120;
    const stampH = isTwitter ? 110 : 145;
    const stampX = cardX + cardW - stampW - 30;
    const stampY = cardY + 30;
    
    // Pul arkaplanı
    ctx.fillStyle = '#fff9f0';
    ctx.strokeStyle = '#b91c1c';
    ctx.lineWidth = 2.5;
    ctx.setLineDash([5, 4]);
    ctx.strokeRect(stampX, stampY, stampW, stampH);
    ctx.fillRect(stampX, stampY, stampW, stampH);
    ctx.setLineDash([]);

    // Pul içi
    ctx.fillStyle = '#b91c1c';
    ctx.font = 'bold 12px "Courier New", monospace';
    ctx.textAlign = 'center';
    ctx.fillText('PRATFALL', stampX + (stampW / 2), stampY + 24);
    ctx.font = 'bold 26px Georgia, serif';
    ctx.fillText('✒️', stampX + (stampW / 2), stampY + 65);
    ctx.font = 'bold 11px "Courier New", monospace';
    ctx.fillText('100 KURUŞ', stampX + (stampW / 2), stampY + 95);

    // Damga dalgaları
    ctx.strokeStyle = 'rgba(30, 58, 138, 0.65)';
    ctx.lineWidth = 2;
    for (let dw = 0; dw < 3; dw++) {
      ctx.beginPath();
      const waveY = stampY + 35 + (dw * 14);
      ctx.moveTo(stampX - 45, waveY);
      ctx.bezierCurveTo(stampX - 25, waveY - 8, stampX - 5, waveY + 8, stampX + 15, waveY);
      ctx.stroke();
    }
    ctx.restore();
  }

  // 3. Üst Logo & Slogan Hiyerarşisi
  ctx.textAlign = 'center';
  ctx.fillStyle = accentColor;
  ctx.font = (isTwitter ? 'bold 24px' : 'bold 30px') + ' "Courier New", monospace';
  const headerY1 = cardY + (isTwitter ? 46 : 60);
  ctx.fillText('✦ PRATFALL ✦', w / 2, headerY1);

  ctx.fillStyle = textColor;
  ctx.font = (isTwitter ? '600 15px' : '600 19px') + ' "Courier New", monospace';
  const headerY2 = headerY1 + (isTwitter ? 26 : 34);
  ctx.fillText('Edebiyat, Fikir, Kültür, Sanat', w / 2, headerY2);

  ctx.fillStyle = mutedColor;
  ctx.font = (isTwitter ? 'italic 15px' : 'italic 19px') + ' Georgia, serif';
  const headerY3 = headerY2 + (isTwitter ? 24 : 32);
  ctx.fillText('Kusurlu Kalma Çabası', w / 2, headerY3);

  // Üst ayırıcı çizgi
  const headerLineY = headerY3 + (isTwitter ? 18 : 25);
  ctx.strokeStyle = frameColor;
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(w / 2 - (isTwitter ? 100 : 130), headerLineY);
  ctx.lineTo(w / 2 + (isTwitter ? 100 : 130), headerLineY);
  ctx.stroke();

  // 4. Alt Bilgi Alanı Konumu
  const author = document.getElementById('cardAuthor').value.trim();
  const title = document.getElementById('cardTitle').value.trim();
  const footerSeparatorY = cardY + cardH - (isTwitter ? 105 : (isStory ? 210 : 155));

  // 5. İçerik Alanı & Arka Plan Alıntı Filigranı (Watermark)
  const contentTop = headerLineY + 20;
  const contentBottom = footerSeparatorY - 20;
  const contentCenterY = (contentTop + contentBottom) / 2;
  const availHeight = contentBottom - contentTop;

  ctx.save();
  ctx.font = (isTwitter ? 'bold 200px' : 'bold 320px') + ' Georgia, serif';
  ctx.fillStyle = watermarkColor;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText('“', w / 2, contentCenterY);
  ctx.restore();

  // 6. Alıntı Metni Sarma & Boyutlandırma Motoru
  const quote = document.getElementById('cardQuoteText').value.trim() || 'Sözcüklerin kusuru, insanın hakiki yarasına denk gelir.';
  const maxTextWidth = isTwitter ? w - 240 : (w - (cardPad * 2) - 130);

  function wrapWords(text, fSize) {
    ctx.font = `italic ${fSize}px Georgia, serif`;
    const words = text.split(/\s+/);
    let lns = [];
    let curLine = '';
    for (let n = 0; n < words.length; n++) {
      const test = curLine + words[n] + ' ';
      if (ctx.measureText(test).width > maxTextWidth && n > 0) {
        lns.push(curLine.trim());
        curLine = words[n] + ' ';
      } else {
        curLine = test;
      }
    }
    if (curLine.trim()) lns.push(curLine.trim());
    return lns;
  }

  let fontSize = isTwitter ? 34 : (isStory ? 52 : (currentFormat === 'portrait' ? 46 : 42));
  let lines = wrapWords(quote, fontSize);
  let lineHeight = fontSize * 1.55;
  let totalTextHeight = lines.length * lineHeight;

  const minFont = isTwitter ? 17 : 21;
  while ((totalTextHeight + 60 > availHeight) && fontSize > minFont) {
    fontSize -= 2;
    lineHeight = fontSize * 1.55;
    lines = wrapWords(quote, fontSize);
    totalTextHeight = lines.length * lineHeight;
  }

  let startY = contentCenterY - (totalTextHeight / 2) + (fontSize * 0.4);

  // 7. Vurucu Alıntı Tırnağı İkonu
  const quoteBadgeY = startY - (lineHeight * 0.7);
  ctx.font = 'bold ' + Math.min(42, Math.max(24, Math.round(fontSize * 0.85))) + 'px Georgia, serif';
  ctx.fillStyle = accentColor;
  ctx.textAlign = 'center';
  ctx.fillText('“', w / 2, quoteBadgeY);

  // 8. Fanzin Vurgulayıcı Kalem Efekti (Fanzine temasında satır altı fosforlu bant)
  if (currentTheme === 'fanzine' && lines.length > 0) {
    ctx.save();
    ctx.fillStyle = 'rgba(254, 240, 138, 0.72)';
    for (let k = 0; k < lines.length; k++) {
      ctx.font = `italic ${fontSize}px Georgia, serif`;
      const lineMetrics = ctx.measureText(lines[k]);
      const lineW = lineMetrics.width;
      const lx = (w / 2) - (lineW / 2) - 8;
      const ly = startY + (k * lineHeight) - (fontSize * 0.72);
      ctx.fillRect(lx, ly, lineW + 16, fontSize * 0.88);
    }
    ctx.restore();
  }

  // 9. Metin Satırlarını Çiz
  ctx.font = `italic ${fontSize}px Georgia, serif`;
  ctx.fillStyle = textColor;
  ctx.textAlign = 'center';
  for (let k = 0; k < lines.length; k++) {
    ctx.fillText(lines[k], w / 2, startY + (k * lineHeight));
  }

  // 10. Alt Bilgi Çizimi
  ctx.strokeStyle = frameColor;
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(w / 2 - (isTwitter ? 70 : 90), footerSeparatorY);
  ctx.lineTo(w / 2 + (isTwitter ? 70 : 90), footerSeparatorY);
  ctx.stroke();

  if (author) {
    ctx.font = (isTwitter ? 'bold 24px' : 'bold 30px') + ' "Courier New", monospace';
    ctx.fillStyle = accentColor;
    ctx.fillText('[ ' + author.toUpperCase() + ' ]', w / 2, footerSeparatorY + (isTwitter ? 32 : 44));
  }

  if (title) {
    ctx.font = (isTwitter ? '18px' : '22px') + ' Georgia, serif';
    ctx.fillStyle = mutedColor;
    ctx.fillText('« ' + title + ' »', w / 2, footerSeparatorY + (isTwitter ? 58 : 80));
  }

  // En alt domain
  ctx.font = (isTwitter ? '16px' : '20px') + ' "Courier New", monospace';
  ctx.fillStyle = mutedColor;
  ctx.fillText('www.pratfall.com.tr', w / 2, cardY + cardH - (isTwitter ? 16 : 24));

  // 11. Kusurlu Katmanlar (Koli Bandı & Matbaa Kaşesi)
  if (optTape) {
    // Üst orta sabitleyici koli bandı
    const tapeW = isTwitter ? 150 : 200;
    const tapeH = isTwitter ? 32 : 42;
    drawWashiTapeStrip(ctx, (w / 2) - (tapeW / 2), cardY - 14, tapeW, tapeH, -2, tapeColor);

    // Sağ alt köşeye çapraz destek bandı
    if (!isTwitter) {
      drawWashiTapeStrip(ctx, cardX + cardW - 130, cardY + cardH - 60, 160, 36, -38, tapeColor);
    }
  }

  if (optStamp) {
    // Sol alt veya sağ üst köşeye vurulmuş kaşe
    const stampRadius = isTwitter ? 58 : 80;
    const stampX = cardX + (isTwitter ? 85 : 125);
    const stampY = cardY + cardH - (isTwitter ? 85 : 125);
    drawRubberStamp(ctx, stampX, stampY, stampRadius, -14, stampColor, 'KUSURLU KALMA ÇABASI');
  }

  // 12. Kağıt Lif & Doku (Noise) Katmanı
  if (optTexture) {
    drawNoiseTexture(ctx, w, h, isTwitter ? 1800 : 3500, currentTheme === 'dark' ? 'rgba(255, 255, 255, 0.035)' : 'rgba(0, 0, 0, 0.045)');
  }
}

function downloadQuoteCard() {
  const canvas = document.getElementById('quoteCanvas');
  const title = (document.getElementById('cardAuthor').value || 'pratfall').toLowerCase().replace(/[^a-z0-9]/g, '-');
  const link = document.createElement('a');
  link.download = `pratfall-alinti-${title}-${Date.now()}.png`;
  link.href = canvas.toDataURL('image/png');
  link.click();
}

// ==========================================================
// OTOMATİK ÖNE ÇIKAN KAPAK GÖRSELİ ÜRETİCİSİ (CANVAS MOTORU)
// ==========================================================
let currentCoverTheme = 'paper'; // 'paper', 'dark', 'red', 'fanzine'
let currentCoverFont = 'serif';  // 'serif', 'mono'

function openCoverGeneratorModal() {
  const titleInput = document.getElementById('title');
  const authorSelect = document.getElementById('author_id');
  const categorySelect = document.getElementById('category');

  let postTitle = titleInput ? titleInput.value.trim() : '';
  let authorName = 'Pratfall Yazarı';
  if (authorSelect && authorSelect.selectedIndex >= 0) {
    const selText = authorSelect.options[authorSelect.selectedIndex].text.trim();
    if (selText && !selText.startsWith('✦')) {
      authorName = selText;
    }
  }
  let categoryName = categorySelect ? categorySelect.value.trim() : 'Deneme';

  document.getElementById('coverGenTitle').value = postTitle || 'Eserin Başlığı';
  document.getElementById('coverGenAuthor').value = authorName;
  document.getElementById('coverGenCategory').value = categoryName || 'Edebiyat';
  document.getElementById('coverGenBadge').value = 'PRATFALL · EDEBİYAT & FİKİR & KÜLTÜR & SANAT PLATFORMU';
  document.getElementById('coverSaveStatus').innerHTML = '';

  document.getElementById('coverGeneratorModal').style.display = 'flex';
  setCoverTheme('paper');
  setCoverFont('serif');
  drawCoverImage();
}

function closeCoverGeneratorModal() {
  document.getElementById('coverGeneratorModal').style.display = 'none';
}

function updateCoverPreview(url) {
  const box = document.getElementById('featured_image_preview_box');
  const img = document.getElementById('featured_image_preview_img');
  if (!box || !img) return;
  if (url && url.trim().length > 5) {
    img.src = url.trim();
    box.style.display = 'block';
  } else {
    box.style.display = 'none';
  }
}

function setCoverTheme(thm) {
  currentCoverTheme = thm;
  const themeIds = [
    'coverThmPaperBtn', 'coverThmFanzineBtn', 'coverThmBlueprintBtn',
    'coverThmDarkBtn', 'coverThmBordeauxBtn', 'coverThmPostageBtn',
    'coverThmKraftBtn', 'coverThmEmeraldBtn'
  ];

  themeIds.forEach(id => {
    const btn = document.getElementById(id);
    if (btn) {
      btn.style.background = '';
      btn.style.color = '';
      btn.classList.add('btn-secondary');
      btn.classList.remove('btn-primary');
    }
  });

  const activeMap = {
    'paper': 'coverThmPaperBtn',
    'fanzine': 'coverThmFanzineBtn',
    'blueprint': 'coverThmBlueprintBtn',
    'dark': 'coverThmDarkBtn',
    'bordeaux': 'coverThmBordeauxBtn',
    'postage': 'coverThmPostageBtn',
    'kraft': 'coverThmKraftBtn',
    'emerald': 'coverThmEmeraldBtn'
  };

  const activeBtn = document.getElementById(activeMap[thm]);
  if (activeBtn) {
    activeBtn.style.background = '#1a1a1a';
    activeBtn.style.color = '#fff';
    activeBtn.classList.remove('btn-secondary');
    activeBtn.classList.add('btn-primary');
  }

  drawCoverImage();
}

function setCoverFont(font) {
  currentCoverFont = font;
  const btnSerif = document.getElementById('coverFontSerifBtn');
  const btnMono = document.getElementById('coverFontMonoBtn');

  if (font === 'serif') {
    btnSerif.style.background = '#1a1a1a';
    btnSerif.style.color = '#fff';
    btnMono.style.background = '';
    btnMono.style.color = '';
  } else {
    btnMono.style.background = '#1a1a1a';
    btnMono.style.color = '#fff';
    btnSerif.style.background = '';
    btnSerif.style.color = '';
  }

  drawCoverImage();
}

function drawCoverImage() {
  const canvas = document.getElementById('coverGenCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width;  // 1200
  const h = canvas.height; // 675
  const m = 38;

  // Katman Seçenekleri
  const optTape = document.getElementById('coverOptTape') ? document.getElementById('coverOptTape').checked : true;
  const optStamp = document.getElementById('coverOptStamp') ? document.getElementById('coverOptStamp').checked : true;
  const optTexture = document.getElementById('coverOptTexture') ? document.getElementById('coverOptTexture').checked : true;

  // Tema Renkleri
  let bgColor = '#ede5d5';
  let cardBg = '#faf6ed';
  let textColor = '#1e1c19';
  let accentColor = '#8b1e0f';
  let frameColor = '#2b2621';
  let mutedColor = '#5c544a';
  let badgeBg = '#fef08a';
  let badgeText = '#141416';
  let stampColor = 'rgba(139, 30, 15, 0.82)';
  let tapeColor = 'rgba(235, 218, 185, 0.88)';

  if (currentCoverTheme === 'paper') {
    bgColor = '#ede5d5';
    cardBg = '#faf6ed';
    textColor = '#1e1c19';
    accentColor = '#8b1e0f';
    frameColor = '#2b2621';
    mutedColor = '#5c544a';
    badgeBg = '#fef08a';
    badgeText = '#141416';
    stampColor = 'rgba(139, 30, 15, 0.82)';
    tapeColor = 'rgba(235, 218, 185, 0.88)';
  } else if (currentCoverTheme === 'fanzine') {
    bgColor = '#e5e3dc';
    cardBg = '#ffffff';
    textColor = '#050505';
    accentColor = '#000000';
    frameColor = '#000000';
    mutedColor = '#333333';
    badgeBg = '#000000';
    badgeText = '#ffffff';
    stampColor = 'rgba(0, 0, 0, 0.88)';
    tapeColor = 'rgba(254, 240, 138, 0.9)'; // Sarı fanzin koli bandı
  } else if (currentCoverTheme === 'blueprint') {
    bgColor = '#edf3f8';
    cardBg = '#f8fbfe';
    textColor = '#0f172a';
    accentColor = '#1e40af';
    frameColor = '#1e3a8a';
    mutedColor = '#475569';
    badgeBg = '#dbeafe';
    badgeText = '#1e40af';
    stampColor = 'rgba(30, 64, 175, 0.82)';
    tapeColor = 'rgba(219, 234, 254, 0.88)';
  } else if (currentCoverTheme === 'dark') {
    bgColor = '#0f0f10';
    cardBg = '#18181b';
    textColor = '#f4f4f5';
    accentColor = '#f59e0b';
    frameColor = '#3f3f46';
    mutedColor = '#a1a1aa';
    badgeBg = '#27272a';
    badgeText = '#f59e0b';
    stampColor = 'rgba(245, 158, 11, 0.85)';
    tapeColor = 'rgba(63, 63, 70, 0.8)';
  } else if (currentCoverTheme === 'bordeaux') {
    bgColor = '#380a0f';
    cardBg = '#4c0d14';
    textColor = '#fdf8f0';
    accentColor = '#fbbf24';
    frameColor = '#d97706';
    mutedColor = '#e2c5b0';
    badgeBg = '#5f121b';
    badgeText = '#fbbf24';
    stampColor = 'rgba(251, 191, 36, 0.88)';
    tapeColor = 'rgba(180, 83, 9, 0.7)';
  } else if (currentCoverTheme === 'postage') {
    bgColor = '#f4efe6';
    cardBg = '#fcf9f2';
    textColor = '#1c1917';
    accentColor = '#b91c1c';
    frameColor = '#1e3a8a';
    mutedColor = '#57534e';
    badgeBg = '#fee2e2';
    badgeText = '#b91c1c';
    stampColor = 'rgba(185, 28, 28, 0.82)';
    tapeColor = 'rgba(245, 230, 205, 0.85)';
  } else if (currentCoverTheme === 'kraft') {
    bgColor = '#cbb396';
    cardBg = '#dbc1a4';
    textColor = '#2e1d11';
    accentColor = '#c2410c';
    frameColor = '#2e1d11';
    mutedColor = '#5c4028';
    badgeBg = '#fed7aa';
    badgeText = '#7c2d12';
    stampColor = 'rgba(74, 44, 17, 0.85)';
    tapeColor = 'rgba(161, 98, 7, 0.75)';
  } else if (currentCoverTheme === 'emerald') {
    bgColor = '#09231d';
    cardBg = '#0e352c';
    textColor = '#fefce8';
    accentColor = '#eab308';
    frameColor = '#134e40';
    mutedColor = '#93c5b8';
    badgeBg = '#14532d';
    badgeText = '#fef08a';
    stampColor = 'rgba(234, 179, 8, 0.85)';
    tapeColor = 'rgba(20, 83, 45, 0.75)';
  }

  // 1. Zemin Doldur
  ctx.fillStyle = bgColor;
  ctx.fillRect(0, 0, w, h);

  // 1.b Tema Özel Arka Plan Çizgileri
  if (currentCoverTheme === 'blueprint') {
    ctx.save();
    ctx.strokeStyle = 'rgba(30, 64, 175, 0.12)';
    ctx.lineWidth = 1;
    for (let gx = 0; gx < w; gx += 28) {
      ctx.beginPath();
      ctx.moveTo(gx, 0);
      ctx.lineTo(gx, h);
      ctx.stroke();
    }
    for (let gy = 0; gy < h; gy += 28) {
      ctx.beginPath();
      ctx.moveTo(0, gy);
      ctx.lineTo(w, gy);
      ctx.stroke();
    }
    // Defter marjin kırmızı çizgisi
    ctx.strokeStyle = 'rgba(239, 68, 68, 0.45)';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    ctx.moveTo(m + 55, 0);
    ctx.lineTo(m + 55, h);
    ctx.stroke();
    ctx.restore();
  } else if (currentCoverTheme === 'postage') {
    drawAirmailBorders(ctx, w, h, 26);
  }

  // 2. İç Blok / Kart (Hard Shadow & Container)
  const cardPad = currentCoverTheme === 'postage' ? 36 : 28;
  const cardX = cardPad;
  const cardY = cardPad;
  const cardW = w - (cardPad * 2);
  const cardH = h - (cardPad * 2);

  // Brutalist Sert Gölge (Paper, Fanzine, Kraft temalarında)
  if (currentCoverTheme === 'paper' || currentCoverTheme === 'fanzine' || currentCoverTheme === 'kraft') {
    ctx.save();
    ctx.fillStyle = currentCoverTheme === 'fanzine' ? '#000000' : 'rgba(0, 0, 0, 0.22)';
    ctx.fillRect(cardX + 10, cardY + 10, cardW, cardH);
    ctx.restore();
  }

  // İç Kart Gövdesi
  ctx.fillStyle = cardBg;
  ctx.fillRect(cardX, cardY, cardW, cardH);

  // Kart Çerçevesi
  ctx.strokeStyle = frameColor;
  ctx.lineWidth = currentCoverTheme === 'fanzine' ? 5 : 3.5;
  ctx.strokeRect(cardX, cardY, cardW, cardH);

  if (currentCoverTheme === 'fanzine') {
    // Üst kesik/perfore çizgi süsü
    ctx.save();
    ctx.setLineDash([12, 8]);
    ctx.lineWidth = 2;
    ctx.strokeStyle = '#000';
    ctx.beginPath();
    ctx.moveTo(cardX + 20, cardY + 28);
    ctx.lineTo(cardX + cardW - 20, cardY + 28);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.font = 'bold 12px monospace';
    ctx.fillStyle = '#000';
    ctx.fillText('✂ [ KUSURLU FANZİN KAPAK BASKISI - SAYI: 01 ]', cardX + 35, cardY + 23);
    ctx.restore();
  } else if (currentCoverTheme === 'bordeaux' || currentCoverTheme === 'emerald') {
    // Çift yaldız çizgi & köşe süsleri
    ctx.strokeStyle = accentColor;
    ctx.lineWidth = 1.5;
    ctx.strokeRect(cardX + 10, cardY + 10, cardW - 20, cardH - 20);
  } else if (currentCoverTheme === 'dark' || currentCoverTheme === 'blueprint') {
    // 4 köşeye matbaa krosları
    const crossOffset = 20;
    drawCornerCross(ctx, cardX + crossOffset, cardY + crossOffset, 10, accentColor);
    drawCornerCross(ctx, cardX + cardW - crossOffset, cardY + crossOffset, 10, accentColor);
    drawCornerCross(ctx, cardX + crossOffset, cardY + cardH - crossOffset, 10, accentColor);
    drawCornerCross(ctx, cardX + cardW - crossOffset, cardY + cardH - crossOffset, 10, accentColor);
  }

  // Posta Pulu Figürü (Postage Teması için)
  if (currentCoverTheme === 'postage') {
    ctx.save();
    const stampW = 95;
    const stampH = 115;
    const stampX = cardX + cardW - stampW - 25;
    const stampY = cardY + 25;
    
    ctx.fillStyle = '#fff9f0';
    ctx.strokeStyle = '#b91c1c';
    ctx.lineWidth = 2;
    ctx.setLineDash([5, 4]);
    ctx.strokeRect(stampX, stampY, stampW, stampH);
    ctx.fillRect(stampX, stampY, stampW, stampH);
    ctx.setLineDash([]);

    ctx.fillStyle = '#b91c1c';
    ctx.font = 'bold 11px "Courier New", monospace';
    ctx.textAlign = 'center';
    ctx.fillText('PRATFALL', stampX + (stampW / 2), stampY + 20);
    ctx.font = 'bold 22px Georgia, serif';
    ctx.fillText('✒️', stampX + (stampW / 2), stampY + 54);
    ctx.font = 'bold 10px "Courier New", monospace';
    ctx.fillText('100 KURUŞ', stampX + (stampW / 2), stampY + 80);

    // Damga dalgaları
    ctx.strokeStyle = 'rgba(30, 58, 138, 0.65)';
    ctx.lineWidth = 2;
    for (let dw = 0; dw < 3; dw++) {
      ctx.beginPath();
      const waveY = stampY + 30 + (dw * 12);
      ctx.moveTo(stampX - 40, waveY);
      ctx.bezierCurveTo(stampX - 20, waveY - 6, stampX - 5, waveY + 6, stampX + 15, waveY);
      ctx.stroke();
    }
    ctx.restore();
  }

  // 3. Üst Logo & Platform Başlığı
  const badgeTitle = document.getElementById('coverGenBadge').value.trim() || 'PRATFALL · EDEBİYAT & FİKİR & KÜLTÜR & SANAT PLATFORMU';
  const category = (document.getElementById('coverGenCategory') ? document.getElementById('coverGenCategory').value.trim() : 'Edebiyat') || 'Edebiyat';

  ctx.font = 'bold 15px "Courier New", monospace';
  ctx.fillStyle = mutedColor;
  ctx.textAlign = 'center';
  const headY = cardY + 42;
  ctx.fillText(badgeTitle.toUpperCase(), w / 2, headY);

  // Kategori Pulu (Sağ Üst Köşede)
  if (currentCoverTheme !== 'postage') {
    ctx.save();
    ctx.translate(cardX + cardW - 35, headY - 4);
    ctx.rotate(-0.02);
    ctx.fillStyle = badgeBg;
    ctx.strokeStyle = frameColor;
    ctx.lineWidth = 1.5;
    const catText = '✦ ' + category.toUpperCase() + ' ✦';
    ctx.font = 'bold 14px "Courier New", monospace';
    const bW = ctx.measureText(catText).width + 20;
    ctx.fillRect(-bW, -14, bW, 28);
    ctx.strokeRect(-bW, -14, bW, 28);
    ctx.fillStyle = badgeText;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(catText, -bW / 2, 0);
    ctx.restore();
  }

  // Üst Perfore Ayraç
  const headDivY = cardY + 74;
  ctx.strokeStyle = frameColor;
  ctx.lineWidth = 1.5;
  ctx.setLineDash([8, 6]);
  ctx.beginPath();
  ctx.moveTo(cardX + 25, headDivY);
  ctx.lineTo(cardX + cardW - 25, headDivY);
  ctx.stroke();
  ctx.setLineDash([]);

  // 4. Alt Bilgi Çubuğu (Yazar & Edebi Arşiv)
  const author = (document.getElementById('coverGenAuthor') ? document.getElementById('coverGenAuthor').value.trim() : 'Pratfall Yazarı') || 'Pratfall Yazarı';
  const footDivY = cardY + cardH - 74;

  ctx.strokeStyle = frameColor;
  ctx.lineWidth = 1.5;
  ctx.setLineDash([8, 6]);
  ctx.beginPath();
  ctx.moveTo(cardX + 25, footDivY);
  ctx.lineTo(cardX + cardW - 25, footDivY);
  ctx.stroke();
  ctx.setLineDash([]);

  // Yazar Bilgisi
  ctx.textAlign = 'center';
  ctx.font = 'bold 24px "Courier New", monospace';
  ctx.fillStyle = accentColor;
  ctx.fillText('[ YAZAR: ' + author.toUpperCase() + ' ]', w / 2, footDivY + 34);

  // Slogan & Domain
  ctx.font = '15px "Courier New", monospace';
  ctx.fillStyle = mutedColor;
  ctx.fillText('WWW.PRATFALL.COM.TR · KUSURLU KALMA ÇABASI', w / 2, footDivY + 58);

  // 5. Orta Başlık Alanı
  const rawTitle = (document.getElementById('coverGenTitle') ? document.getElementById('coverGenTitle').value.trim() : 'Kusurlu Kalma Çabası') || 'Kusurlu Kalma Çabası';
  const fontFam = currentCoverFont === 'mono' ? '"Courier New", monospace' : 'Georgia, serif';

  function wrapTitle(text, fSize) {
    ctx.font = `bold ${fSize}px ${fontFam}`;
    const words = text.split(/\s+/);
    let lns = [];
    let curLine = '';
    const maxW = cardW - 140;

    for (let n = 0; n < words.length; n++) {
      const test = curLine + words[n] + ' ';
      if (ctx.measureText(test).width > maxW && n > 0) {
        lns.push(curLine.trim());
        curLine = words[n] + ' ';
      } else {
        curLine = test;
      }
    }
    if (curLine.trim()) lns.push(curLine.trim());
    return lns;
  }

  let titleSize = 56;
  let titleLines = wrapTitle(rawTitle, titleSize);
  let lineHeight = titleSize * 1.35;
  let totalTitleH = titleLines.length * lineHeight;
  const maxTitleH = footDivY - headDivY - 60;

  while (totalTitleH > maxTitleH && titleSize > 28) {
    titleSize -= 3;
    lineHeight = titleSize * 1.35;
    titleLines = wrapTitle(rawTitle, titleSize);
    totalTitleH = titleLines.length * lineHeight;
  }

  const centerContentY = (headDivY + footDivY) / 2;
  const titleStartY = centerContentY - (totalTitleH / 2) + (titleSize * 0.38);

  // Fanzin Temasında Başlığın Arkasında Sarı Vurgulayıcı Bant
  if (currentCoverTheme === 'fanzine' && titleLines.length > 0) {
    ctx.save();
    ctx.fillStyle = 'rgba(254, 240, 138, 0.75)';
    for (let k = 0; k < titleLines.length; k++) {
      ctx.font = `bold ${titleSize}px ${fontFam}`;
      const metrics = ctx.measureText(titleLines[k]);
      const lW = metrics.width;
      const lx = (w / 2) - (lW / 2) - 10;
      const ly = titleStartY + (k * lineHeight) - (titleSize * 0.85);
      ctx.fillRect(lx, ly, lW + 20, titleSize * 1.05);
    }
    ctx.restore();
  }

  // Başlık Metnini Çiz
  ctx.textAlign = 'center';
  ctx.font = `bold ${titleSize}px ${fontFam}`;
  ctx.fillStyle = textColor;
  for (let i = 0; i < titleLines.length; i++) {
    ctx.fillText(titleLines[i], w / 2, titleStartY + (i * lineHeight));
  }

  // Başlığın Altına Edebi Ayraç
  const ornamentY = titleStartY + (titleLines.length * lineHeight) + 12;
  if (ornamentY < footDivY - 14) {
    ctx.font = 'bold 20px Georgia, serif';
    ctx.fillStyle = accentColor;
    ctx.fillText('◆  ·  —  ·  ◆', w / 2, ornamentY);
  }

  // 6. Kusurlu Katmanlar (Koli Bandı & Matbaa Kaşesi)
  if (optTape) {
    drawWashiTapeStrip(ctx, (w / 2) - 90, cardY - 12, 180, 34, -2, tapeColor);
    drawWashiTapeStrip(ctx, cardX + cardW - 130, cardY + cardH - 45, 140, 32, -35, tapeColor);
  }

  if (optStamp) {
    const stampRadius = 65;
    const stampX = cardX + 90;
    const stampY = cardY + cardH - 95;
    drawRubberStamp(ctx, stampX, stampY, stampRadius, -14, stampColor, 'KUSURLU KALMA ÇABASI');
  }

  // 7. Kağıt Lif & Doku (Noise) Katmanı
  if (optTexture) {
    drawNoiseTexture(ctx, w, h, 2400, (currentCoverTheme === 'dark' || currentCoverTheme === 'emerald') ? 'rgba(255, 255, 255, 0.035)' : 'rgba(0, 0, 0, 0.04)');
  }
}

function saveGeneratedCoverToPost() {
  const canvas = document.getElementById('coverGenCanvas');
  const btn = document.getElementById('saveCoverBtn');
  const status = document.getElementById('coverSaveStatus');
  if (!canvas || !btn) return;

  btn.disabled = true;
  btn.innerText = 'Kapak Optimize Edilip Kaydediliyor...';
  status.innerHTML = '<span style="color:var(--adm-primary);">Sunucuya WebP olarak aktarılıyor...</span>';

  const dataUrl = canvas.toDataURL('image/png');
  const formData = new FormData();
  formData.append('csrf_token', '<?= Auth::csrf_token(); ?>');
  formData.append('image_data', dataUrl);

  fetch('<?= SITE_URL; ?>/admin/posts.php?action=ajax_save_cover', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerText = '✓ Görseli Kaydet ve Yazıya Ata';
    if (data.success && data.url) {
      // 1. Forma görsel URL'sini ata
      const input = document.getElementById('featured_image_input');
      if (input) {
        input.value = data.url;
      }
      // 2. Önizlemeyi güncelle
      updateCoverPreview(data.url);

      status.innerHTML = '<span style="color:#10b981; font-weight:700;">✓ Kapak görseli başarıyla oluşturuldu ve yazıya atandı!</span>';
      setTimeout(() => {
        closeCoverGeneratorModal();
      }, 700);
    } else {
      status.innerHTML = '<span style="color:#ef4444;">Hata: ' + (data.message || 'Görsel kaydedilemedi.') + '</span>';
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerText = '✓ Görseli Kaydet ve Yazıya Ata';
    status.innerHTML = '<span style="color:#ef4444;">Bağlantı hatası: ' + err + '</span>';
  });
}

function downloadCoverImage() {
  const canvas = document.getElementById('coverGenCanvas');
  const title = (document.getElementById('coverGenTitle').value || 'pratfall-kapak').toLowerCase().replace(/[^a-z0-9]/g, '-');
  const link = document.createElement('a');
  link.download = `kapak-${title}-${Date.now()}.png`;
  link.href = canvas.toDataURL('image/png');
  link.click();
}
</script>

<!-- OTOMATİK ÖNE ÇIKAN KAPAK GÖRSELİ ÜRETİCİ MODAL (CANVAS MOTORU) -->
<div id="coverGeneratorModal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1.5rem;">
  <div style="background:var(--adm-card, #fff); max-width:960px; width:100%; border-radius:12px; border:1px solid var(--adm-border); box-shadow:0 25px 50px rgba(0,0,0,0.3); overflow:hidden; max-height:92vh; display:flex; flex-direction:column;">
    
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--adm-border); display:flex; justify-content:space-between; align-items:center; background:#fafbfc;">
      <div style="display:flex; align-items:center; gap:0.5rem;">
        <span style="font-size:1.3rem;">🎨</span>
        <div>
          <h3 style="margin:0; font-size:1.15rem; font-weight:700;">Otomatik Kapak Görseli Tasarımcısı</h3>
          <span style="font-size:0.75rem; color:var(--adm-muted);">Site geneli ve sosyal paylaşımlar için 16:9 (1200x675) edebi kapak üretir.</span>
        </div>
      </div>
      <button type="button" onclick="closeCoverGeneratorModal()" style="background:transparent; border:none; font-size:1.5rem; cursor:pointer; color:var(--adm-muted); padding:0 0.5rem;">&times;</button>
    </div>

    <div style="display:grid; grid-template-columns:460px 1fr; gap:1.5rem; padding:1.5rem; overflow-y:auto; align-items:start;">
      
      <!-- Sol: Canlı Kapak Önizleme (16:9 Canvas) -->
      <div style="display:flex; flex-direction:column; align-items:center;">
        <div style="width:100%; border:1px solid var(--adm-border); border-radius:8px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.15); background:#f0f0f0;">
          <canvas id="coverGenCanvas" width="1200" height="675" style="width:100%; height:auto; display:block;"></canvas>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.5rem; width:100%; margin-top:1rem;">
          <button type="button" onclick="saveGeneratedCoverToPost()" id="saveCoverBtn" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.65rem; font-weight:700; font-size:0.9rem;">
            ✓ Görseli Kaydet ve Yazıya Ata
          </button>
          <button type="button" onclick="downloadCoverImage()" class="btn btn-secondary btn-sm" style="width:100%; justify-content:center; font-size:0.8rem;">
            💾 Bilgisayara İndir (PNG)
          </button>
        </div>

        <div id="coverSaveStatus" style="font-size:0.75rem; margin-top:0.5rem; text-align:center; min-height:1.2rem;"></div>
      </div>

      <!-- Sağ: Düzenleme Parametreleri -->
      <div style="display:flex; flex-direction:column; gap:0.9rem;">
        
        <div>
          <label class="form-label" style="font-weight:600; margin-bottom:0.25rem;">Eser Başlığı (Kapakta Görünecek):</label>
          <input type="text" id="coverGenTitle" class="form-control" oninput="drawCoverImage()" style="font-weight:600;" />
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div>
            <label class="form-label" style="font-weight:600; margin-bottom:0.25rem;">Yazar Adı:</label>
            <input type="text" id="coverGenAuthor" class="form-control" oninput="drawCoverImage()" />
          </div>
          <div>
            <label class="form-label" style="font-weight:600; margin-bottom:0.25rem;">Edebi Tür / Kategori:</label>
            <input type="text" id="coverGenCategory" class="form-control" oninput="drawCoverImage()" />
          </div>
        </div>

        <div>
          <label class="form-label" style="font-weight:600; margin-bottom:0.25rem;">Üst Logo & Platform Başlığı:</label>
          <input type="text" id="coverGenBadge" class="form-control" oninput="drawCoverImage()" value="PRATFALL · EDEBİYAT & FİKİR & KÜLTÜR & SANAT PLATFORMU" />
        </div>

        <div>
          <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">Edebi Doku & Kusurluluk Teması (8 Farklı Tasarım):</label>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.45rem;" id="coverThemeButtonsGroup">
            <button type="button" class="btn btn-sm" id="coverThmPaperBtn" onclick="setCoverTheme('paper')" style="background:#1a1a1a; color:#fff; font-weight:700; font-size:0.75rem;">📜 Ham Kağıt & Bant</button>
            <button type="button" class="btn btn-sm btn-secondary" id="coverThmFanzineBtn" onclick="setCoverTheme('fanzine')" style="font-size:0.75rem;">🖨️ Kusurlu Fanzin</button>
            <button type="button" class="btn btn-sm btn-secondary" id="coverThmBlueprintBtn" onclick="setCoverTheme('blueprint')" style="font-size:0.75rem;">📐 Milimetrik Defter</button>
            <button type="button" class="btn btn-sm btn-secondary" id="coverThmDarkBtn" onclick="setCoverTheme('dark')" style="font-size:0.75rem;">🖤 Gece Daktilosu</button>
            <button type="button" class="btn btn-sm btn-secondary" id="coverThmBordeauxBtn" onclick="setCoverTheme('bordeaux')" style="font-size:0.75rem;">🍷 Matbaa Bordo</button>
            <button type="button" class="btn btn-sm btn-secondary" id="coverThmPostageBtn" onclick="setCoverTheme('postage')" style="font-size:0.75rem;">✉️ Posta Pulu & Zarf</button>
            <button type="button" class="btn btn-sm btn-secondary" id="coverThmKraftBtn" onclick="setCoverTheme('kraft')" style="font-size:0.75rem;">📦 Ham Kraft Koli</button>
            <button type="button" class="btn btn-sm btn-secondary" id="coverThmEmeraldBtn" onclick="setCoverTheme('emerald')" style="font-size:0.75rem;">🌲 Kütüphane Zümrüt</button>
          </div>
        </div>

        <div>
          <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Kusurlu Edebi Katmanlar (Detaylar):</label>
          <div style="display:flex; flex-wrap:wrap; gap:0.75rem; background:var(--adm-bg); padding:0.6rem 0.8rem; border-radius:6px; font-size:0.78rem;">
            <label style="display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer;">
              <input type="checkbox" id="coverOptTape" checked onchange="drawCoverImage()" /> Koli Bandı Şeritleri
            </label>
            <label style="display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer;">
              <input type="checkbox" id="coverOptStamp" checked onchange="drawCoverImage()" /> Kusurlu Matbaa Kaşesi
            </label>
            <label style="display:inline-flex; align-items:center; gap:0.35rem; cursor:pointer;">
              <input type="checkbox" id="coverOptTexture" checked onchange="drawCoverImage()" /> Kağıt Lif & Doku
            </label>
          </div>
        </div>

        <div>
          <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">Tipografi Dizgisi:</label>
          <div style="display:flex; gap:0.5rem;">
            <button type="button" class="btn btn-sm" id="coverFontSerifBtn" onclick="setCoverFont('serif')" style="flex:1; justify-content:center; background:#1a1a1a; color:#fff; font-weight:700; font-size:0.78rem;">Klasik Edebi Serif</button>
            <button type="button" class="btn btn-sm btn-secondary" id="coverFontMonoBtn" onclick="setCoverFont('mono')" style="flex:1; justify-content:center; font-size:0.78rem;">Daktilo / Brutalist</button>
          </div>
        </div>

        <div style="font-size:0.72rem; color:var(--adm-muted); line-height:1.4; background:#fafbfc; padding:0.6rem 0.8rem; border-radius:6px; border:1px solid #e2e8f0;">
          ✦ <strong>Otomatik Optimizasyon:</strong> "Görseli Kaydet ve Yazıya Ata" dediğinizde görsel WebP formatında optimize edilerek <code>uploads/covers/</code> dizinine kaydedilir ve yazı formuyla anında ilişkilendirilir.
        </div>

      </div>

    </div>

  </div>
</div>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
