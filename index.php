<?php
/**
 * Pratfall Standalone - Ana Giriş Noktası (Front Controller)
 */

define( 'PRATFALL_ROOT', __DIR__ );
require_once PRATFALL_ROOT . '/config.php';

// Veritabanı ve Şemayı Hazırla
Database::get();

// 1. Arama Motoru ve Sistem Rotaları
Router::get( '/sitemap.xml', function() {
    Seo::generate_sitemap();
} );

Router::get( '/robots.txt', function() {
    Seo::generate_robots();
} );

// 2. Ön Yüz (Front-End) Rotaları
Router::get( '/', PATH_VIEWS . '/front.php' );
Router::get( '/dergiler', PATH_VIEWS . '/dergiler.php' );
Router::get( '/eser/{slug}', PATH_VIEWS . '/single.php' );
Router::get( '/yazar/{slug}', PATH_VIEWS . '/author.php' );

// 3. Yönetim Paneli (Admin) Rotaları
Router::any( '/admin', PATH_ADMIN . '/index.php' );
Router::any( '/admin/login', PATH_ADMIN . '/login.php' );
Router::get( '/admin/logout', function() {
    Auth::logout();
    header( 'Location: ' . SITE_URL . '/admin/login' );
    exit;
} );

Router::any( '/admin/posts', PATH_ADMIN . '/posts.php' );
Router::any( '/admin/authors', PATH_ADMIN . '/authors.php' );
Router::any( '/admin/magazines', PATH_ADMIN . '/magazines.php' );
Router::any( '/admin/import-docx', PATH_ADMIN . '/import-docx.php' );
Router::any( '/admin/settings', PATH_ADMIN . '/settings.php' );
Router::any( '/admin/git-sync', PATH_ADMIN . '/git-sync.php' );

// Yönlendiriciyi Çalıştır
Router::dispatch();
