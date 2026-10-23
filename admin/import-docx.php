<?php
/**
 * Pratfall Standalone - Word (.docx) Toplu İçe Aktarıcı & Düzenleme Masası
 * 
 * Bilgisayardan yüklenen Word belgelerini çözümler;
 * başlık, yazar, kategori, önerilen etiketler, özet ve eksiksiz HTML içeriği çıkarır.
 * Kullanıcının toplu olarak inceleyip düzenlemesine ve tek tıkla kaydetmesine imkan tanır.
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Word (.docx) Toplu İçe Aktar';
$active_tab = 'import';

$db = Database::get();
$authors = $db->query( "SELECT id, name FROM authors" )->fetchAll();
Helpers::sort_turkish_alphabetical( $authors );

// 0. AJAX Otomatik Üretilen Kapak Görselini Kaydet
if ( isset( $_GET['action'] ) && $_GET['action'] === 'ajax_save_cover' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    header( 'Content-Type: application/json; charset=UTF-8' );
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        echo json_encode( [ 'success' => false, 'message' => 'Güvenlik doğrulaması (CSRF) başarısız.' ] );
        exit;
    }
    $imageData = (string) ( $_POST['image_data'] ?? '' );
    if ( empty( $imageData ) ) {
        echo json_encode( [ 'success' => false, 'message' => 'Görsel verisi boş.' ] );
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
$categories = $db->query( "SELECT * FROM categories ORDER BY order_num ASC, name ASC" )->fetchAll();
if ( empty( $categories ) ) {
    $categories = [
        [ 'name' => 'Deneme' ],
        [ 'name' => 'Şiir' ],
        [ 'name' => 'İnceleme' ],
        [ 'name' => 'Hikaye' ],
        [ 'name' => 'Söyleşi' ],
        [ 'name' => 'Eleştiri' ],
    ];
}

$step = 'upload'; // upload | review | success
$parsed_items = [];
$results = [];
$errors = [];

// ==========================================================
// 1. AŞAMA: Toplu Formdan SQLite'a Nihai Kayıt (Save Bulk)
// ==========================================================
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['bulk_action'] ) && $_POST['bulk_action'] === 'save_bulk' ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $errors[] = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $items = $_POST['items'] ?? [];
        $selected = $_POST['selected'] ?? [];

        $saved_count = 0;
        foreach ( $items as $idx => $data ) {
            // Sadece seçili olanları kaydet
            if ( ! isset( $selected[$idx] ) ) {
                continue;
            }

            try {
                $post_id = DocxImporter::import_to_database( $data );
                
                $imported_post = $db->query( "
                    SELECT p.*, a.name AS author_name 
                    FROM posts p 
                    LEFT JOIN authors a ON p.author_id = a.id 
                    WHERE p.id = {$post_id}
                " )->fetch();

                $results[] = [
                    'post_id'      => $post_id,
                    'title'        => $imported_post['title'],
                    'slug'         => $imported_post['slug'],
                    'author'       => $imported_post['author_name'] ?? 'Bilinmiyor',
                    'category'     => $imported_post['category'],
                    'tags'         => $imported_post['tags'] ?? '',
                    'reading_time' => $imported_post['reading_time'],
                ];
                $saved_count++;
            } catch ( Exception $e ) {
                $errors[] = "Eser kaydedilirken hata oluştu ({$data['title']}): " . $e->getMessage();
            }
        }

        if ( $saved_count > 0 ) {
            $step = 'success';
        }
    }
}

// ==========================================================
// 2. AŞAMA: Dosyaları Yükle ve Çözümle (Upload & Parse)
// ==========================================================
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_FILES['docx_files'] ) ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $errors[] = 'Güvenlik doğrulaması (CSRF) başarısız oldu.';
    } else {
        $default_author_id = (int) ( $_POST['default_author_id'] ?? 0 );
        $default_category  = trim( $_POST['default_category'] ?? '' );
        $default_status    = trim( $_POST['default_status'] ?? 'published' );
        $direct_save       = ! empty( $_POST['direct_save'] );

        $files = $_FILES['docx_files'];
        $file_count = count( $files['name'] );

        for ( $i = 0; $i < $file_count; $i++ ) {
            $name     = $files['name'][$i];
            $tmp_name = $files['tmp_name'][$i];
            $error    = $files['error'][$i];

            if ( $error !== UPLOAD_ERR_OK || empty( $tmp_name ) ) {
                continue;
            }

            $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            if ( $ext !== 'docx' ) {
                $errors[] = "{$name}: Sadece .docx uzantılı Word dosyaları desteklenir.";
                continue;
            }

            try {
                // Word belgesini derinlemesine çözümle
                $parsed = DocxImporter::parse( $tmp_name, $name );

                // Kategori ve yazar öncelikleri
                if ( ! empty( $default_category ) ) {
                    $parsed['category'] = $default_category;
                }
                
                // Yazar eşleştirme
                $author_id = $default_author_id;
                if ( $author_id === 0 && ! empty( $parsed['author_name'] ) ) {
                    // Veritabanındaki yazar isimleriyle eşleşiyor mu kontrol et
                    foreach ( $authors as $a ) {
                        if ( mb_strtolower( $a['name'], 'UTF-8' ) === mb_strtolower( $parsed['author_name'], 'UTF-8' ) ) {
                            $author_id = (int) $a['id'];
                            break;
                        }
                    }

                    // Eşleşen yazar bulunamadıysa veritabanına otomatik yeni yazar olarak kaydet
                    if ( $author_id === 0 && mb_strlen( $parsed['author_name'], 'UTF-8' ) >= 3 ) {
                        try {
                            $new_slug = Database::slugify( $parsed['author_name'] );
                            $check_slug = $db->prepare( "SELECT id FROM authors WHERE slug = ?" );
                            $check_slug->execute( [ $new_slug ] );
                            if ( $check_slug->fetch() ) {
                                $new_slug .= '-' . time();
                            }
                            $auto_title = ( mb_stripos( $parsed['category'] ?? '', 'şiir' ) !== false || mb_stripos( $parsed['category'] ?? '', 'siir' ) !== false ) ? 'Şair' : 'Yazar';
                            $ins_a = $db->prepare( "INSERT INTO authors (name, slug, title) VALUES (?, ?, ?)" );
                            $ins_a->execute( [ $parsed['author_name'], $new_slug, $auto_title ] );
                            $author_id = (int) $db->lastInsertId();
                            $authors[] = [ 'id' => $author_id, 'name' => $parsed['author_name'] ];
                            Helpers::sort_turkish_alphabetical( $authors );
                        } catch ( Throwable $e ) {}
                    }
                }
                $parsed['author_id'] = $author_id;
                $parsed['status']    = $default_status;
                $parsed['filename']  = $name;

                if ( $direct_save ) {
                    // Doğrudan kaydet modu seçildiyse beklemeden aktar
                    $post_id = DocxImporter::import_to_database( $parsed );
                    $results[] = [
                        'post_id'      => $post_id,
                        'title'        => $parsed['title'],
                        'slug'         => $parsed['slug'],
                        'author'       => $parsed['author_name'] ?? 'Bilinmiyor',
                        'category'     => $parsed['category'],
                        'tags'         => $parsed['tags'] ?? '',
                        'reading_time' => $parsed['reading_time'],
                    ];
                } else {
                    // Toplu inceleme ve düzenleme masasına ekle
                    $parsed_items[] = $parsed;
                }

            } catch ( Exception $e ) {
                $errors[] = "{$name}: " . $e->getMessage();
            }
        }

        if ( $direct_save && ! empty( $results ) ) {
            $step = 'success';
        } elseif ( ! empty( $parsed_items ) ) {
            $step = 'review';
        }
    }
}

require PATH_ADMIN . '/layout/header.php';
?>

<div class="admin-header-row" style="margin-bottom:1.5rem;">
  <div>
    <h1 class="topbar-page-title" style="display:flex; align-items:center; gap:0.5rem;">
      <span>📥</span> Word (.docx) Toplu İçe Aktarma & Düzenleme Masası
    </h1>
    <p style="margin:0.25rem 0 0 0; color:var(--adm-muted); font-size:0.87rem;">
      Bilgisayarınızdaki Word belgelerini seçin; başlıklar, kategoriler, önerilen etiketler, özetler ve tam içerikler otomatik çıkarılsın. Topluca düzenleyip tek tıkla yayına alın.
    </p>
  </div>
</div>

<?php if ( ! empty( $errors ) ) : ?>
  <div class="alert alert-error" style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:1rem 1.25rem; border-radius:var(--adm-radius-md); margin-bottom:1.5rem;">
    <strong>Bazı belgelerde uyarı veya hata oluştu:</strong>
    <ul style="margin:0.5rem 0 0 1.2rem; padding:0;">
      <?php foreach ( $errors as $err ) : ?>
        <li><?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- ==========================================================
     DURUM 1: BAŞARI RAPORU (SUCCESS)
     ========================================================== -->
<?php if ( $step === 'success' && ! empty( $results ) ) : ?>
  <div class="card" style="border-left:4px solid var(--adm-green); margin-bottom:2rem;">
    <div class="card-body" style="padding:1.5rem;">
      <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
        <span style="font-size:1.8rem;">🎉</span>
        <div>
          <h2 style="margin:0; font-size:1.25rem; font-weight:700; color:var(--adm-text);">
            Tebrikler! <?= count( $results ); ?> Adet Eser Başarıyla İçe Aktarıldı
          </h2>
          <div style="font-size:0.85rem; color:var(--adm-muted);">
            Tüm içerikler, biçimlendirmeler, kategoriler ve etiketler eksiksiz olarak kaydedildi.
          </div>
        </div>
      </div>

      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Başlık</th>
              <th>Kategori</th>
              <th>Etiketler</th>
              <th>Yazar</th>
              <th>Süre</th>
              <th style="text-align:right;">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $results as $r ) : ?>
              <tr>
                <td><strong><?= htmlspecialchars( $r['title'], ENT_QUOTES, 'UTF-8' ); ?></strong></td>
                <td><span class="pill-badge pill-purple"><?= htmlspecialchars( $r['category'], ENT_QUOTES, 'UTF-8' ); ?></span></td>
                <td style="font-size:0.8rem; color:var(--adm-muted);"><?= htmlspecialchars( $r['tags'] ?: '—', ENT_QUOTES, 'UTF-8' ); ?></td>
                <td><?= htmlspecialchars( $r['author'], ENT_QUOTES, 'UTF-8' ); ?></td>
                <td><?= $r['reading_time']; ?> dk</td>
                <td style="text-align:right;">
                  <a href="<?= SITE_URL; ?>/eser/<?= $r['slug']; ?>" target="_blank" class="btn btn-sm">Canlı Gör ↗</a>
                  <a href="<?= SITE_URL; ?>/admin/posts.php?action=edit&id=<?= $r['post_id']; ?>" class="btn btn-sm btn-primary">Düzenle</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:1.5rem; display:flex; gap:1rem;">
        <a href="<?= SITE_URL; ?>/admin/import-docx.php" class="btn btn-accent">
          + Yeni Belgeler İçe Aktar
        </a>
        <a href="<?= SITE_URL; ?>/admin/posts.php" class="btn">
          Yazılar Listesine Git →
        </a>
      </div>
    </div>
  </div>
<?php endif; ?>


<!-- ==========================================================
     DURUM 2: TOPLU DÜZENLEME VE İNCELEME MASASI (REVIEW)
     ========================================================== -->
<?php if ( $step === 'review' && ! empty( $parsed_items ) ) : ?>
  
  <form method="POST" action="<?= SITE_URL; ?>/admin/import-docx.php" id="bulkSaveForm">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />
    <input type="hidden" name="bulk_action" value="save_bulk" />

    <div class="card" style="margin-bottom:1.5rem; background:#f8fafc; border:1px solid #cbd5e1;">
      <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; padding:1rem 1.25rem;">
        <div style="display:flex; align-items:center; gap:0.75rem;">
          <span style="font-size:1.5rem;">📋</span>
          <div>
            <div style="font-weight:700; font-size:1rem; color:var(--adm-text);">
              <?= count( $parsed_items ); ?> Adet Word Belgesi İncelenmek Üzere Çözümlendi
            </div>
            <div style="font-size:0.8rem; color:var(--adm-muted);">
              Aşağıdaki listeden başlıkları, kategorileri, önerilen etiketleri ve kısa açıklamaları dilediğiniz gibi düzenleyebilir, "İçeriği Gör" butonuyla metni kontrol edebilirsiniz.
            </div>
          </div>
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
          <button type="button" class="btn btn-sm" onclick="bulkAutoGenerateAllCovers()" id="btnBulkGenCovers" style="color:var(--adm-accent); border:1px solid var(--adm-accent); background:#ffffff; font-weight:600;" title="Seçili tüm eserler için otomatik 16:9 kapak görseli oluşturur">
            🎨 Tümüne Otomatik Kapak Üret
          </button>
          <button type="button" class="btn btn-sm" onclick="openBulkQuickAuthorModal()" style="color:var(--adm-primary); border:1px solid var(--adm-primary); background:#ffffff;">
            👤 + Yeni Yazar Ekle
          </button>
          <button type="button" class="btn btn-sm" onclick="toggleSelectAll()">
            Tümünü Seç / Kaldır
          </button>
          <button type="submit" class="btn btn-accent" style="padding:0.6rem 1.25rem; font-weight:700;">
            ✓ Seçilen Eserleri Toplu Olarak Sisteme Aktar
          </button>
        </div>
      </div>
    </div>

    <!-- Eser Listesi Tablosu / Kartları -->
    <div style="display:flex; flex-direction:column; gap:1.25rem; margin-bottom:2rem;">
      <?php foreach ( $parsed_items as $idx => $item ) : ?>
        <div class="card" style="margin-bottom:0;" id="item_card_<?= $idx; ?>">
          
          <!-- Kart Üst Barı -->
          <div class="card-header" style="background:#fafbfc; display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1.25rem;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
              <input type="checkbox" name="selected[<?= $idx; ?>]" value="1" checked class="item-checkbox" style="width:18px; height:18px; cursor:pointer;" />
              <span style="font-family:var(--adm-font-mono); font-size:0.82rem; font-weight:600; color:var(--adm-subtle);">
                📄 <?= htmlspecialchars( $item['filename'], ENT_QUOTES, 'UTF-8' ); ?>
              </span>
              <span class="pill-badge pill-gray" style="font-size:0.7rem;">
                <?= $item['word_count']; ?> kelime · ~<?= $item['reading_time']; ?> dk
              </span>
              <?php if ( $item['images_count'] > 0 ) : ?>
                <span class="pill-badge pill-green" style="font-size:0.7rem;">
                  🖼️ <?= $item['images_count']; ?> Görsel Ayıklandı
                </span>
              <?php endif; ?>
            </div>

            <div>
              <button type="button" class="btn btn-sm" onclick="openContentPreview(<?= $idx; ?>)" style="font-size:0.76rem;">
                👁️ Word İçeriğini Önizle
              </button>
            </div>
          </div>

          <!-- Kart Form Alanları -->
          <div class="card-body" style="padding:1.25rem;">
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:1rem; margin-bottom:1rem;">
              
              <!-- Başlık -->
              <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.3rem;">
                  <label class="form-label" style="font-weight:600; margin:0;">Eser Başlığı</label>
                  <span style="font-size:0.7rem; color:var(--adm-muted); font-weight:normal;">✦ İlk Harfleri Büyük</span>
                </div>
                <input type="text" name="items[<?= $idx; ?>][title]" id="item_title_<?= $idx; ?>" class="form-control" value="<?= htmlspecialchars( Helpers::turkish_title_case( $item['title'] ), ENT_QUOTES, 'UTF-8' ); ?>" required style="font-weight:600;" onblur="this.value = toTurkishTitleCase(this.value)" />
              </div>

              <!-- Kategori -->
              <div>
                <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">Edebi Tür / Kategori</label>
                <select name="items[<?= $idx; ?>][category]" id="item_category_<?= $idx; ?>" class="form-control">
                  <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?= htmlspecialchars( $cat['name'], ENT_QUOTES, 'UTF-8' ); ?>" <?= strtolower( $item['category'] ) === strtolower( $cat['name'] ) ? 'selected' : ''; ?>>
                      <?= htmlspecialchars( $cat['name'], ENT_QUOTES, 'UTF-8' ); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Yazar -->
              <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.3rem;">
                  <label class="form-label" style="font-weight:600; margin:0;">Yazar</label>
                  <button type="button" onclick="openBulkQuickAuthorModal(<?= $idx; ?>)" class="btn btn-sm" style="padding:0.1rem 0.45rem; font-size:0.7rem; color:var(--adm-primary); background:rgba(0,0,0,0.03); border:1px dashed var(--adm-primary); cursor:pointer;">
                    + Yeni Ekle
                  </button>
                </div>
                <select name="items[<?= $idx; ?>][author_id]" id="item_author_<?= $idx; ?>" class="form-control author-select-field">
                  <option value="0">✦ Ortak Platform Metni</option>
                  <?php foreach ( $authors as $a ) : ?>
                    <option value="<?= $a['id']; ?>" <?= $item['author_id'] == $a['id'] ? 'selected' : ''; ?>>
                      <?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

            </div>

            <div style="display:grid; grid-template-columns: 1fr 1.6fr 1fr 1fr; gap:1rem; align-items:start;">
              
              <!-- Önerilen Etiketler -->
              <div>
                <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">
                  Önerilen Etiketler <span style="font-size:0.75rem; color:var(--adm-muted);">(Virgülle)</span>
                </label>
                <input type="text" name="items[<?= $idx; ?>][tags]" class="form-control" value="<?= htmlspecialchars( $item['tags'], ENT_QUOTES, 'UTF-8' ); ?>" placeholder="edebiyat, felsefe, şiir" />
              </div>

              <!-- Önerilen Kısa Açıklama (Özet) -->
              <div>
                <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">
                  Önerilen Kısa Açıklama (Özet / Excerpt)
                </label>
                <textarea name="items[<?= $idx; ?>][excerpt]" class="form-control" rows="2" style="font-size:0.85rem; line-height:1.4; resize:vertical;"><?= htmlspecialchars( $item['excerpt'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
              </div>

              <!-- Yayın Durumu -->
              <div>
                <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">Yayın Durumu</label>
                <select name="items[<?= $idx; ?>][status]" class="form-control">
                  <option value="published" <?= $item['status'] === 'published' ? 'selected' : ''; ?>>Doğrudan Yayında</option>
                  <option value="draft" <?= $item['status'] === 'draft' ? 'selected' : ''; ?>>Taslak Olarak Sakla</option>
                </select>
              </div>

              <!-- Öne Çıkan Görsel (16:9 Otomatik Kapak) -->
              <div>
                <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">Öne Çıkan Görsel</label>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                  <div id="cover_preview_box_<?= $idx; ?>" style="width:62px; height:35px; border:1px solid var(--adm-border); border-radius:3px; overflow:hidden; background:#f1f5f9; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <?php if ( ! empty( $item['featured_image'] ) ) : ?>
                      <img src="<?= htmlspecialchars( $item['featured_image'] ); ?>" id="cover_preview_img_<?= $idx; ?>" style="width:100%; height:100%; object-fit:cover; display:block;" />
                      <span id="cover_preview_placeholder_<?= $idx; ?>" style="display:none; font-size:0.55rem; color:var(--adm-muted); text-align:center;">KAPAK YOK</span>
                    <?php else : ?>
                      <span id="cover_preview_placeholder_<?= $idx; ?>" style="font-size:0.55rem; color:var(--adm-muted); text-align:center;">KAPAK YOK</span>
                      <img src="" id="cover_preview_img_<?= $idx; ?>" style="width:100%; height:100%; object-fit:cover; display:none;" />
                    <?php endif; ?>
                  </div>
                  <button type="button" onclick="openBulkCoverModal(<?= $idx; ?>)" class="btn btn-sm btn-primary" style="font-size:0.75rem; padding:0.35rem 0.55rem; white-space:nowrap;" title="Bu eser için 16:9 edebi kapak görseli tasarla">
                    🎨 Kapak Hazırla
                  </button>
                </div>
                <input type="hidden" name="items[<?= $idx; ?>][featured_image]" id="item_featured_image_<?= $idx; ?>" value="<?= htmlspecialchars( $item['featured_image'], ENT_QUOTES, 'UTF-8' ); ?>" />
              </div>

            </div>

            <!-- Gizli / Saklanan Alanlar (İçerik) -->
            <textarea name="items[<?= $idx; ?>][content]" id="hidden_content_<?= $idx; ?>" style="display:none;"><?= htmlspecialchars( $item['content'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
            <input type="hidden" name="items[<?= $idx; ?>][reading_time]" value="<?= $item['reading_time']; ?>" />

          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Alt Sabit Onay Butonu -->
    <div style="position:sticky; bottom:1.5rem; background:#ffffff; border:1px solid var(--adm-border); border-radius:var(--adm-radius-md); padding:1rem 1.5rem; box-shadow:0 10px 25px rgba(0,0,0,0.1); display:flex; justify-content:space-between; align-items:center;">
      <a href="<?= SITE_URL; ?>/admin/import-docx.php" class="btn">
        ← İptal Et ve Başa Dön
      </a>
      <button type="submit" class="btn btn-accent" style="padding:0.75rem 2rem; font-size:1rem; font-weight:700;">
        ✓ Seçilen Eserleri Toplu Olarak Sisteme Aktar
      </button>
    </div>

  </form>

<?php endif; ?>


<!-- ==========================================================
     DURUM 3: YÜKLEME FORMU (UPLOAD STEP)
     ========================================================== -->
<?php if ( $step === 'upload' ) : ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem; align-items:start;">
  
  <!-- Yükleme Kutusu -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">1. Word (.docx) Dosyalarını Seçin</h2>
    </div>
    <div class="card-body">
      
      <form method="POST" action="<?= SITE_URL; ?>/admin/import-docx.php" enctype="multipart/form-data" id="docxUploadForm">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />

        <!-- Sürükle Bırak Alanı -->
        <div class="dropzone-box" onclick="document.getElementById('fileInput').click()" id="dropzone" style="border:2px dashed #cbd5e1; border-radius:var(--adm-radius-md); padding:2.5rem 1.5rem; text-align:center; background:#fafbfc; cursor:pointer; transition:all 0.2s ease;">
          <div style="font-size:3rem; margin-bottom:0.75rem;">📄 ➔ 📚</div>
          <div style="font-size:1.15rem; font-weight:700; color:var(--adm-text); margin-bottom:0.35rem;">
            Word (.docx) Dosyalarını Buraya Sürükleyin
          </div>
          <p style="margin:0; font-size:0.85rem; color:var(--adm-muted);">
            veya bilgisayarınızdan çoklu seçim yapmak için tıklayın
          </p>
          <div style="margin-top:1.25rem;">
            <span class="btn btn-primary" style="pointer-events:none; font-size:0.88rem;">
              + Dosyaları Seç (Birden Fazla Dosya Desteklenir)
            </span>
          </div>
          <input type="file" id="fileInput" name="docx_files[]" multiple accept=".docx" style="display:none;" onchange="showSelectedFiles(this)" />
        </div>

        <!-- Seçilen Dosyaların Önizleme Listesi -->
        <div id="selectedFilesList" style="margin-top:1.25rem; display:none;">
          <div style="font-size:0.85rem; font-weight:700; margin-bottom:0.4rem; color:var(--adm-text);">
            Seçilen Dosyalar (<span id="selectedCount">0</span>):
          </div>
          <ul id="fileNamesUl" style="font-family:var(--adm-font-mono); font-size:0.8rem; background:#f1f5f9; padding:0.75rem 1.5rem; border-radius:var(--adm-radius-sm); border:1px solid #e2e8f0; max-height:160px; overflow-y:auto; margin:0;"></ul>
        </div>

        <!-- Genel Ön Ayarlar -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:1.5rem;">
          
          <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
              <label class="form-label" style="margin:0;">Varsayılan Yazar (Öncelik)</label>
              <button type="button" onclick="openBulkQuickAuthorModal()" class="btn btn-sm" style="padding:0.15rem 0.5rem; font-size:0.72rem; color:var(--adm-primary); background:rgba(0,0,0,0.03); border:1px dashed var(--adm-primary); cursor:pointer;">
                + Yeni Yazar Ekle
              </button>
            </div>
            <select name="default_author_id" id="default_author_id" class="form-control author-select-field">
              <option value="0">✦ Belgeden Otomatik Tespit Et / Eşleştir</option>
              <?php foreach ( $authors as $a ) : ?>
                <option value="<?= $a['id']; ?>"><?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?></option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:0.72rem; color:var(--adm-muted); margin-top:0.25rem;">Belgede yazar belirtilmemişse geçerli olur.</div>
          </div>

          <div class="form-group">
            <label class="form-label">Varsayılan Kategori</label>
            <select name="default_category" id="default_category" class="form-control">
              <option value="">✦ İçeriğe Göre Otomatik Öner</option>
              <?php foreach ( $categories as $cat ) : ?>
                <option value="<?= htmlspecialchars( $cat['name'], ENT_QUOTES, 'UTF-8' ); ?>">
                  <?= htmlspecialchars( $cat['name'], ENT_QUOTES, 'UTF-8' ); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:0.72rem; color:var(--adm-muted); margin-top:0.25rem;">Tümüne uygulanacak tek bir kategori seçebilirsiniz.</div>
          </div>

        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid var(--adm-border);">
          <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; cursor:pointer; color:var(--adm-muted);">
            <input type="checkbox" name="direct_save" value="1" style="width:16px; height:16px;" />
            <span>İnceleme masasına girmeden doğrudan sisteme aktar (Hızlı Mod)</span>
          </label>

          <button type="submit" class="btn btn-accent" style="padding:0.75rem 1.75rem; font-weight:700;">
            Dosyaları Çözümle ve Düzenleme Masasına Getir ➔
          </button>
        </div>

      </form>

    </div>
  </div>

  <!-- Sağ Taraf: Edebi İpuçları & Rehber -->
  <div class="card" style="background:#fafbfc;">
    <div class="card-header">
      <h3 class="card-title">💡 Akıllı Word Aktarımı Nasıl Çalışır?</h3>
    </div>
    <div class="card-body" style="font-size:0.85rem; line-height:1.65; color:var(--adm-subtle);">
      
      <div style="margin-bottom:1rem;">
        <strong style="color:var(--adm-text);">✦ Eksiksiz İçerik Aktarımı:</strong>
        <div>Paragraflar, başlıklar, kalın (bold), italik, altı çizili metinler ve Word içine gömülü tüm fotoğraflar sıfır kayıpla aktarılır.</div>
      </div>

      <div style="margin-bottom:1rem;">
        <strong style="color:var(--adm-text);">✦ Otomatik Etiket Önerisi:</strong>
        <div>Metin içi edebi frekans analiziyle yazının anahtar kavramları tespit edilir ve etiket olarak önerilir.</div>
      </div>

      <div style="margin-bottom:1rem;">
        <strong style="color:var(--adm-text);">✦ Edebi Tür Tespiti:</strong>
        <div>Metnin ritmine, mısra yapısına veya içeriğine göre Şiir, Deneme, İnceleme veya Hikaye kategorisi otomatik önerilir.</div>
      </div>

      <div>
        <strong style="color:var(--adm-text);">✦ Özel Belge Etiketleri (İsteğe Bağlı):</strong>
        <div>Dilerseniz Word dosyasının en başına şu ifadeleri yazarak sistemi yönlendirebilirsiniz:</div>
        <div style="font-family:var(--adm-font-mono); font-size:0.75rem; background:#f1f5f9; padding:0.4rem 0.6rem; border-radius:4px; margin-top:0.4rem;">
          [Yazar: Cahit Sıtkı Tarancı]<br/>
          [Kategori: Şiir]<br/>
          [Etiketler: ölüm, zaman, yaşama sevinci]
        </div>
      </div>

    </div>
  </div>

</div>

<?php endif; ?>

<!-- ==========================================================
     ÖNİZLEME MODALI (POPUP)
     ========================================================== -->
<div id="previewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.7); z-index:9999; align-items:center; justify-content:center; padding:1.5rem;">
  <div style="background:#ffffff; max-width:850px; width:100%; max-height:85vh; border-radius:var(--adm-radius-lg); display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
    
    <div style="padding:1rem 1.5rem; border-bottom:1px solid var(--adm-border); display:flex; justify-content:space-between; align-items:center; background:#fafbfc;">
      <div>
        <span class="pill-badge pill-purple" style="font-size:0.68rem; margin-bottom:0.2rem;">Eser İçeriği Önizlemesi</span>
        <h3 id="modalTitle" style="margin:0; font-size:1.15rem; font-weight:700; color:var(--adm-text);"></h3>
      </div>
      <button type="button" onclick="closeContentPreview()" style="background:none; border:none; font-size:1.6rem; cursor:pointer; color:var(--adm-muted); padding:0 0.5rem;">&times;</button>
    </div>

    <div id="modalBody" style="padding:2rem; overflow-y:auto; line-height:1.75; font-family:Georgia, serif; font-size:1.05rem; color:#1e293b; background:#fff;"></div>

    <div style="padding:0.75rem 1.5rem; border-top:1px solid var(--adm-border); background:#fafbfc; display:flex; justify-content:space-between; align-items:center;">
      <span style="font-size:0.78rem; color:var(--adm-muted);">✦ Bu içerik Word belgesinden çıkarılan ham biçimlendirilmiş gövdedir.</span>
      <button type="button" onclick="closeContentPreview()" class="btn btn-sm">Kapat</button>
    </div>

  </div>
</div>

<!-- ==========================================================
     HIZLI YAZAR EKLEME MODALI (POPUP)
     ========================================================== -->
<div id="quickAuthorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.7); z-index:99999; align-items:center; justify-content:center; padding:1.5rem;">
  <div style="background:#ffffff; max-width:480px; width:100%; border-radius:var(--adm-radius-lg); box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; border:1px solid var(--adm-border);">
    
    <div style="padding:1rem 1.25rem; border-bottom:1px solid var(--adm-border); display:flex; justify-content:space-between; align-items:center; background:#fafbfc;">
      <div style="font-weight:700; font-size:1.05rem; color:var(--adm-text); display:flex; align-items:center; gap:0.5rem;">
        <span>👤</span> Yeni Yazar Ekle & Seç
      </div>
      <button type="button" onclick="closeBulkQuickAuthorModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--adm-muted); padding:0 0.35rem; line-height:1;">&times;</button>
    </div>

    <div style="padding:1.25rem;">
      <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label" style="font-weight:600; margin-bottom:0.35rem;">Yazar Adı Soyadı <span style="color:#ef4444;">*</span></label>
        <input type="text" id="modalAuthorName" class="form-control" placeholder="Örn: Emine Öykü Güner" />
      </div>

      <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label" style="font-weight:600; margin-bottom:0.35rem;">Edebi Alan / Ünvan (İsteğe Bağlı)</label>
        <input type="text" id="modalAuthorTitle" class="form-control" placeholder="Örn: Deneme & Eleştiri" />
      </div>

      <div id="modalAuthorMsg" style="font-size:0.8rem; margin-bottom:0.75rem; min-height:1.2rem;"></div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeBulkQuickAuthorModal()" class="btn btn-sm">İptal</button>
        <button type="button" onclick="saveBulkQuickAuthor()" id="modalAuthorSaveBtn" class="btn btn-primary btn-sm" style="padding:0.45rem 1.25rem;">
          ✓ Yazarı Kaydet ve Seç
        </button>
      </div>

      <div style="font-size:0.72rem; color:var(--adm-muted); margin-top:1rem; border-top:1px dashed #e2e8f0; padding-top:0.6rem; line-height:1.4;">
        ✦ Eklediğiniz yazar sisteme anında kaydedilir ve sayfadaki tüm yazar kutularına eklenir. Detaylarını (fotoğraf, biyografi) dilediğiniz zaman <strong>Yazarlar</strong> sayfasından düzenleyebilirsiniz.
      </div>
    </div>

  </div>
</div>

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
          <button type="button" onclick="saveBulkGeneratedCover()" id="saveCoverBtn" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.65rem; font-weight:700; font-size:0.9rem;">
            ✓ Görseli Kaydet ve Bu Eserin Kapağı Yap
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
          <div style="display:flex; flex-wrap:wrap; gap:0.75rem; background:var(--adm-bg, #f4f4f5); padding:0.6rem 0.8rem; border-radius:6px; font-size:0.78rem;">
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
          ✦ <strong>Otomatik Optimizasyon:</strong> Kapak görseli 16:9 oranında WebP formatına dönüştürülüp <code>uploads/covers/</code> dizinine kaydedilir ve karta otomatik atanır.
        </div>

      </div>

    </div>

  </div>
</div>

<script>
  let activeAuthorTargetIdx = null;

  // Select Option A-Z Sıralama Yardımcısı (Türkçe Alfabe Uyumlu)
  function sortSelectOptions(selectEl, selectedId) {
    if (!selectEl) return;
    const options = Array.from(selectEl.options);
    const firstOpt = (options[0] && (options[0].value === '0' || options[0].value === '')) ? options.shift() : null;

    options.sort((a, b) => a.text.localeCompare(b.text, 'tr'));

    selectEl.innerHTML = '';
    if (firstOpt) selectEl.appendChild(firstOpt);
    options.forEach(o => {
      if (selectedId && String(o.value) === String(selectedId)) {
        o.selected = true;
      }
      selectEl.appendChild(o);
    });
  }

  function openBulkQuickAuthorModal(idx = null) {
    activeAuthorTargetIdx = idx;
    document.getElementById('modalAuthorName').value = '';

    // Seçili kategoriye göre otomatik edebi unvan belirleme (Şiir -> Şair, Diğerleri -> Yazar)
    let currentCat = '';
    if (idx !== null) {
      const catSelect = document.getElementById('item_category_' + idx) || document.querySelector('select[name="items[' + idx + '][category]"]');
      if (catSelect) currentCat = catSelect.value.trim().toLowerCase();
    } else {
      const defCatSelect = document.getElementById('default_category');
      if (defCatSelect) currentCat = defCatSelect.value.trim().toLowerCase();
    }

    const titleInput = document.getElementById('modalAuthorTitle');
    if (titleInput) {
      if (currentCat.includes('şiir') || currentCat === 'siir') {
        titleInput.value = 'Şair';
      } else {
        titleInput.value = 'Yazar';
      }
    }

    document.getElementById('modalAuthorMsg').innerText = '';
    document.getElementById('quickAuthorModal').style.display = 'flex';
    setTimeout(() => {
      document.getElementById('modalAuthorName').focus();
    }, 100);
  }

  function closeBulkQuickAuthorModal() {
    document.getElementById('quickAuthorModal').style.display = 'none';
  }

  function saveBulkQuickAuthor() {
    const nameInput = document.getElementById('modalAuthorName');
    const titleInput = document.getElementById('modalAuthorTitle');
    const msg = document.getElementById('modalAuthorMsg');
    const btn = document.getElementById('modalAuthorSaveBtn');

    const name = nameInput.value.trim();

    let currentCat = '';
    if (activeAuthorTargetIdx !== null) {
      const catSelect = document.getElementById('item_category_' + activeAuthorTargetIdx) || document.querySelector('select[name="items[' + activeAuthorTargetIdx + '][category]"]');
      if (catSelect) currentCat = catSelect.value.trim().toLowerCase();
    } else {
      const defCatSelect = document.getElementById('default_category');
      if (defCatSelect) currentCat = defCatSelect.value.trim().toLowerCase();
    }
    const defaultRole = (currentCat.includes('şiir') || currentCat === 'siir') ? 'Şair' : 'Yazar';
    const title = titleInput.value.trim() || defaultRole;

    if (!name) {
      msg.style.color = '#ef4444';
      msg.innerText = 'Lütfen yazar adı ve soyadını giriniz.';
      nameInput.focus();
      return;
    }

    btn.disabled = true;
    btn.innerText = 'Kaydediliyor...';
    msg.innerText = '';

    const formData = new FormData();
    formData.append('csrf_token', '<?= Auth::csrf_token(); ?>');
    formData.append('name', name);
    formData.append('title', title);

    fetch('<?= SITE_URL; ?>/admin/authors.php?action=ajax_create', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      btn.disabled = false;
      btn.innerText = '✓ Yazarı Kaydet ve Seç';
      if (data.success) {
        // Tüm yazar select kutularına yeni yazarı ekle ve A'dan Z'ye harf sırasına göre sırala
        const allAuthorSelects = document.querySelectorAll('.author-select-field, select[name="default_author_id"]');
        allAuthorSelects.forEach(select => {
          const opt = document.createElement('option');
          opt.value = data.id;
          opt.textContent = data.name + (data.title ? ' (' + data.title + ')' : '');
          select.appendChild(opt);

          const shouldSelect = (activeAuthorTargetIdx !== null && select.id === 'item_author_' + activeAuthorTargetIdx) || (activeAuthorTargetIdx === null && select.id === 'default_author_id');
          sortSelectOptions(select, shouldSelect ? data.id : select.value);
        });

        msg.style.color = '#10b981';
        msg.innerText = '✓ Yazar başarıyla oluşturuldu ve seçildi!';
        setTimeout(() => {
          closeBulkQuickAuthorModal();
        }, 500);
      } else {
        msg.style.color = '#ef4444';
        msg.innerText = data.message || 'Hata oluştu.';
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerText = '✓ Yazarı Kaydet ve Seç';
      msg.style.color = '#ef4444';
      msg.innerText = 'Bağlantı hatası: ' + err;
    });
  }

  // Varsayılan kategori değiştiğinde yazar unvanını senkronize et
  const defCatEl = document.getElementById('default_category');
  if (defCatEl) {
    defCatEl.addEventListener('change', function() {
      const titleInput = document.getElementById('modalAuthorTitle');
      if (titleInput && (!titleInput.value || titleInput.value === 'Yazar' || titleInput.value === 'Şair')) {
        titleInput.value = this.value.trim().toLowerCase().includes('şiir') ? 'Şair' : 'Yazar';
      }
    });
  }

  // ==========================================================
  // TOPLU YAZI İÇİN KAPAK GÖRSELİ ÜRETİCİSİ (CANVAS MOTORU)
  // ==========================================================
  let activeCoverTargetIdx = null;
  let currentCoverTheme = 'paper';
  let currentCoverFont = 'serif';

  function openBulkCoverModal(idx) {
    activeCoverTargetIdx = idx;
    const titleInput = document.getElementById('item_title_' + idx);
    const authorSelect = document.getElementById('item_author_' + idx);
    const catSelect = document.getElementById('item_category_' + idx);

    let postTitle = titleInput ? titleInput.value.trim() : 'Eser Başlığı';
    let authorName = 'Pratfall Yazarı';
    if (authorSelect && authorSelect.selectedIndex >= 0) {
      const selText = authorSelect.options[authorSelect.selectedIndex].text.trim();
      if (selText && !selText.startsWith('✦')) {
        authorName = selText.replace(/\s*\([^)]*\)$/, '');
      }
    }
    let categoryName = catSelect ? catSelect.value.trim() : 'Deneme';

    document.getElementById('coverGenTitle').value = postTitle;
    document.getElementById('coverGenAuthor').value = authorName;
    document.getElementById('coverGenCategory').value = categoryName;
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
    for (let i = 0; i <= teeth; i++) {
      const ty = (i / teeth) * height;
      const tx = width + (i % 2 === 0 ? 5 : -4);
      ctx.lineTo(tx, ty);
    }
    ctx.lineTo(0, height);
    for (let i = teeth; i >= 0; i--) {
      const ty = (i / teeth) * height;
      const tx = 0 - (i % 2 === 0 ? 5 : -4);
      ctx.lineTo(tx, ty);
    }
    ctx.closePath();
    ctx.fill();

    ctx.strokeStyle = 'rgba(0, 0, 0, 0.12)';
    ctx.lineWidth = 1;
    ctx.stroke();

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

    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.arc(0, 0, radius, 0, Math.PI * 2);
    ctx.stroke();

    ctx.lineWidth = 2;
    ctx.setLineDash([8, 6]);
    ctx.beginPath();
    ctx.arc(0, 0, radius - 10, 0, Math.PI * 2);
    ctx.stroke();
    ctx.setLineDash([]);

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
    ctx.lineTo(x + size, y);
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
    if (btnSerif && btnMono) {
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
    }
    drawCoverImage();
  }

  function drawCoverImage(customTitle, customAuthor, customCat) {
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
      tapeColor = 'rgba(254, 240, 138, 0.9)';
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

    // 1. Zemin
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

    if (currentCoverTheme === 'paper' || currentCoverTheme === 'fanzine' || currentCoverTheme === 'kraft') {
      ctx.save();
      ctx.fillStyle = currentCoverTheme === 'fanzine' ? '#000000' : 'rgba(0, 0, 0, 0.22)';
      ctx.fillRect(cardX + 10, cardY + 10, cardW, cardH);
      ctx.restore();
    }

    ctx.fillStyle = cardBg;
    ctx.fillRect(cardX, cardY, cardW, cardH);

    ctx.strokeStyle = frameColor;
    ctx.lineWidth = currentCoverTheme === 'fanzine' ? 5 : 3.5;
    ctx.strokeRect(cardX, cardY, cardW, cardH);

    if (currentCoverTheme === 'fanzine') {
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
      ctx.strokeStyle = accentColor;
      ctx.lineWidth = 1.5;
      ctx.strokeRect(cardX + 10, cardY + 10, cardW - 20, cardH - 20);
    } else if (currentCoverTheme === 'dark' || currentCoverTheme === 'blueprint') {
      const crossOffset = 20;
      drawCornerCross(ctx, cardX + crossOffset, cardY + crossOffset, 10, accentColor);
      drawCornerCross(ctx, cardX + cardW - crossOffset, cardY + crossOffset, 10, accentColor);
      drawCornerCross(ctx, cardX + crossOffset, cardY + cardH - crossOffset, 10, accentColor);
      drawCornerCross(ctx, cardX + cardW - crossOffset, cardY + cardH - crossOffset, 10, accentColor);
    }

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
    const badgeTitle = (document.getElementById('coverGenBadge') ? document.getElementById('coverGenBadge').value : '').trim() || 'PRATFALL · EDEBİYAT & FİKİR & KÜLTÜR & SANAT PLATFORMU';
    const category = (customCat || (document.getElementById('coverGenCategory') ? document.getElementById('coverGenCategory').value.trim() : 'Edebiyat')) || 'Edebiyat';

    ctx.font = 'bold 15px "Courier New", monospace';
    ctx.fillStyle = mutedColor;
    ctx.textAlign = 'center';
    const headY = cardY + 42;
    ctx.fillText(badgeTitle.toUpperCase(), w / 2, headY);

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

    // 4. Alt Bilgi Çubuğu
    const author = (customAuthor || (document.getElementById('coverGenAuthor') ? document.getElementById('coverGenAuthor').value.trim() : 'Pratfall Yazarı')) || 'Pratfall Yazarı';
    const footDivY = cardY + cardH - 74;

    ctx.strokeStyle = frameColor;
    ctx.lineWidth = 1.5;
    ctx.setLineDash([8, 6]);
    ctx.beginPath();
    ctx.moveTo(cardX + 25, footDivY);
    ctx.lineTo(cardX + cardW - 25, footDivY);
    ctx.stroke();
    ctx.setLineDash([]);

    ctx.textAlign = 'center';
    ctx.font = 'bold 24px "Courier New", monospace';
    ctx.fillStyle = accentColor;
    ctx.fillText('[ YAZAR: ' + author.toUpperCase() + ' ]', w / 2, footDivY + 34);

    ctx.font = '15px "Courier New", monospace';
    ctx.fillStyle = mutedColor;
    ctx.fillText('WWW.PRATFALL.COM.TR · KUSURLU KALMA ÇABASI', w / 2, footDivY + 58);

    // 5. Orta Başlık Alanı
    const rawTitle = (customTitle || (document.getElementById('coverGenTitle') ? document.getElementById('coverGenTitle').value.trim() : 'Kusurlu Kalma Çabası')) || 'Kusurlu Kalma Çabası';
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

    ctx.textAlign = 'center';
    ctx.font = `bold ${titleSize}px ${fontFam}`;
    ctx.fillStyle = textColor;
    for (let i = 0; i < titleLines.length; i++) {
      ctx.fillText(titleLines[i], w / 2, titleStartY + (i * lineHeight));
    }

    const ornamentY = titleStartY + (titleLines.length * lineHeight) + 12;
    if (ornamentY < footDivY - 14) {
      ctx.font = 'bold 20px Georgia, serif';
      ctx.fillStyle = accentColor;
      ctx.fillText('◆  ·  —  ·  ◆', w / 2, ornamentY);
    }

    // 6. Kusurlu Katmanlar
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

  function saveBulkGeneratedCover() {
    if (activeCoverTargetIdx === null) return;
    const canvas = document.getElementById('coverGenCanvas');
    const btn = document.getElementById('saveCoverBtn');
    const status = document.getElementById('coverSaveStatus');
    if (!canvas || !btn) return;

    btn.disabled = true;
    btn.innerText = 'Kapak Optimize Edilip Kaydediliyor...';
    status.innerHTML = '<span style="color:var(--adm-primary);">WebP formatında sunucuya yükleniyor...</span>';

    const dataUrl = canvas.toDataURL('image/png');
    const formData = new FormData();
    formData.append('csrf_token', '<?= Auth::csrf_token(); ?>');
    formData.append('image_data', dataUrl);

    fetch('<?= SITE_URL; ?>/admin/import-docx.php?action=ajax_save_cover', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      btn.disabled = false;
      btn.innerText = '✓ Görseli Kaydet ve Bu Eserin Kapağı Yap';
      if (data.success && data.url) {
        applyCoverToItemCard(activeCoverTargetIdx, data.url);
        status.innerHTML = '<span style="color:#10b981; font-weight:700;">✓ Kapak görseli başarıyla oluşturuldu ve karta atandı!</span>';
        setTimeout(() => {
          closeCoverGeneratorModal();
        }, 700);
      } else {
        status.innerHTML = '<span style="color:#ef4444;">Hata: ' + (data.message || 'Kaydedilemedi.') + '</span>';
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerText = '✓ Görseli Kaydet ve Bu Eserin Kapağı Yap';
      status.innerHTML = '<span style="color:#ef4444;">Bağlantı hatası: ' + err + '</span>';
    });
  }

  function applyCoverToItemCard(idx, url) {
    const hiddenInput = document.getElementById('item_featured_image_' + idx);
    const imgEl = document.getElementById('cover_preview_img_' + idx);
    const placeholderEl = document.getElementById('cover_preview_placeholder_' + idx);

    if (hiddenInput) hiddenInput.value = url;
    if (imgEl) {
      imgEl.src = url;
      imgEl.style.display = 'block';
    }
    if (placeholderEl) {
      placeholderEl.style.display = 'none';
    }
  }

  function downloadCoverImage() {
    const canvas = document.getElementById('coverGenCanvas');
    const title = (document.getElementById('coverGenTitle').value || 'pratfall-kapak').toLowerCase().replace(/[^a-z0-9]/g, '-');
    const link = document.createElement('a');
    link.download = `kapak-${title}-${Date.now()}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
  }

  // TÜM SEÇİLİ ESERLER İÇİN TOPLU OTOMATİK KAPAK ÜRETİCİ
  async function bulkAutoGenerateAllCovers() {
    const btn = document.getElementById('btnBulkGenCovers');
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length === 0) {
      alert('Lütfen en az bir eser seçin.');
      return;
    }

    if (!confirm(checkboxes.length + ' adet eser için otomatik 16:9 edebi kapak görseli üretilecek. Onaylıyor musunuz?')) {
      return;
    }

    const origBtnText = btn.innerHTML;
    btn.disabled = true;

    let successCount = 0;
    const canvas = document.getElementById('coverGenCanvas');

    for (let i = 0; i < checkboxes.length; i++) {
      const cb = checkboxes[i];
      const match = cb.name.match(/selected\[(\d+)\]/);
      if (!match) continue;
      const idx = match[1];

      btn.innerHTML = `⏳ Üretiliyor (${i + 1}/${checkboxes.length})...`;

      const titleInput = document.getElementById('item_title_' + idx);
      const authorSelect = document.getElementById('item_author_' + idx);
      const catSelect = document.getElementById('item_category_' + idx);

      let postTitle = titleInput ? titleInput.value.trim() : 'Eser Başlığı';
      let authorName = 'Pratfall Yazarı';
      if (authorSelect && authorSelect.selectedIndex >= 0) {
        const selText = authorSelect.options[authorSelect.selectedIndex].text.trim();
        if (selText && !selText.startsWith('✦')) {
          authorName = selText.replace(/\s*\([^)]*\)$/, '');
        }
      }
      let categoryName = catSelect ? catSelect.value.trim() : 'Deneme';

      // Canvas'a çiz
      drawCoverImage(postTitle, authorName, categoryName);

      const dataUrl = canvas.toDataURL('image/png');
      const formData = new FormData();
      formData.append('csrf_token', '<?= Auth::csrf_token(); ?>');
      formData.append('image_data', dataUrl);

      try {
        const res = await fetch('<?= SITE_URL; ?>/admin/import-docx.php?action=ajax_save_cover', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success && data.url) {
          applyCoverToItemCard(idx, data.url);
          successCount++;
        }
      } catch (e) {
        console.error('Kapak üretilemedi (idx: ' + idx + '):', e);
      }
    }

    btn.disabled = false;
    btn.innerHTML = '✓ Kapaklar Hazırlandı (' + successCount + ')';
    setTimeout(() => {
      btn.innerHTML = origBtnText;
    }, 3000);
  }
      } else {
        msg.style.color = '#ef4444';
        msg.innerText = data.message || 'Hata oluştu.';
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerText = '✓ Yazarı Kaydet ve Seç';
      msg.style.color = '#ef4444';
      msg.innerText = 'Bağlantı hatası: ' + err;
    });
  }

  // Türkçe Title Case JavaScript Yardımcısı
  function toTurkishTitleCase(str) {
    if (!str) return '';
    const turkishLower = str.replace(/I/g, 'ı').replace(/İ/g, 'i').toLowerCase();
    return turkishLower.replace(/(?<![\p{L}'’])(\p{L})/gu, function(match, char) {
      if (char === 'i') return 'İ';
      if (char === 'ı') return 'I';
      return char.toLocaleUpperCase('tr-TR');
    });
  }

  function showSelectedFiles(input) {
    const listWrap = document.getElementById('selectedFilesList');
    const countSpan = document.getElementById('selectedCount');
    const ul = document.getElementById('fileNamesUl');
    ul.innerHTML = '';

    if (input.files && input.files.length > 0) {
      countSpan.textContent = input.files.length;
      for (let i = 0; i < input.files.length; i++) {
        const li = document.createElement('li');
        li.textContent = '📄 ' + input.files[i].name;
        ul.appendChild(li);
      }
      listWrap.style.display = 'block';
    } else {
      listWrap.style.display = 'none';
    }
  }

  // Sürükle Bırak Efekti
  const dropzone = document.getElementById('dropzone');
  if (dropzone) {
    ['dragenter', 'dragover'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--adm-primary)';
        dropzone.style.background = '#f0fdf4';
      }, false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.style.borderColor = '#cbd5e1';
        dropzone.style.background = '#fafbfc';
      }, false);
    });
    dropzone.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      const files = dt.files;
      const fileInput = document.getElementById('fileInput');
      fileInput.files = files;
      showSelectedFiles(fileInput);
    });
  }

  // Tümünü Seç / Kaldır
  function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const anyUnchecked = Array.from(checkboxes).some(cb => !cb.checked);
    checkboxes.forEach(cb => cb.checked = anyUnchecked);
  }

  // Modal Önizleme
  function openContentPreview(index) {
    const content = document.getElementById('hidden_content_' + index).value;
    const title = document.getElementById('item_title_' + index).value;
    document.getElementById('modalTitle').textContent = title || 'Eser İçeriği';
    document.getElementById('modalBody').innerHTML = content || '<p style="color:#ef4444; font-family:sans-serif;">İçerik boş veya okunamadı.</p>';
    document.getElementById('previewModal').style.display = 'flex';
  }

  function closeContentPreview() {
    document.getElementById('previewModal').style.display = 'none';
  }

  // Escape tuşu ile modal kapat
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeContentPreview();
  });
</script>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
