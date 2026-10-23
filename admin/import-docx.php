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
$authors = $db->query( "SELECT id, name FROM authors ORDER BY name ASC" )->fetchAll();
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

        <div style="display:flex; align-items:center; gap:0.75rem;">
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
                <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">Eser Başlığı</label>
                <input type="text" name="items[<?= $idx; ?>][title]" id="item_title_<?= $idx; ?>" class="form-control" value="<?= htmlspecialchars( $item['title'], ENT_QUOTES, 'UTF-8' ); ?>" required style="font-weight:600;" />
              </div>

              <!-- Kategori -->
              <div>
                <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">Edebi Tür / Kategori</label>
                <select name="items[<?= $idx; ?>][category]" class="form-control">
                  <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?= htmlspecialchars( $cat['name'], ENT_QUOTES, 'UTF-8' ); ?>" <?= strtolower( $item['category'] ) === strtolower( $cat['name'] ) ? 'selected' : ''; ?>>
                      <?= htmlspecialchars( $cat['name'], ENT_QUOTES, 'UTF-8' ); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Yazar -->
              <div>
                <label class="form-label" style="font-weight:600; margin-bottom:0.3rem;">Yazar</label>
                <select name="items[<?= $idx; ?>][author_id]" class="form-control">
                  <option value="0">✦ Ortak Platform Metni</option>
                  <?php foreach ( $authors as $a ) : ?>
                    <option value="<?= $a['id']; ?>" <?= $item['author_id'] == $a['id'] ? 'selected' : ''; ?>>
                      <?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

            </div>

            <div style="display:grid; grid-template-columns: 1fr 2fr 1fr; gap:1rem;">
              
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

            </div>

            <!-- Gizli / Saklanan Alanlar (İçerik ve Görsel) -->
            <textarea name="items[<?= $idx; ?>][content]" id="hidden_content_<?= $idx; ?>" style="display:none;"><?= htmlspecialchars( $item['content'], ENT_QUOTES, 'UTF-8' ); ?></textarea>
            <input type="hidden" name="items[<?= $idx; ?>][featured_image]" value="<?= htmlspecialchars( $item['featured_image'], ENT_QUOTES, 'UTF-8' ); ?>" />
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
            <label class="form-label">Varsayılan Yazar (Öncelik)</label>
            <select name="default_author_id" class="form-control">
              <option value="0">✦ Belgeden Otomatik Tespit Et / Eşleştir</option>
              <?php foreach ( $authors as $a ) : ?>
                <option value="<?= $a['id']; ?>"><?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?></option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:0.72rem; color:var(--adm-muted); margin-top:0.25rem;">Belgede yazar belirtilmemişse geçerli olur.</div>
          </div>

          <div class="form-group">
            <label class="form-label">Varsayılan Kategori</label>
            <select name="default_category" class="form-control">
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

<script>
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
