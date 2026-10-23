<?php
/**
 * Pratfall Standalone - Word (.docx) Toplu İçe Aktarıcı (Bulk Importer)
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

$page_title = 'Word (.docx) Toplu İçe Aktar';
$active_tab = 'import';

$db = Database::get();
$authors = $db->query( "SELECT id, name FROM authors ORDER BY name ASC" )->fetchAll();

$results = [];
$errors = [];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_FILES['docx_files'] ) ) {
    if ( ! Auth::verify_csrf( $_POST['csrf_token'] ?? '' ) ) {
        $errors[] = 'Güvenlik doğrulaması (CSRF) başarısız oldu.';
    } else {
        $default_author_id = (int) ( $_POST['default_author_id'] ?? 0 );
        $default_category  = trim( $_POST['default_category'] ?? 'Deneme' );
        $default_status    = trim( $_POST['default_status'] ?? 'published' );

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
                $errors[] = "{$name}: Sadece .docx uzantılı Microsoft Word dosyaları desteklenir.";
                continue;
            }

            try {
                // Dosyayı İçe Aktar
                $post_id = DocxImporter::import_to_database( $tmp_name, $name, $default_author_id, $default_status );
                
                // İçe aktarılan yazıyı çek
                $imported_post = $db->query( "
                    SELECT p.*, a.name AS author_name 
                    FROM posts p 
                    LEFT JOIN authors a ON p.author_id = a.id 
                    WHERE p.id = {$post_id}
                " )->fetch();

                $results[] = [
                    'file'         => $name,
                    'post_id'      => $post_id,
                    'title'        => $imported_post['title'],
                    'slug'         => $imported_post['slug'],
                    'author'       => $imported_post['author_name'] ?? 'Bilinmiyor',
                    'category'     => $imported_post['category'],
                    'reading_time' => $imported_post['reading_time'],
                ];
            } catch ( Exception $e ) {
                $errors[] = "{$name}: " . $e->getMessage();
            }
        }
    }
}

require PATH_ADMIN . '/layout/header.php';
?>

<div class="admin-header-row">
  <div>
    <h1 class="admin-title">📥 Word (.docx) Toplu İçe Aktarıcı</h1>
    <p style="margin:0.25rem 0 0 0; color:var(--admin-muted); font-size:0.9rem;">
      Bilgisayarınızdaki Word (.docx) dosyalarını tek seferde sisteme yükleyin; başlıklar, yazarlar, biçimlendirmeler ve resimler otomatik olarak eserlere dönüştürülsün.
    </p>
  </div>
</div>

<?php if ( ! empty( $errors ) ) : ?>
  <div class="alert alert-error">
    <strong>Bazı dosyalar yüklenirken hata oluştu:</strong>
    <ul style="margin:0.5rem 0 0 1rem; padding:0;">
      <?php foreach ( $errors as $err ) : ?>
        <li><?= htmlspecialchars( $err, ENT_QUOTES, 'UTF-8' ); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ( ! empty( $results ) ) : ?>
  <div class="alert alert-success">
    <strong>✦ Tebrikler! <?= count( $results ); ?> adet Word belgesi başarıyla içe aktarıldı.</strong>
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0; font-family:var(--admin-serif);">İçe Aktarılan Eserler:</h3>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Dosya Adı</th>
          <th>Tespit Edilen Başlık</th>
          <th>Yazar</th>
          <th>Kategori</th>
          <th>Okuma Süresi</th>
          <th style="text-align:right;">İşlem</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $results as $r ) : ?>
          <tr>
            <td style="font-family:var(--admin-mono); font-size:0.8rem;"><?= htmlspecialchars( $r['file'], ENT_QUOTES, 'UTF-8' ); ?></td>
            <td><strong><?= htmlspecialchars( $r['title'], ENT_QUOTES, 'UTF-8' ); ?></strong></td>
            <td><?= htmlspecialchars( $r['author'], ENT_QUOTES, 'UTF-8' ); ?></td>
            <td><span class="stamp-tag" style="font-size:0.7rem;"><?= htmlspecialchars( $r['category'], ENT_QUOTES, 'UTF-8' ); ?></span></td>
            <td><?= $r['reading_time']; ?> dk</td>
            <td style="text-align:right;">
              <a href="<?= SITE_URL; ?>/eser/<?= $r['slug']; ?>" target="_blank" class="btn" style="padding:0.2rem 0.5rem; font-size:0.75rem;">Canlıda Gör ↗</a>
              <a href="<?= SITE_URL; ?>/admin/posts?action=edit&id=<?= $r['post_id']; ?>" class="btn" style="padding:0.2rem 0.5rem; font-size:0.75rem;">Düzenle</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem; align-items:start;">
  
  <!-- Yükleme Formu -->
  <div class="admin-card">
    <form method="POST" action="<?= SITE_URL; ?>/admin/import-docx" enctype="multipart/form-data" id="docxUploadForm">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrf_token(); ?>" />

      <!-- Sürükle Bırak Kutusu -->
      <div class="dropzone-box" onclick="document.getElementById('fileInput').click()" id="dropzone">
        <div style="font-size:2.5rem; margin-bottom:0.5rem;">📄 ➔ 📚</div>
        <div style="font-family:var(--admin-serif); font-size:1.3rem; font-weight:700; margin-bottom:0.25rem;">
          Word (.docx) Dosyalarını Buraya Sürükleyin
        </div>
        <p style="margin:0; font-size:0.85rem; color:var(--admin-muted);">
          veya bilgisayarınızdan birden fazla dosya seçmek için tıklayın
        </p>
        <div style="margin-top:1rem;">
          <span class="btn btn-primary" style="pointer-events:none;">Dosyaları Seç (Çoklu Seçim Desteklenir)</span>
        </div>
        <input type="file" id="fileInput" name="docx_files[]" multiple accept=".docx" style="display:none;" onchange="showSelectedFiles(this)" />
      </div>

      <!-- Seçilen Dosyalar Listesi -->
      <div id="selectedFilesList" style="margin-bottom:1.5rem; display:none;">
        <h4 style="margin:0 0 0.5rem 0; font-family:var(--admin-mono); font-size:0.85rem;">Seçilen Dosyalar (<span id="selectedCount">0</span>):</h4>
        <ul id="fileNamesUl" style="font-family:var(--admin-mono); font-size:0.8rem; background:#f5f5f4; padding:0.75rem 1.5rem; border:1px solid #e7e5e4; max-height:150px; overflow-y:auto; margin:0;"></ul>
      </div>

      <!-- İçe Aktarma Ayarları -->
      <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label class="form-label" for="default_author_id">Varsayılan Yazar</label>
          <select name="default_author_id" id="default_author_id" class="form-control">
            <option value="0">✦ Word Belgesinden Otomatik Tespit Et</option>
            <?php foreach ( $authors as $a ) : ?>
              <option value="<?= $a['id']; ?>"><?= htmlspecialchars( $a['name'], ENT_QUOTES, 'UTF-8' ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="default_category">Varsayılan Kategori</label>
          <select name="default_category" id="default_category" class="form-control">
            <option value="Deneme">Deneme</option>
            <option value="Şiir">Şiir</option>
            <option value="İnceleme">İnceleme</option>
            <option value="Anlatı">Anlatı</option>
            <option value="Eleştiri">Eleştiri</option>
            <option value="Felsefe">Felsefe</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="default_status">Yayın Durumu</label>
          <select name="default_status" id="default_status" class="form-control">
            <option value="published">Doğrudan Yayına Al (Published)</option>
            <option value="draft">Taslak Olarak Kaydet (Draft)</option>
          </select>
        </div>
      </div>

      <div style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-success" style="padding:0.75rem 1.5rem; font-size:0.95rem;">
          Seçilen Word Belgelerini Sisteme Aktar ↗
        </button>
      </div>
    </form>
  </div>

  <!-- Rehber & İpuçları -->
  <div class="admin-card" style="background:#fafaf9; font-size:0.85rem; line-height:1.6;">
    <h3 style="margin-top:0; font-family:var(--admin-serif); font-size:1.15rem; border-bottom:1.5px solid var(--admin-border); padding-bottom:0.5rem;">
      💡 Word İçe Aktarıcı Nasıl Çalışır?
    </h3>
    <ul style="padding-left:1.2rem; margin-top:0.75rem; color:var(--admin-text);">
      <li><strong>Başlık Belirleme:</strong> Word belgesinin ilk satırını veya "Başlık 1" stilini otomatik başlık yapar.</li>
      <li><strong>Yazar Etiketi:</strong> Belge içerisine <code>[Yazar: İlhan Berk]</code> yazarsanız yazar otomatik atanır. Yazmazsanız sol panelden seçtiğiniz yazar geçerli olur.</li>
      <li><strong>Kategori Etiketi:</strong> Belge içine <code>[Kategori: Şiir]</code> yazarak kategoriyi otomatik belirleyebilirsiniz.</li>
      <li><strong>Biçimlendirmeler:</strong> Word içindeki <strong>kalın</strong>, <em>italik</em> ve altı çizili metinler aynen korunur.</li>
      <li><strong>Gömülü Resimler:</strong> Word içine yapıştırdığınız resimler otomatik ayıklanıp sitenizin <code>uploads/</code> klasörüne kaydedilir ve esere bağlanır.</li>
    </ul>
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
  ['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault();
      dropzone.classList.add('dragover');
    }, false);
  });
  ['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault();
      dropzone.classList.remove('dragover');
    }, false);
  });
  dropzone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    document.getElementById('fileInput').files = files;
    showSelectedFiles(document.getElementById('fileInput'));
  });
</script>

<?php require PATH_ADMIN . '/layout/footer.php'; ?>
