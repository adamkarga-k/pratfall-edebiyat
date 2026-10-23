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

// Yazar Listesi
$authors = $db->query( "SELECT id, name FROM authors ORDER BY name ASC" )->fetchAll();

// Kategori Listesi
$all_categories = $db->query( "SELECT * FROM categories ORDER BY order_num ASC, name ASC" )->fetchAll();

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
                <div id="editorVisual" class="editor-content-area" contenteditable="true"><?= $post['content']; ?></div>

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
            
            <?php if ( ! empty( $post['featured_image'] ) ) : ?>
              <div style="margin-bottom:0.75rem; border-radius:var(--adm-radius-sm); overflow:hidden; border:1px solid var(--adm-border);">
                <img src="<?= $post['featured_image']; ?>" alt="Kapak" style="width:100%; height:130px; object-fit:cover; display:block;" />
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label class="form-label">Bilgisayardan Yükle (Otomatik Sıkıştırılır)</label>
              <input type="file" name="image_file" accept="image/*" class="form-control" style="padding:0.35rem;" />
            </div>

            <div class="form-group" style="margin-bottom:0.75rem;">
              <label class="form-label">Veya Görsel URL'si</label>
              <input type="text" name="featured_image" id="featured_image_input" class="form-control" value="<?= htmlspecialchars( $post['featured_image'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="https://..." style="font-size:0.8rem;" />
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
        if (textAlignLast && textAlignLast !== 'auto') {
          target.style.textAlignLast = textAlignLast;
        } else {
          target.style.removeProperty('text-align-last');
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

    document.getElementById('editorVisual')?.addEventListener('input', syncContent);
    document.getElementById('editorVisual')?.addEventListener('blur', syncContent);

    document.getElementById('postForm')?.addEventListener('submit', function() {
      if (!isHtmlMode) {
        syncContent();
      }
    });

    // Hızlı Yazar Ekleme Fonksiyonları
    function toggleQuickAuthorBox() {
      var box = document.getElementById('quickAuthorBox');
      if (box.style.display === 'none' || box.style.display === '') {
        box.style.display = 'block';
        document.getElementById('quickAuthorName').focus();
      } else {
        box.style.display = 'none';
      }
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
      var title = titleInput.value.trim();

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
          // 1. Yeni yazarı dropdown listesine ekle ve seç
          var sel = document.getElementById('author_id');
          var opt = document.createElement('option');
          opt.value = data.id;
          opt.textContent = data.name + (data.title ? ' (' + data.title + ')' : '');
          opt.selected = true;
          sel.appendChild(opt);

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
                Henüz kayıtlı bir metin bulunmuyor.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
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
          <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Kart Formatı:</label>
          <div style="display:flex; gap:0.5rem;">
            <button type="button" class="btn btn-sm" id="fmtStoryBtn" onclick="setCardFormat('story')" style="flex:1; justify-content:center; font-weight:bold; background:#1a1a1a; color:#fff;">Story (9:16)</button>
            <button type="button" class="btn btn-sm btn-secondary" id="fmtSquareBtn" onclick="setCardFormat('square')" style="flex:1; justify-content:center;">Kare / Post (1:1)</button>
          </div>
        </div>

        <div>
          <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:0.35rem;">Renk & Doku Teması:</label>
          <div style="display:flex; gap:0.5rem;">
            <button type="button" class="btn btn-sm" onclick="setCardTheme('paper')" style="flex:1; justify-content:center; background:#fbf7ee; color:#1a1a1a; border:1.5px solid #1a1a1a; font-weight:600;">Ham Kağıt</button>
            <button type="button" class="btn btn-sm" onclick="setCardTheme('dark')" style="flex:1; justify-content:center; background:#141414; color:#f4eee1; border:1px solid #333; font-weight:600;">Gece Daktilosu</button>
            <button type="button" class="btn btn-sm" onclick="setCardTheme('red')" style="flex:1; justify-content:center; background:#8b1e0f; color:#fbf7ee; border:1px solid #8b1e0f; font-weight:600;">Matbaa Bordo</button>
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
let currentFormat = 'story'; // 'story' veya 'square'
let currentTheme = 'paper';   // 'paper', 'dark', 'red'

function openStoryModal(data) {
  document.getElementById('cardQuoteText').value = data.excerpt || data.title || '';
  document.getElementById('cardAuthor').value = data.author || 'Pratfall Yazarı';
  document.getElementById('cardTitle').value = data.title || '';
  
  var modal = document.getElementById('storyModal');
  modal.style.display = 'flex';
  
  setCardFormat('story');
}

function closeStoryModal() {
  document.getElementById('storyModal').style.display = 'none';
}

function setCardFormat(fmt) {
  currentFormat = fmt;
  const canvas = document.getElementById('quoteCanvas');
  const btnStory = document.getElementById('fmtStoryBtn');
  const btnSquare = document.getElementById('fmtSquareBtn');

  if (fmt === 'story') {
    canvas.width = 1080;
    canvas.height = 1920;
    btnStory.style.background = '#1a1a1a';
    btnStory.style.color = '#fff';
    btnSquare.style.background = '';
    btnSquare.style.color = '';
  } else {
    canvas.width = 1080;
    canvas.height = 1080;
    btnSquare.style.background = '#1a1a1a';
    btnSquare.style.color = '#fff';
    btnStory.style.background = '';
    btnStory.style.color = '';
  }
  drawQuoteCard();
}

function setCardTheme(thm) {
  currentTheme = thm;
  drawQuoteCard();
}

function drawQuoteCard() {
  const canvas = document.getElementById('quoteCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width;
  const h = canvas.height;

  // Tema Renkleri
  let bgColor = '#fbf7ee';
  let textColor = '#1a1a1a';
  let accentColor = '#8b1e0f';
  let frameColor = '#1a1a1a';
  let mutedColor = '#555555';

  if (currentTheme === 'dark') {
    bgColor = '#141414';
    textColor = '#f4eee1';
    accentColor = '#d97706';
    frameColor = '#333333';
    mutedColor = '#888888';
  } else if (currentTheme === 'red') {
    bgColor = '#8b1e0f';
    textColor = '#fbf7ee';
    accentColor = '#fbf7ee';
    frameColor = '#fbf7ee';
    mutedColor = 'rgba(251,247,238,0.7)';
  }

  // 1. Zemin Doldur
  ctx.fillStyle = bgColor;
  ctx.fillRect(0, 0, w, h);

  // 2. Dış Çerçeve
  const margin = 48;
  ctx.strokeStyle = frameColor;
  ctx.lineWidth = 6;
  ctx.strokeRect(margin, margin, w - margin * 2, h - margin * 2);

  // 3. Üst Logo & Slogan
  ctx.textAlign = 'center';
  ctx.fillStyle = accentColor;
  ctx.font = 'bold 28px "Courier New", monospace';
  ctx.fillText('✦ PRATFALL · EDEBİYAT PLATFORMU ✦', w / 2, margin + 70);

  ctx.fillStyle = mutedColor;
  ctx.font = '22px "Courier New", monospace';
  ctx.fillText('KUSURLU KALMA ÇABASI', w / 2, margin + 110);

  // Ayırıcı çizgi üst
  ctx.strokeStyle = frameColor;
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(w / 2 - 120, margin + 140);
  ctx.lineTo(w / 2 + 120, margin + 140);
  ctx.stroke();

  // 4. Dekoratif Alıntı İmi
  ctx.font = 'bold 120px Georgia, serif';
  ctx.fillStyle = accentColor;
  ctx.fillText('“', w / 2, currentFormat === 'story' ? h * 0.28 : h * 0.25);

  // 5. Alıntı Metni
  const quote = document.getElementById('cardQuoteText').value.trim() || 'Sözcüklerin kusuru, insanın hakiki yarasına denk gelir.';
  
  // Metin Boyutu Hesaplama
  let fontSize = currentFormat === 'story' ? (quote.length > 200 ? 46 : 56) : (quote.length > 200 ? 38 : 46);
  ctx.font = `italic ${fontSize}px Georgia, serif`;
  ctx.fillStyle = textColor;

  const maxTextWidth = w - 240;
  const lineHeight = fontSize * 1.6;

  // Kelime Sarma (Word Wrap)
  const words = quote.split(/\s+/);
  let lines = [];
  let currentLine = '';

  for (let n = 0; n < words.length; n++) {
    const testLine = currentLine + words[n] + ' ';
    const metrics = ctx.measureText(testLine);
    if (metrics.width > maxTextWidth && n > 0) {
      lines.push(currentLine.trim());
      currentLine = words[n] + ' ';
    } else {
      currentLine = testLine;
    }
  }
  lines.push(currentLine.trim());

  // Metni dikeyde ortala
  const totalTextHeight = lines.length * lineHeight;
  let startY = (h / 2) - (totalTextHeight / 2) + 20;
  if (currentFormat === 'story') {
    startY = h * 0.38 - (totalTextHeight / 2);
  }

  for (let k = 0; k < lines.length; k++) {
    ctx.fillText(lines[k], w / 2, startY + (k * lineHeight));
  }

  // 6. Yazar & Eser Bilgisi (Alt Bölüm)
  const author = document.getElementById('cardAuthor').value.trim();
  const title = document.getElementById('cardTitle').value.trim();
  const bottomY = currentFormat === 'story' ? h - margin - 220 : h - margin - 150;

  // Ayırıcı minik çizgi
  ctx.beginPath();
  ctx.moveTo(w / 2 - 80, bottomY - 60);
  ctx.lineTo(w / 2 + 80, bottomY - 60);
  ctx.stroke();

  if (author) {
    ctx.font = 'bold 36px "Courier New", monospace';
    ctx.fillStyle = accentColor;
    ctx.fillText('[ ' + author.toUpperCase() + ' ]', w / 2, bottomY);
  }

  if (title) {
    ctx.font = '26px Georgia, serif';
    ctx.fillStyle = mutedColor;
    ctx.fillText('« ' + title + ' »', w / 2, bottomY + 45);
  }

  // En alt domain
  ctx.font = '22px "Courier New", monospace';
  ctx.fillStyle = mutedColor;
  ctx.fillText('pratfall.com.tr', w / 2, h - margin - 35);
}

function downloadQuoteCard() {
  const canvas = document.getElementById('quoteCanvas');
  const title = (document.getElementById('cardAuthor').value || 'pratfall').toLowerCase().replace(/[^a-z0-9]/g, '-');
  const link = document.createElement('a');
  link.download = `pratfall-alinti-${title}-${Date.now()}.png`;
  link.href = canvas.toDataURL('image/png');
  link.click();
}
</script>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
