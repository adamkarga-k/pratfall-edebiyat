<?php
/**
 * Pratfall Standalone - Word (.docx) Toplu İçe Aktarıcı (Bulk Importer)
 * 
 * Microsoft Word (.docx) dosyalarını hiçbir harici kütüphane gerektirmeden,
 * PHP'nin yerel ZipArchive ve SimpleXML modülleriyle okur.
 * Başlıkları, paragrafları, kalın/italik biçimlendirmeleri ve
 * Word içine gömülü resimleri otomatik ayıklayarak yazıya dönüştürür.
 */

class DocxImporter {

    /**
     * Tek bir .docx dosyasını çözümle ve dizi olarak döndür
     */
    public static function parse( string $file_path, string $original_filename = '' ): array {
        if ( ! file_exists( $file_path ) ) {
            throw new Exception( "Dosya bulunamadı: " . $file_path );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $file_path ) !== true ) {
            throw new Exception( "Geçersiz veya bozuk Word (.docx) dosyası." );
        }

        // 1. Resimleri Ayıkla (word/media/)
        $extracted_images = self::extract_images( $zip );

        // 2. Metadata Oku (docProps/core.xml)
        $meta = self::extract_metadata( $zip );

        // 3. Ana Metni Oku (word/document.xml)
        $doc_xml = $zip->getFromName( 'word/document.xml' );
        $zip->close();

        if ( ! $doc_xml ) {
            throw new Exception( "Word içeriği (word/document.xml) okunamadı." );
        }

        // 4. XML'i HTML'e Dönüştür
        $parsed = self::xml_to_html( $doc_xml, $extracted_images );

        // 5. Başlık Belirleme
        $title = '';
        if ( ! empty( $parsed['detected_title'] ) ) {
            $title = $parsed['detected_title'];
        } elseif ( ! empty( $meta['title'] ) ) {
            $title = $meta['title'];
        } else {
            // Dosya adından başlık türet
            $name = ! empty( $original_filename ) ? $original_filename : basename( $file_path );
            $title = preg_replace( '/\.[^.]+$/', '', $name );
            $title = str_replace( [ '-', '_' ], ' ', $title );
            $title = mb_convert_case( $title, MB_CASE_TITLE, "UTF-8" );
        }

        // 6. Yazar Belirleme
        $author_name = ! empty( $parsed['detected_author'] ) ? $parsed['detected_author'] : ( ! empty( $meta['creator'] ) ? $meta['creator'] : '' );

        // 7. Kategori Belirleme
        $category = ! empty( $parsed['detected_category'] ) ? $parsed['detected_category'] : 'Deneme';

        // 8. Özet ve Okuma Süresi
        $clean_text = strip_tags( $parsed['html'] );
        $words = preg_split( '/\s+/', trim( $clean_text ) );
        $word_count = count( $words );
        $reading_time = max( 1, (int) ceil( $word_count / 180 ) );

        $excerpt = implode( ' ', array_slice( $words, 0, 28 ) );
        if ( $word_count > 28 ) {
            $excerpt .= '...';
        }

        // Öne çıkan görsel (Varsa ilk gömülü resim)
        $featured_image = ! empty( $extracted_images ) ? $extracted_images[0] : '';

