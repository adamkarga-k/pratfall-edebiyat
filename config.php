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
define( 'PRATFALL_APP_VERSION', '1.0.0' );

// Oturum Başlatma (Güvenli Çerez Ayarlarıyla)
if ( session_status() === PHP_SESSION_NONE ) {
    ini_set( 'session.cookie_httponly', 1 );
    ini_set( 'session.use_only_cookies', 1 );
    if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) {
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

// Dinamik Site URL Algılama (Admin ve Alt Dizin Uyumlu)
function pratfall_base_url() {
    $protocol = ( ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 ) )
               || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) ) ? 'https://' : 'http://';
    $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Belge kök dizini ile PRATFALL_ROOT arasındaki fark
    $doc_root = str_replace( '\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '' );
    $app_root = str_replace( '\\', '/', PRATFALL_ROOT );

    $sub_dir = '';
    if ( ! empty( $doc_root ) && strpos( $app_root, $doc_root ) === 0 ) {
        $sub_dir = substr( $app_root, strlen( $doc_root ) );
    } else {
        // Fallback: SCRIPT_NAME üzerinden /admin kısmını ayıkla
        $script_dir = str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ?? '' ) );
        $sub_dir = preg_replace( '#/admin(/.*)?$#i', '', $script_dir );
    }
    
    $sub_dir = trim( $sub_dir, '/' );
    if ( empty( $sub_dir ) ) {
        return $protocol . $host;
    }
    
    return $protocol . $host . '/' . $sub_dir;
}

define( 'SITE_URL', rtrim( pratfall_base_url(), '/' ) );

// Otomatik Sınıf Yükleyici (Autoloader)
spl_autoload_register( function ( $class ) {
    $file = PATH_APP . '/' . $class . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
