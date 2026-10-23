<?php
/**
 * Pratfall Standalone - Genel Yardımcı Fonksiyonlar
 */

class Helpers {

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
}
