<?php
/**
 * Pratfall Standalone CMS - Yapılandırma Dosyası
 * 
 * cPanel ve standart paylaşımlı hostinglerle %100 uyumlu.
 * Otomatik yol ve URL algılama sistemi içerir.
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    define( 'PRATFALL_ROOT', __DIR__ );
}

// Hata raporlama (Canlıda 0, geliştirmede 1)
error_reporting( E_ALL );
ini_set( 'display_errors', '0' );

// Zaman Dilimi
date_default_timezone_set( 'Europe/Istanbul' );

// Sürüm Bilgisi
define( 'PRATFALL_APP_VERSION', '1.3.8' );

// Oturum Başlatma (Güvenli Çerez Ayarlarıyla)
if ( session_status() === PHP_SESSION_NONE ) {
    ini_set( 'session.cookie_httponly', 1 );
    ini_set( 'session.use_only_cookies', 1 );
    $is_ssl = ( ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1' || $_SERVER['HTTPS'] === 1 ) )
             || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) === 'https' )
             || ( isset( $_SERVER['SERVER_PORT'] ) && (int) $_SERVER['SERVER_PORT'] === 443 ) );
    if ( $is_ssl ) {
        ini_set( 'session.cookie_secure', 1 );
    }
    session_start();
}

// Dizin Yolları
define( 'PATH_DATA', PRATFALL_ROOT . '/data' );
define( 'PATH_UPLOADS', PRATFALL_ROOT . '/uploads' );
define( 'PATH_VIEWS', PRATFALL_ROOT . '/views' );
define( 'PATH_ADMIN', PRATFALL_ROOT . '/admin' );
define( 'PATH_APP', PRATFALL_ROOT . '/app' );

// SQLite Veritabanı Dosya Yolu
define( 'DB_FILE', PATH_DATA . '/database.sqlite' );

// E-posta ve Hassas Veri Şifreleme Anahtarı (AES-256-CBC ve HMAC-SHA256 Korumalı Kasa)
define( 'PRATFALL_CRYPTO_KEY', '7f9c8d3e2b1a4f5e6d7c8b9a0f1e2d3c4b5a6f7e8d9c0b1a2f3e4d5c6b7a8f90' );

// Dinamik Site URL Algılama (HTTPS ve Proxy Korumalı)
function pratfall_base_url() {
    $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Canlı domainlerde (pratfall.com.tr vb.) DAİMA https:// protokolünü zorunlu kıl
    if ( strpos( $host, 'localhost' ) === false && strpos( $host, '127.0.0.1' ) === false ) {
        // Eğer kullanıcı http üzerinden gelmişse anında güvenli https'ye 301 yönlendir
        $is_https = ( ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1' || $_SERVER['HTTPS'] === 1 ) )
                   || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) === 'https' )
                   || ( isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && strtolower( $_SERVER['HTTP_X_FORWARDED_SSL'] ) === 'on' )
                   || ( isset( $_SERVER['SERVER_PORT'] ) && (int) $_SERVER['SERVER_PORT'] === 443 ) );

        if ( ! $is_https && ! headers_sent() && php_sapi_name() !== 'cli' ) {
            $req_uri = $_SERVER['REQUEST_URI'] ?? '/';
            header( 'Location: https://' . $host . $req_uri, true, 301 );
            exit;
        }

        return 'https://' . $host;
    }

    $is_https = ( ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1' || $_SERVER['HTTPS'] === 1 ) )
               || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) === 'https' )
               || ( isset( $_SERVER['SERVER_PORT'] ) && (int) $_SERVER['SERVER_PORT'] === 443 ) );
    
    $protocol = $is_https ? 'https://' : 'http://';

    // Localhost veya test ortamında alt klasörü algıla
    $script_dir = str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ?? '' ) );
    $sub_dir = trim( preg_replace( '#/admin(/.*)?$#i', '', $script_dir ), '/' );
    if ( ! empty( $sub_dir ) ) {
        return $protocol . $host . '/' . $sub_dir;
    }

    return $protocol . $host;
}

define( 'SITE_URL', rtrim( pratfall_base_url(), '/' ) );

// Otomatik Sınıf Yükleyici (Autoloader)
spl_autoload_register( function ( $class ) {
    $file = PATH_APP . '/' . $class . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
