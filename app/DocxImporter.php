<?php
/**
 * Pratfall Standalone - Word (.docx) Toplu İçe Aktarıcı (Bulk Importer)
 * 
 * Microsoft Word (.docx) dosyalarını hiçbir harici kütüphane gerektirmeden,
 * PHP'nin yerel ZipArchive, DOMDocument ve SimpleXML modülleriyle okur.
 * Başlıkları, paragrafları, kalın/italik biçimlendirmeleri, gömülü resimleri,
 * önerilen etiketleri ve kısa özetleri eksiksiz ayıklayarak yazıya dönüştürür.
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

        // 4. XML'i Temiz Semantik HTML'e Dönüştür
        $parsed = self::xml_to_html( $doc_xml, $extracted_images );

        // 4.1. Dosya Adından Akıllı Ayrıştırma (Örn: "Deneme, Emine Öykü Güner, Varlık Çatışması.docx")
        $raw_filename = ! empty( $original_filename ) ? $original_filename : basename( $file_path );
        $clean_fn = preg_replace( '/\.[^.]+$/', '', $raw_filename );
        $fn_category = '';
        $fn_author = '';
        $fn_title = '';

        if ( strpos( $clean_fn, ',' ) !== false ) {
            $fn_parts = array_map( 'trim', explode( ',', $clean_fn ) );
            if ( count( $fn_parts ) >= 3 ) {
                $fn_category = $fn_parts[0];
                $fn_author   = $fn_parts[1];
                $fn_title    = implode( ', ', array_slice( $fn_parts, 2 ) );
            } elseif ( count( $fn_parts ) === 2 ) {
                $test_cat = self::suggest_category( $fn_parts[0], $fn_parts[0] );
                if ( mb_strtolower( $fn_parts[0], 'UTF-8' ) === mb_strtolower( $test_cat, 'UTF-8' ) ) {
                    $fn_category = $fn_parts[0];
                    $fn_title = $fn_parts[1];
                } else {
                    $fn_author = $fn_parts[0];
                    $fn_title = $fn_parts[1];
                }
            }
        } elseif ( strpos( $clean_fn, '-' ) !== false ) {
            $fn_parts = array_map( 'trim', explode( '-', $clean_fn ) );
            if ( count( $fn_parts ) >= 3 ) {
                $fn_category = $fn_parts[0];
                $fn_author   = $fn_parts[1];
                $fn_title    = implode( ' - ', array_slice( $fn_parts, 2 ) );
            } elseif ( count( $fn_parts ) === 2 ) {
                $fn_author = $fn_parts[0];
                $fn_title = $fn_parts[1];
            }
        }

        // 5. Başlık Belirleme (Kelimelerin İlk Harfleri Büyük, Kalanlar Küçük)
        $title = '';
        if ( ! empty( $parsed['detected_title'] ) ) {
            $title = $parsed['detected_title'];
        } elseif ( ! empty( $fn_title ) ) {
            $title = $fn_title;
        } elseif ( ! empty( $meta['title'] ) ) {
            $title = $meta['title'];
        } else {
            // Dosya adından temiz başlık türet
            $name = ! empty( $original_filename ) ? $original_filename : basename( $file_path );
            $title = preg_replace( '/\.[^.]+$/', '', $name );
            $title = preg_replace( '/^\d+[\s\-_.]*/', '', $title ); // Başındaki '01 - ' gibi numaraları temizle
            $title = str_replace( [ '-', '_' ], ' ', $title );
        }

        // Başlığı otomatik Türkçe Title Case (İlk harfler büyük, diğerleri küçük) yap
        $title = Helpers::turkish_title_case( $title );

        // 6. Yazar Belirleme
        $author_name = '';
        if ( ! empty( $parsed['detected_author'] ) ) {
            $author_name = $parsed['detected_author'];
        } elseif ( ! empty( $fn_author ) && mb_strlen( $fn_author ) > 2 && mb_strlen( $fn_author ) < 50 ) {
            $author_name = $fn_author;
        } elseif ( ! empty( $meta['creator'] ) ) {
            $author_name = $meta['creator'];
        }

        if ( ! empty( $author_name ) ) {
            $author_name = Helpers::turkish_title_case( $author_name );
        }

        // 7. Kategori Belirleme (Öneri)
        if ( ! empty( $parsed['detected_category'] ) ) {
            $category = $parsed['detected_category'];
        } elseif ( ! empty( $fn_category ) ) {
            $category = self::suggest_category( $fn_category, $fn_category );
        } else {
            $category = self::suggest_category( $parsed['raw_text'], $title );
        }

        // Eğer Şiir ise mısraları semantik kıta formatına dönüştür
        $doc_content = $parsed['html'];
        if ( mb_stripos( $category, 'şiir' ) !== false || mb_stripos( $category, 'siir' ) !== false ) {
            $doc_content = Helpers::format_poem_content( $doc_content );
        }

        // 8. Önerilen Etiketler (Tags)
        $tags = ! empty( $parsed['detected_tags'] )
            ? $parsed['detected_tags']
            : self::suggest_tags( $parsed['raw_text'] );

        // 9. Özet ve Okuma Süresi
        $clean_text = $parsed['raw_text'];
        $words = preg_split( '/\s+/u', trim( $clean_text ) );
        $word_count = count( array_filter( $words ) );
        $reading_time = max( 1, (int) ceil( $word_count / 180 ) );

        // Önerilen Kısa Açıklama (İlk 28 kelime)
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
            'tags'           => trim( $tags ),
            'excerpt'        => trim( $excerpt ),
            'content'        => $doc_content,
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
                $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
                if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ] ) ) {
                    $new_name = 'doc_' . uniqid() . '.' . $ext;
                    $save_path = $target_dir . '/' . $new_name;
                    $content = $zip->getFromIndex( $i );
                    if ( $content && file_put_contents( $save_path, $content ) ) {
                        @chmod( $save_path, 0644 );
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
     * word/document.xml Dosyasını Eksiksiz ve Kusursuz Semantik HTML'e Dönüştür
     */
    private static function xml_to_html( string $xml_content, array $images = [] ): array {
        // Kontrol karakterlerini temizle (XML bozulmalarını önler)
        $clean_xml = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xml_content );

        $html_output = [];
        $raw_text_parts = [];
        $detected_title = '';
        $detected_author = '';
        $detected_category = '';
        $detected_tags = '';

        // 1. Birincil Yöntem: DOMDocument + DOMXPath ile kusursuz hiyerarşik çözümleme
        $dom = new DOMDocument();
        $loaded = @$dom->loadXML( $clean_xml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE );

        if ( $loaded ) {
            $xpath = new DOMXPath( $dom );
            // Tüm paragrafları namespace bağımsız yakala
            $p_nodes = $xpath->query( "//*[local-name()='p']" );

            $paragraph_texts = [];

            if ( $p_nodes && $p_nodes->length > 0 ) {
                foreach ( $p_nodes as $p ) {
                    $p_html = '';
                    $p_plain = '';
                    $is_heading = false;
                    $heading_level = 2;

                    // Stil Kontrolü (Heading / Başlık)
                    $style_nodes = $xpath->query( ".//*[local-name()='pStyle']/@*[local-name()='val']", $p );
                    if ( $style_nodes && $style_nodes->length > 0 ) {
                        $style_val = (string) $style_nodes->item(0)->nodeValue;
                        if ( stripos( $style_val, 'Heading1' ) !== false || stripos( $style_val, 'Title' ) !== false || stripos( $style_val, 'Baslik1' ) !== false ) {
                            $is_heading = true;
                            $heading_level = 2;
                        } elseif ( stripos( $style_val, 'Heading2' ) !== false || stripos( $style_val, 'Baslik2' ) !== false ) {
                            $is_heading = true;
                            $heading_level = 3;
                        } elseif ( stripos( $style_val, 'Heading3' ) !== false ) {
                            $is_heading = true;
                            $heading_level = 4;
                        }
                    }

                    // Tüm run'ları (r) yakala (hyperlink, sdt, ins içindekiler dahil!)
                    $r_nodes = $xpath->query( ".//*[local-name()='r']", $p );
                    if ( $r_nodes && $r_nodes->length > 0 ) {
                        foreach ( $r_nodes as $r ) {
                            // Biçimlendirme kontrolleri
                            $is_bold   = $xpath->query( ".//*[local-name()='b' and (not(@*[local-name()='val']) or @*[local-name()='val'] != '0' and @*[local-name()='val'] != 'false')]", $r )->length > 0;
                            $is_italic = $xpath->query( ".//*[local-name()='i' and (not(@*[local-name()='val']) or @*[local-name()='val'] != '0' and @*[local-name()='val'] != 'false')]", $r )->length > 0;
                            $is_under  = $xpath->query( ".//*[local-name()='u' and (not(@*[local-name()='val']) or @*[local-name()='val'] != 'none')]", $r )->length > 0;
                            $is_strike = $xpath->query( ".//*[local-name()='strike']", $r )->length > 0;

                            // Metin parçaları
                            $t_nodes = $xpath->query( ".//*[local-name()='t']", $r );
                            $run_text = '';
                            foreach ( $t_nodes as $t ) {
                                $run_text .= $t->nodeValue;
                            }

                            // Satır sonu ve sekmeler
                            if ( $xpath->query( ".//*[local-name()='br' or local-name()='cr']", $r )->length > 0 ) {
                                $run_text .= "\n";
                            }
                            if ( $xpath->query( ".//*[local-name()='tab']", $r )->length > 0 ) {
                                $run_text .= "\t";
                            }

                            if ( $run_text === '' ) {
                                continue;
                            }

                            $p_plain .= $run_text;
                            $escaped = htmlspecialchars( $run_text, ENT_QUOTES, 'UTF-8' );
                            $escaped = nl2br( $escaped );

                            if ( $is_bold )   $escaped = "<strong>{$escaped}</strong>";
                            if ( $is_italic ) $escaped = "<em>{$escaped}</em>";
                            if ( $is_under )  $escaped = "<u>{$escaped}</u>";
                            if ( $is_strike ) $escaped = "<s>{$escaped}</s>";

                            $p_html .= $escaped;
                        }
                    } else {
                        // Eğer run etiketi bulunamadıysa doğrudan t etiketlerine bak
                        $direct_t = $xpath->query( ".//*[local-name()='t']", $p );
                        foreach ( $direct_t as $t ) {
                            $txt = $t->nodeValue;
                            $p_plain .= $txt;
                            $p_html .= htmlspecialchars( $txt, ENT_QUOTES, 'UTF-8' );
                        }
                    }

                    $trimmed_plain = trim( $p_plain );
                    if ( $trimmed_plain === '' ) {
                        // Boş satırı kıta ayracı veya paragraf aralığı olarak koru
                        if ( ! empty( $paragraph_texts ) ) {
                            $last_p = end( $paragraph_texts );
                            if ( ! empty( $last_p['plain'] ) ) {
                                $paragraph_texts[] = [
                                    'html'       => '<br>',
                                    'plain'      => '',
                                    'is_heading' => false,
                                    'level'      => 0,
                                ];
                            }
                        }
                        continue;
                    }

                    // Edebi özel etiket kontrolleri ([Yazar: ...], [Kategori: ...], vb.)
                    if ( preg_match( '/^\[?\s*Yazar\s*:\s*(.+?)\]?$/iu', $trimmed_plain, $m ) ) {
                        $detected_author = trim( $m[1] );
                        continue;
                    }
                    if ( preg_match( '/^\[?\s*Kategori\s*:\s*(.+?)\]?$/iu', $trimmed_plain, $m ) ) {
                        $detected_category = trim( $m[1] );
                        continue;
                    }
                    if ( preg_match( '/^\[?\s*(?:Etiketler|Etiket)\s*:\s*(.+?)\]?$/iu', $trimmed_plain, $m ) ) {
                        $detected_tags = trim( $m[1] );
                        continue;
                    }
                    if ( preg_match( '/^\[?\s*Başlık\s*:\s*(.+?)\]?$/iu', $trimmed_plain, $m ) ) {
                        $detected_title = trim( $m[1] );
                        continue;
                    }

                    $paragraph_texts[] = [
                        'html'       => $p_html,
                        'plain'      => $trimmed_plain,
                        'is_heading' => $is_heading,
                        'level'      => $heading_level,
                    ];
                    $raw_text_parts[] = $trimmed_plain;
                }

                // Paragrafları derle
                $p_count = count( $paragraph_texts );
                $is_first = true;

                foreach ( $paragraph_texts as $pt ) {
                    if ( $is_first && empty( $detected_title ) ) {
                        $is_first = false;
                        // Eğer ilk paragraf başlık formatındaysa veya < 120 karakterse ve arkasında en az bir paragraf daha varsa
                        if ( ( $pt['is_heading'] || mb_strlen( $pt['plain'] ) < 120 ) && $p_count > 1 ) {
                            $detected_title = $pt['plain'];
                            continue; // Başlığı gövdeye tekrar gömme
                        }
                    }

                    if ( $pt['is_heading'] ) {
                        $html_output[] = "<h{$pt['level']}>{$pt['html']}</h{$pt['level']}>";
                    } elseif ( $pt['plain'] === '' ) {
                        $html_output[] = "<p><br></p>";
                    } else {
                        $html_output[] = "<p>{$pt['html']}</p>";
                    }
                }
            }
        }

        // 2. İkincil / Yedek Yöntem (Regex Fallback): Eğer DOM ile içerik boş geldiyse asla pes etme!
        if ( empty( $html_output ) ) {
            if ( preg_match_all( '/<w:p(?:\s[^>]*)?>(.*?)<\/w:p>/is', $clean_xml, $p_matches ) ) {
                foreach ( $p_matches[1] as $p_inner ) {
                    if ( preg_match_all( '/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/is', $p_inner, $t_matches ) ) {
                        $line = implode( '', $t_matches[1] );
                        $line = html_entity_decode( $line, ENT_QUOTES, 'UTF-8' );
                        $trimmed = trim( $line );
                        if ( ! empty( $trimmed ) ) {
                            if ( empty( $detected_title ) && mb_strlen( $trimmed ) < 120 ) {
                                $detected_title = $trimmed;
                            } else {
                                $html_output[] = '<p>' . nl2br( htmlspecialchars( $trimmed, ENT_QUOTES, 'UTF-8' ) ) . '</p>';
                            }
                            $raw_text_parts[] = $trimmed;
                        }
                    }
                }
            }
        }

        // 3. Üçüncül Yöntem: Düz Metin Çıkarıcı
        if ( empty( $html_output ) ) {
            if ( preg_match_all( '/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/is', $clean_xml, $t_all ) ) {
                $all_str = implode( ' ', $t_all[1] );
                $all_str = html_entity_decode( $all_str, ENT_QUOTES, 'UTF-8' );
                $trimmed = trim( $all_str );
                if ( ! empty( $trimmed ) ) {
                    $html_output[] = '<p>' . nl2br( htmlspecialchars( $trimmed, ENT_QUOTES, 'UTF-8' ) ) . '</p>';
                    $raw_text_parts[] = $trimmed;
                }
            }
        }

        return [
            'html'              => implode( "\n", $html_output ),
            'raw_text'          => implode( " ", $raw_text_parts ),
            'detected_title'    => $detected_title,
            'detected_author'   => $detected_author,
            'detected_category' => $detected_category,
            'detected_tags'     => $detected_tags,
        ];
    }

    /**
     * Metinden Otomatik Edebi Kategori Önerisi
     */
    public static function suggest_category( string $text, string $title = '' ): string {
        $combined = mb_strtolower( $title . ' ' . $text, 'UTF-8' );

        // Şiir Tespiti
        if ( preg_match( '/\b(şiir|mısra|dize|kıta|koşma|gazel|hece|kafiye|redif)\b/iu', $combined ) ) {
            return 'Şiir';
        }
        // İnceleme / Eleştiri Tespiti
        if ( preg_match( '/\b(inceleme|eleştiri|tahlil|monografi|tahlili|metin şerhi|tarihsel)\b/iu', $combined ) ) {
            return 'İnceleme';
        }
        // Hikaye / Öykü Tespiti
        if ( preg_match( '/\b(öykü|hikaye|anlatı|kısa öykü|karakter|kurgu)\b/iu', $combined ) ) {
            return 'Hikaye';
        }
        // Söyleşi Tespiti
        if ( preg_match( '/\b(söyleşi|mülakat|röportaj|soru-cevap|konuşma)\b/iu', $combined ) ) {
            return 'Söyleşi';
        }

        return 'Deneme';
    }

    /**
     * Metinden Otomatik Akıllı Etiket (Tags) Önerisi
     */
    public static function suggest_tags( string $text ): string {
        $stop_words = [
            'bir', 've', 'veya', 'ile', 'bu', 'şu', 'o', 'de', 'da', 'ki', 'için', 'gibi', 'kadar', 'daha',
            'çok', 'en', 'ama', 'fakat', 'ancak', 'çünkü', 'ise', 'mi', 'mu', 'mü', 'mısın', 'misin',
            'olan', 'olarak', 'her', 'şey', 'zaman', 'sonra', 'önce', 'göre', 'ben', 'sen', 'biz', 'siz',
            'onlar', 'kendi', 'ya', 'hem', 'yok', 'var', 'bunu', 'buna', 'bunun', 'onu', 'ona', 'onun',
            'öyle', 'böyle', 'şöyle', 'tüm', 'bazı', 'hiç', 'biri', 'bütün', 'diye', 'üzere', 'karşı',
            'artık', 'yani', 'bile', 'hep', 'aynı', 'kendi', 'nasıl', 'neden', 'niçin', 'nerede',
        ];

        // Kelimeleri ayıkla
        $words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $text, 'UTF-8' ) );
        $freq = [];

        foreach ( $words as $w ) {
            $w = trim( $w );
            if ( mb_strlen( $w ) < 4 || mb_strlen( $w ) > 20 ) {
                continue;
            }
            if ( in_array( $w, $stop_words ) ) {
                continue;
            }
            $freq[$w] = ( $freq[$w] ?? 0 ) + 1;
        }

        arsort( $freq );
        $top_words = array_slice( array_keys( $freq ), 0, 4 );

        // Temel edebi etiketle zenginleştir
        if ( ! in_array( 'edebiyat', $top_words ) && count( $top_words ) < 5 ) {
            $top_words[] = 'edebiyat';
        }

        return implode( ', ', $top_words );
    }

    /**
     * Belgeyi Veritabanına Yazı Olarak Ekle
     */
    public static function import_to_database( array $data ): int {
        $db = Database::get();

        $title          = trim( $data['title'] ?? '' );
        if ( ! empty( $title ) ) {
            $title = Helpers::turkish_title_case( $title );
        }
        $author_id      = (int) ( $data['author_id'] ?? 0 );
        $category       = trim( $data['category'] ?? 'Deneme' );
        $tags           = trim( $data['tags'] ?? '' );
        $excerpt        = trim( $data['excerpt'] ?? '' );
        $content        = trim( $data['content'] ?? '' );
        if ( mb_stripos( $category, 'şiir' ) !== false || mb_stripos( $category, 'siir' ) !== false ) {
            $content = Helpers::format_poem_content( $content );
        }
        $featured_image = trim( $data['featured_image'] ?? '' );
        $reading_time   = (int) ( $data['reading_time'] ?? 5 );
        $status         = trim( $data['status'] ?? 'published' );

        if ( empty( $title ) ) {
            $title = 'İsimsiz Eser - ' . date( 'd.m.Y' );
        }

        // Slug oluştur
        $base_slug = Database::slugify( $title );
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

        if ( empty( $excerpt ) ) {
            $clean = strip_tags( $content );
            $excerpt = mb_substr( $clean, 0, 180 ) . '...';
        }

        $stmt = $db->prepare( "
            INSERT INTO posts (author_id, title, slug, category, tags, excerpt, content, featured_image, reading_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        " );

        $stmt->execute( [
            $author_id > 0 ? $author_id : null,
            $title,
            $slug,
            $category,
            $tags,
            $excerpt,
            $content,
            $featured_image,
            $reading_time,
            $status,
        ] );

        return (int) $db->lastInsertId();
    }
}
