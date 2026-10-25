<?php
/**
 * Pratfall Standalone - Otomatik Görsel Optimizasyon ve Sıkıştırma Motoru
 * 
 * - Yüklenen fotoğrafları otomatik olarak 1600px maksimum genişliğe ölçeklendirir.
 * - Destekleniyorsa modern WebP formatına dönüştürür (dosya boyutunu %60-%80 düşürür).
 * - Görsel kalitesini kaybetmeden sitenin açılış hızını ultra optimize eder.
 * - Otomatik 0644 izinlerini verir.
 */

class ImageOptimizer {

    /**
     * Yüklenen Görseli Optimize Et ve Kaydet
     * 
     * @param array $file $_FILES['input_name'] dizisi
     * @param string $target_dir Hedef klasör yolu
     * @param string $prefix Dosya adı ön eki
     * @return string|null Oluşan dosya adı veya hata durumunda null
     */
    public static function optimize_uploaded_file( array $file, string $target_dir, string $prefix = 'img' ): ?string {
        if ( empty( $file['tmp_name'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
            return null;
        }

        if ( ! is_dir( $target_dir ) ) {
            @mkdir( $target_dir, 0755, true );
        }

        $tmp_path  = $file['tmp_name'];
        $orig_name = $file['name'];
        $ext       = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );

        if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ] ) ) {
            return null;
        }

        // GD Kütüphanesi kontrolü
        if ( ! extension_loaded( 'gd' ) || ! function_exists( 'imagecreatefromstring' ) ) {
            $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;
            $dest = rtrim( $target_dir, '/' ) . '/' . $filename;
            if ( move_uploaded_file( $tmp_path, $dest ) ) {
                @chmod( $dest, 0644 );
                return $filename;
            }
            return null;
        }

        $raw_data = file_get_contents( $tmp_path );
        $image = @imagecreatefromstring( $raw_data );
        if ( ! $image ) {
            $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;
            $dest = rtrim( $target_dir, '/' ) . '/' . $filename;
            if ( move_uploaded_file( $tmp_path, $dest ) ) {
                @chmod( $dest, 0644 );
                return $filename;
            }
            return null;
        }

        $width  = imagesx( $image );
        $height = imagesy( $image );

        // Maksimum Genişlik Sınırı: 1600px (Sitenin ultra hızlı kalması için)
        $max_width = 1600;
        if ( $width > $max_width ) {
            $new_width  = $max_width;
            $new_height = (int) round( $height * ( $max_width / $width ) );
            $resized    = imagecreatetruecolor( $new_width, $new_height );

            // Şeffaflık Koruması (PNG / WebP)
            imagealphablending( $resized, false );
            imagesavealpha( $resized, true );

            imagecopyresampled( $resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
            imagedestroy( $image );
            $image = $resized;
        }

        // WebP Desteği Varsa WebP'ye Dönüştür
        $supports_webp = function_exists( 'imagewebp' );
        $final_ext = $supports_webp ? 'webp' : ( $ext === 'png' ? 'png' : 'jpg' );
        $filename  = $prefix . '_' . time() . '_' . uniqid() . '.' . $final_ext;
        $dest      = rtrim( $target_dir, '/' ) . '/' . $filename;

        if ( $supports_webp ) {
            imagewebp( $image, $dest, 85 ); // %85 kalite: Mükemmel keskinlik + minimum dosya boyutu
        } elseif ( $ext === 'png' ) {
            imagepng( $image, $dest, 8 );
        } else {
            imagejpeg( $image, $dest, 85 );
        }

        imagedestroy( $image );
        @chmod( $dest, 0644 );

        return $filename;
    }

    /**
     * Canvas'tan Gelen Base64 Görselini Optimize Et ve Kaydet
     */
    public static function optimize_base64_image( string $base64_data, string $target_dir, string $prefix = 'cover' ): ?string {
        if ( empty( $base64_data ) ) {
            return null;
        }

        if ( preg_match( '/^data:image\/(\w+);base64,/', $base64_data ) ) {
            $base64_data = substr( $base64_data, strpos( $base64_data, ',' ) + 1 );
        }

        $decoded = base64_decode( $base64_data );
        if ( ! $decoded ) {
            return null;
        }

        if ( ! is_dir( $target_dir ) ) {
            @mkdir( $target_dir, 0755, true );
        }

        $supports_webp = function_exists( 'imagewebp' ) && extension_loaded( 'gd' );
        $final_ext     = $supports_webp ? 'webp' : 'png';
        
        $clean_slug    = Database::slugify( $prefix );
        if ( empty( $clean_slug ) || $clean_slug === 'cover' || $clean_slug === 'kapak' ) {
            $filename  = 'kapak_' . time() . '_' . uniqid() . '.' . $final_ext;
        } else {
            $filename  = $clean_slug . '.' . $final_ext;
            if ( file_exists( rtrim( $target_dir, '/' ) . '/' . $filename ) ) {
                $filename = $clean_slug . '-' . time() . '.' . $final_ext;
            }
        }
        $dest          = rtrim( $target_dir, '/' ) . '/' . $filename;

        if ( extension_loaded( 'gd' ) && function_exists( 'imagecreatefromstring' ) ) {
            $image = @imagecreatefromstring( $decoded );
            if ( $image ) {
                if ( $supports_webp ) {
                    imagewebp( $image, $dest, 88 );
                } else {
                    imagepng( $image, $dest, 8 );
                }
                imagedestroy( $image );
                @chmod( $dest, 0644 );
                return $filename;
            }
        }

        if ( file_put_contents( $dest, $decoded ) ) {
            @chmod( $dest, 0644 );
            return $filename;
        }

        return null;
    }
}
