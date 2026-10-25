<?php
/**
 * Pratfall Standalone - Genel Yardımcı Fonksiyonlar
 */

class Helpers {

    private static array $poet_slug_cache = [];

    /**
     * Yazarın Şair olup olmadığını kontrol et
     * title, author_title, bio veya author_bio alanlarında 'şair' / 'sair' kelimesi geçiyorsa true döner.
     */
    public static function is_poet( $author ): bool {
        if ( empty( $author ) ) {
            return false;
        }

        // Eğer doğrudan slug stringi verildiyse veritabanından unvan/biyografi kontrolü yap ve önbellekle
        if ( is_string( $author ) ) {
            $slug = trim( $author );
            if ( isset( self::$poet_slug_cache[$slug] ) ) {
                return self::$poet_slug_cache[$slug];
            }
            try {
                $db = Database::get();
                $stmt = $db->prepare( "SELECT title, bio FROM authors WHERE slug = ? LIMIT 1" );
                $stmt->execute( [ $slug ] );
                $row = $stmt->fetch();
                self::$poet_slug_cache[$slug] = $row ? self::is_poet( $row ) : false;
                return self::$poet_slug_cache[$slug];
            } catch ( Throwable $e ) {
                return false;
            }
        }

        if ( ! is_array( $author ) ) {
            return false;
        }

        $title = $author['title'] ?? ( $author['author_title'] ?? '' );
        $bio   = $author['bio'] ?? ( $author['author_bio'] ?? '' );
        $quote = $author['quote'] ?? '';

        $haystack = self::turkish_lower( $title . ' ' . $bio . ' ' . $quote );

        if ( mb_strpos( $haystack, 'şair' ) !== false || mb_strpos( $haystack, 'sair' ) !== false ) {
            return true;
        }

        return false;
    }

    /**
     * Yazar veya Şair Profil URL'si Üret
     * Şair ise: SITE_URL . '/sair/' . $slug
     * Diğer yazarlar için: SITE_URL . '/yazar/' . $slug
     */
    public static function author_url( $author, string $default_slug = '' ): string {
        $slug = '';
        $is_poet = false;

        if ( is_array( $author ) ) {
            $slug = $author['slug'] ?? ( $author['author_slug'] ?? $default_slug );
            $is_poet = self::is_poet( $author );
        } else {
            $slug = (string) $author;
            $is_poet = self::is_poet( $slug );
        }

        $slug = ltrim( trim( $slug ), '/' );
        $prefix = $is_poet ? '/sair/' : '/yazar/';

        return SITE_URL . $prefix . $slug;
    }

    /**
     * Yazar Avatarı veya Monogram Kaşe Pulunu Üret
     */
    public static function author_avatar( array $author, int $size = 44 ): string {
        $photo = ! empty( $author['photo'] ) ? $author['photo'] : '';
        $name  = htmlspecialchars( $author['name'] ?? 'Yazar', ENT_QUOTES, 'UTF-8' );

        if ( ! empty( $photo ) ) {
            return "<img src=\"{$photo}\" alt=\"{$name}\" style=\"width:{$size}px; height:{$size}px; object-fit:cover;\" />";
        }

        // Monogram Baş Harfleri (Türkçe uyumlu)
        $words = preg_split( '/\s+/u', trim( $author['name'] ?? '' ) );
        $initials = '';
        if ( count( $words ) >= 2 ) {
            $initials = mb_substr( $words[0], 0, 1 ) . mb_substr( $words[ count( $words ) - 1 ], 0, 1 );
        } elseif ( count( $words ) === 1 && mb_strlen( $words[0] ) >= 2 ) {
            $initials = mb_substr( $words[0], 0, 2 );
        } else {
            $initials = mb_substr( $author['name'] ?? 'P', 0, 1 );
        }
        $initials = mb_strtoupper( $initials, 'UTF-8' );

        $font_size = max( 0.75, round( $size * 0.38 / 16, 2 ) ) . 'rem';

        return "<div class=\"author-monogram-avatar rot-n1\" style=\"width:{$size}px; height:{$size}px; font-size:{$font_size};\">{$initials}</div>";
    }

