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

// Dinamik Site URL Algılama
function pratfall_base_url() {
    $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https://' : 'http://';
    $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Script dizini
    $script_dir = str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ) );
    $script_dir = trim( $script_dir, '/' );
    
    // Eğer root dizindeyse
    if ( empty( $script_dir ) ) {
        return $protocol . $host;
    }
    
    return $protocol . $host . '/' . $script_dir;
}

define( 'SITE_URL', rtrim( pratfall_base_url(), '/' ) );

// Otomatik Sınıf Yükleyici (Autoloader)
spl_autoload_register( function ( $class ) {
    $file = PATH_APP . '/' . $class . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