        return [
            'title'          => trim( $title ),
            'slug'           => Database::slugify( $title ),
            'author_name'    => trim( $author_name ),
            'category'       => trim( $category ),
            'excerpt'        => $excerpt,
            'content'        => $parsed['html'],
            'featured_image' => $featured_image,
            'reading_time'   => $reading_time,
            'word_count'     => $word_count,
            'images_count'   => count( $extracted_images ),
        ];
    }

    /**
     * Word İçindeki Gömülü Resimleri uploads/docx-images/ Klasörüne Çıkart
     */
    private static function extract_images( ZipArchive $zip ): array {
        $images = [];
        $target_dir = PATH_UPLOADS . '/docx-images';
        if ( ! is_dir( $target_dir ) ) {
            @mkdir( $target_dir, 0755, true );
        }

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $filename = $zip->getNameIndex( $i );
            if ( strpos( $filename, 'word/media/' ) === 0 ) {
                $ext = pathinfo( $filename, PATHINFO_EXTENSION );
                if ( in_array( strtolower( $ext ), [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ] ) ) {
                    $new_name = 'doc_' . uniqid() . '.' . $ext;
                    $save_path = $target_dir . '/' . $new_name;
                    $content = $zip->getFromIndex( $i );
                    if ( $content && file_put_contents( $save_path, $content ) ) {
                        $images[] = SITE_URL . '/uploads/docx-images/' . $new_name;
                    }
                }
            }
        }
        return $images;
    }

    /**
     * Metadata Çıkart (Yazar, Başlık vb.)
     */
    private static function extract_metadata( ZipArchive $zip ): array {
        $meta = [ 'title' => '', 'creator' => '' ];
        $xml_content = $zip->getFromName( 'docProps/core.xml' );
        if ( $xml_content ) {
            $xml = @simplexml_load_string( $xml_content );
            if ( $xml ) {
                $namespaces = $xml->getNamespaces( true );
                $dc = $xml->children( $namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/' );
                if ( isset( $dc->title ) ) {
                    $meta['title'] = (string) $dc->title;
                }
                if ( isset( $dc->creator ) ) {
                    $meta['creator'] = (string) $dc->creator;
                }
            }
        }
        return $meta;
    }

    /**
     * word/document.xml Dosyasını Temiz Semantik HTML'e Dönüştür
     */
    private static function xml_to_html( string $xml_content, array $images = [] ): array {
        $xml = @simplexml_load_string( $xml_content );
        if ( ! $xml ) {
            return [ 'html' => '', 'detected_title' => '', 'detected_author' => '', 'detected_category' => '' ];
        }

        $xml->registerXPathNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

        $paragraphs = $xml->xpath( '//w:p' );
        $html_output = [];
        $detected_title = '';
        $detected_author = '';
        $detected_category = '';
        $is_first = true;
        $img_index = 0;

        foreach ( $paragraphs as $p ) {
            $p_text = '';
            $is_heading = false;
            $heading_level = 2;

            // Paragraf Stili Kontrolü
            $pPr = $p->xpath( './w:pPr/w:pStyle' );
            if ( ! empty( $pPr ) ) {
                $style_val = (string) $pPr[0]->attributes( 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' )['val'];
                if ( stripos( $style_val, 'Heading1' ) !== false || stripos( $style_val, 'Title' ) !== false ) {
                    $is_heading = true;
                    $heading_level = 2;
                } elseif ( stripos( $style_val, 'Heading2' ) !== false ) {
                    $is_heading = true;
                    $heading_level = 3;
                } elseif ( stripos( $style_val, 'Heading3' ) !== false ) {
                    $is_heading = true;
                    $heading_level = 4;
                }
            }

            // Metin Parçaları (Runs - w:r)
            $runs = $p->xpath( './w:r' );
            foreach ( $runs as $r ) {
                $t = $r->xpath( './w:t' );
                if ( empty( $t ) ) continue;

                $run_text = htmlspecialchars( (string) $t[0], ENT_QUOTES, 'UTF-8' );

                // Biçimlendirmeler (Kalın, İtalik)
                $is_bold = ! empty( $r->xpath( './w:rPr/w:b' ) );
                $is_italic = ! empty( $r->xpath( './w:rPr/w:i' ) );
                $is_underline = ! empty( $r->xpath( './w:rPr/w:u' ) );

                if ( $is_bold ) $run_text = '<strong>' . $run_text . '</strong>';
                if ( $is_italic ) $run_text = '<em>' . $run_text . '</em>';
                if ( $is_underline ) $run_text = '<u>' . $run_text . '</u>';

                $p_text .= $run_text;
            }

            $raw_trimmed = trim( strip_tags( $p_text ) );
            if ( empty( $raw_trimmed ) ) {
                continue;
            }

            // Özel Etiket Kontrolleri (Örn: [Yazar: İlhan Berk], [Kategori: Şiir])
            if ( preg_match( '/^\[?\s*Yazar\s*:\s*(.+?)\]?$/iu', $raw_trimmed, $matches ) ) {
                $detected_author = trim( $matches[1] );
                continue;
            }
            if ( preg_match( '/^\[?\s*Kategori\s*:\s*(.+?)\]?$/iu', $raw_trimmed, $matches ) ) {
                $detected_category = trim( $matches[1] );
                continue;
            }
            if ( preg_match( '/^\[?\s*Başlık\s*:\s*(.+?)\]?$/iu', $raw_trimmed, $matches ) ) {
                $detected_title = trim( $matches[1] );
                continue;
            }

            // İlk paragraf ve başlık tespit edilmemişse
            if ( $is_first ) {
                $is_first = false;
                if ( $is_heading || mb_strlen( $raw_trimmed ) < 120 ) {
                    $detected_title = $raw_trimmed;
                    continue; // Başlığı içeriğe tekrar basma
                }
            }

            if ( $is_heading ) {
                $html_output[] = "<h{$heading_level}>{$p_text}</h{$heading_level}>";
            } else {
                $html_output[] = "<p>{$p_text}</p>";
            }
        }

        return [
            'html'              => implode( "\n", $html_output ),
            'detected_title'    => $detected_title,
            'detected_author'   => $detected_author,
            'detected_category' => $detected_category,
        ];
    }

    /**
     * Yüklenen Word Dosyasını Doğrudan Veritabanına Yazı Olarak Ekle
     */
    public static function import_to_database( string $file_path, string $original_filename, int $default_author_id = 0, string $status = 'published' ): int {
        $parsed = self::parse( $file_path, $original_filename );
        $db = Database::get();

        // Yazar Eşleştir veya Otomatik Oluştur
        $author_id = $default_author_id;
        if ( ! empty( $parsed['author_name'] ) ) {
            $stmt = $db->prepare( "SELECT id FROM authors WHERE name = ? OR slug = ?" );
            $author_slug = Database::slugify( $parsed['author_name'] );
            $stmt->execute( [ $parsed['author_name'], $author_slug ] );
            $found = $stmt->fetch();

            if ( $found ) {
                $author_id = (int) $found['id'];
            } else {
                // Yeni yazarı otomatik ekle (Kullanıcı açmaya gerek yok!)
                $ins = $db->prepare( "INSERT INTO authors (name, slug, title) VALUES (?, ?, 'Yazar')" );
                $ins->execute( [ $parsed['author_name'], $author_slug ] );
                $author_id = (int) $db->lastInsertId();
            }
        }

        // Eğer yazar hala 0 ise ilk yazara bağla
        if ( $author_id === 0 ) {
            $first_author = $db->query( "SELECT id FROM authors ORDER BY id ASC LIMIT 1" )->fetch();
            $author_id = $first_author ? (int) $first_author['id'] : null;
        }

        // Benzersiz Slug Kontrolü
        $base_slug = $parsed['slug'];
        $slug = $base_slug;
        $counter = 1;
        while ( true ) {
            $stmt = $db->prepare( "SELECT id FROM posts WHERE slug = ?" );
            $stmt->execute( [ $slug ] );
            if ( ! $stmt->fetch() ) {
                break;
            }
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        // Veritabanına Ekle
        $stmt = $db->prepare( "
            INSERT INTO posts (author_id, title, slug, category, excerpt, content, featured_image, reading_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        " );

        $stmt->execute( [
            $author_id,
            $parsed['title'],
            $slug,
            $parsed['category'],
            $parsed['excerpt'],
            $parsed['content'],
            $parsed['featured_image'],
            $parsed['reading_time'],
            $status,
        ] );

        return (int) $db->lastInsertId();
    }
}