    /**
     * Türkçe Tarih Formatı
     */
    public static function format_date( string $datetime ): string {
        $timestamp = strtotime( $datetime );
        $aylar = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
        ];
        $gun = date( 'j', $timestamp );
        $ay = $aylar[ (int) date( 'n', $timestamp ) ];
        $yil = date( 'Y', $timestamp );
        return "{$gun} {$ay} {$yil}";
    }

    /**
     * URL Uyumlu Slug Üret
     */
    public static function slugify( string $text ): string {
        return Database::slugify( $text );
    }

    /**
     * Metinden Kısa Özet Çıkar
     */
    public static function excerpt( string $content, int $limit = 180 ): string {
        $text = strip_tags( $content );
        $text = preg_replace( '/\s+/u', ' ', $text );
        $text = trim( $text );
        if ( mb_strlen( $text, 'UTF-8' ) <= $limit ) {
            return $text;
        }
        return mb_substr( $text, 0, $limit, 'UTF-8' ) . '...';
    }

    /**
     * Tahmini Okuma Süresi (Dakika)
     */
    public static function reading_time( string $content ): int {
        $word_count = count( preg_split( '/\s+/u', strip_tags( $content ), -1, PREG_SPLIT_NO_EMPTY ) );
        return max( 1, (int) ceil( $word_count / 180 ) );
    }

    /**
     * Edebi Okuma Süresi & Ritmi (Şiir, Kahve, Sessizlik Eşleşmesi)
     */
    public static function literary_reading_rhythm( int $minutes, string $category = '' ): string {
        $cat = mb_strtolower( trim( $category ), 'UTF-8' );
        if ( strpos( $cat, 'şiir' ) !== false || strpos( $cat, 'dize' ) !== false ) {
            return "{$minutes} DAKİKALIK DİZE RİTMİ · KISA MOLA";
        }
        if ( $minutes <= 2 ) {
            return "{$minutes} DAKİKA · BİR NEFESLİK METİN";
        } elseif ( $minutes <= 5 ) {
            return "{$minutes} DAKİKA OKUMA · 1 FİNCAN KAHVE";
        } elseif ( $minutes <= 8 ) {
            return "{$minutes} DAKİKA · BİR DENEME MOLASI";
        } elseif ( $minutes <= 12 ) {
            return "{$minutes} DAKİKA DERİN OKUMA · SESSİZLİK ARALIĞI";
        } else {
            return "{$minutes} DAKİKA GENİŞ OKUMA · MATBAA METNİ";
        }
    }

    /**
     * Türkçe Uyumlu Küçük Harfe Dönüştür (I -> ı, İ -> i)
     */
    public static function turkish_lower( string $text ): string {
        $text = str_replace( [ 'I', 'İ' ], [ 'ı', 'i' ], $text );
        return mb_strtolower( $text, 'UTF-8' );
    }

    /**
     * Türkçe Uyumlu Büyük Harfe Dönüştür (ı -> I, i -> İ)
     */
    public static function turkish_upper( string $text ): string {
        $text = str_replace( [ 'ı', 'i' ], [ 'I', 'İ' ], $text );
        return mb_strtoupper( $text, 'UTF-8' );
    }

    /**
     * Kelimelerin İlk Harfini Büyük, Diğerlerini Küçük Yap (Türkçe Uyumlu Title Case)
     * Örn: "VARLIK ÇATIŞMASI: VARLIK- YOKLUK" -> "Varlık Çatışması: Varlık- Yokluk"
     */
    public static function turkish_title_case( string $text ): string {
        $text = trim( $text );
        if ( $text === '' ) {
            return '';
        }

        // Önce tüm metni Türkçe küçük harfe çevir
        $lower = self::turkish_lower( $text );

        // Kelime başlarını büyük harfe çevir (kesme işaretinden sonraki ekleri küçük bırakır)
        return preg_replace_callback( '/(?<![\p{L}\'’])(\p{L})/u', function( $m ) {
            return self::turkish_upper( $m[1] );
        }, $lower );
    }

    /**
     * Diziyi Türkçe Harf Sırasına (A'dan Z'ye) Göre Sırala
     * A, B, C, Ç, D, E, F, G, Ğ, H, I, İ, J, K, L, M, N, O, Ö, P, R, S, Ş, T, U, Ü, V, Y, Z
     */
    public static function sort_turkish_alphabetical( array &$list, string $key = 'name' ): void {
        if ( class_exists( 'Collator' ) ) {
            $c = new Collator( 'tr_TR' );
            usort( $list, function( $a, $b ) use ( $c, $key ) {
                $valA = is_array( $a ) ? ( $a[$key] ?? '' ) : ( $a->$key ?? '' );
                $valB = is_array( $b ) ? ( $b[$key] ?? '' ) : ( $b->$key ?? '' );
                return $c->compare( (string) $valA, (string) $valB );
            } );
            return;
        }

        $alphabet = [
            'a'=>1, 'A'=>1, 'b'=>2, 'B'=>2, 'c'=>3, 'C'=>3, 'ç'=>4, 'Ç'=>4,
            'd'=>5, 'D'=>5, 'e'=>6, 'E'=>6, 'f'=>7, 'F'=>7, 'g'=>8, 'G'=>8,
            'ğ'=>9, 'Ğ'=>9, 'h'=>10, 'H'=>10, 'ı'=>11, 'I'=>11, 'i'=>12, 'İ'=>12,
            'j'=>13, 'J'=>13, 'k'=>14, 'K'=>14, 'l'=>15, 'L'=>15, 'm'=>16, 'M'=>16,
            'n'=>17, 'N'=>17, 'o'=>18, 'O'=>18, 'ö'=>19, 'Ö'=>19, 'p'=>20, 'P'=>20,
            'r'=>21, 'R'=>21, 's'=>22, 'S'=>22, 'ş'=>23, 'Ş'=>23, 't'=>24, 'T'=>24,
            'u'=>25, 'U'=>25, 'ü'=>26, 'Ü'=>26, 'v'=>27, 'V'=>27, 'y'=>28, 'Y'=>28,
            'z'=>29, 'Z'=>29
        ];

        usort( $list, function( $a, $b ) use ( $alphabet, $key ) {
            $valA = is_array( $a ) ? ( $a[$key] ?? '' ) : ( $a->$key ?? '' );
            $valB = is_array( $b ) ? ( $b[$key] ?? '' ) : ( $b->$key ?? '' );

            $strA = self::turkish_lower( trim( (string) $valA ) );
            $strB = self::turkish_lower( trim( (string) $valB ) );
            $lenA = mb_strlen( $strA, 'UTF-8' );
            $lenB = mb_strlen( $strB, 'UTF-8' );
            $minLen = min( $lenA, $lenB );

            for ( $i = 0; $i < $minLen; $i++ ) {
                $charA = mb_substr( $strA, $i, 1, 'UTF-8' );
                $charB = mb_substr( $strB, $i, 1, 'UTF-8' );
                $wA = $alphabet[$charA] ?? ( 100 + ord( $charA ) );
                $wB = $alphabet[$charB] ?? ( 100 + ord( $charB ) );
                if ( $wA !== $wB ) {
                    return $wA - $wB;
                }
            }
            return $lenA - $lenB;
        } );
    }

    /**
     * Şiir Metinlerini Semantik Kıta (Stanza) ve Mısra Düzenine Kavuştur
     * 
     * - Mısralar alt alta sıkı sıralanır (<br> ile bağlanır, araya otomatik devasa paragraf boşluğu atmaz).
     * - Boş satırlar veya kıta ayrımları ferah <p class="poem-stanza"> bloklarına dönüştürülür.
     * - Yazarın daha önce elle <br> ile biçimlendirdiği kıtaları bozmaz, korur.
     */
    public static function format_poem_content( string $content ): string {
        $content = trim( $content );
        if ( empty( $content ) ) {
            return '';
        }

        // Eğer içerik zaten poem-stanza ile biçimlendirilmişse doğrudan döndür
        if ( strpos( $content, 'class="poem-stanza"' ) !== false || strpos( $content, "class='poem-stanza'" ) !== false ) {
            return $content;
        }

        // HTML etiketleri yoksa (düz metinse)
        if ( strpos( $content, '<' ) === false ) {
            $raw_stanzas = preg_split( '/\r?\n\s*\r?\n/u', $content );
            $out = [];
            foreach ( $raw_stanzas as $st ) {
                $st = trim( $st );
                if ( $st === '' ) continue;
                $lines = array_map( function( $l ) {
                    return htmlspecialchars( trim( $l ), ENT_QUOTES, 'UTF-8' );
                }, preg_split( '/\r?\n/u', $st ) );
                $lines = array_filter( $lines, function( $l ) { return $l !== ''; } );
                if ( ! empty( $lines ) ) {
                    $out[] = '<p class="poem-stanza">' . implode( "<br>\n", $lines ) . '</p>';
                }
            }
            return implode( "\n\n", $out );
        }

        // Kıta ayracı belirteci
        $token = '<!--PRATFALL_STANZA_SPLIT-->';

        // 1. Boş paragrafları veya boş div'leri kıta ayracına dönüştür
        $clean = preg_replace( '#<p[^>]*>\s*(?:<br\s*/?>|&nbsp;|\s)*</p>#iu', $token, $content );
        $clean = preg_replace( '#<div[^>]*>\s*(?:<br\s*/?>|&nbsp;|\s)*</div>#iu', $token, $clean );
        $clean = preg_replace( '#(?:<br\s*/?>\s*){2,}#iu', $token, $clean );

        // 2. Token ile ayrılan blokları incele
        $sections = explode( $token, $clean );
        $final_stanzas = [];

        foreach ( $sections as $sec ) {
            $sec = trim( $sec );
            if ( $sec === '' ) {
                continue;
            }

            // Eğer bölüm içinde <p> veya <div> etiketleri varsa
            if ( preg_match_all( '#<(p|div)(?:[^>]*)>(.*?)</\1>#is', $sec, $matches ) ) {
                $current_lines = [];

                foreach ( $matches[2] as $inner ) {
                    $stripped = trim( strip_tags( $inner ) );
                    if ( $stripped === '' || $stripped === '&nbsp;' ) {
                        // Kendi içindeki boşluk kıtayı böler
                        if ( ! empty( $current_lines ) ) {
                            $final_stanzas[] = '<p class="poem-stanza">' . implode( "<br>\n", $current_lines ) . '</p>';
                            $current_lines = [];
                        }
                        continue;
                    }

                    // Eğer bu paragraf zaten kendi içinde <br> içeriyorsa (yazarın daha önce elle ayırdığı kıta)
                    if ( preg_match( '#<br\s*/?>#i', $inner ) ) {
                        if ( ! empty( $current_lines ) ) {
                            $final_stanzas[] = '<p class="poem-stanza">' . implode( "<br>\n", $current_lines ) . '</p>';
                            $current_lines = [];
                        }
                        $sub_lines = preg_split( '#<br\s*/?>#i', $inner );
                        $clean_sub = [];
                        foreach ( $sub_lines as $sl ) {
                            $tsl = trim( $sl );
                            if ( $tsl !== '' && $tsl !== '&nbsp;' ) {
                                $clean_sub[] = $tsl;
                            }
                        }
                        if ( ! empty( $clean_sub ) ) {
                            $final_stanzas[] = '<p class="poem-stanza">' . implode( "<br>\n", $clean_sub ) . '</p>';
                        }
                    } else {
                        // Tek dize
                        $current_lines[] = trim( $inner );
                    }
                }

                if ( ! empty( $current_lines ) ) {
                    $final_stanzas[] = '<p class="poem-stanza">' . implode( "<br>\n", $current_lines ) . '</p>';
                }
            } else {
                // <p> etiketi olmayan düz veya <br>'li içerik
                $lines = preg_split( '#(?:<br\s*/?>|\r?\n)+#iu', $sec );
                $clean_lines = [];
                foreach ( $lines as $l ) {
                    $tl = trim( $l );
                    if ( $tl !== '' && $tl !== '&nbsp;' ) {
                        $clean_lines[] = $tl;
                    }
                }
                if ( ! empty( $clean_lines ) ) {
                    $final_stanzas[] = '<p class="poem-stanza">' . implode( "<br>\n", $clean_lines ) . '</p>';
                }
            }
        }

        if ( empty( $final_stanzas ) ) {
            return $content;
        }

        return implode( "\n\n", $final_stanzas );
    }
}
