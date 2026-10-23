<?php
/**
 * Pratfall Standalone - Tek Tıkla Veritabanı Yedeği İndirici
 */

if ( ! defined( 'PRATFALL_ROOT' ) ) {
    require_once dirname( __DIR__ ) . '/config.php';
}

Auth::require_login();

if ( ! file_exists( DB_FILE ) ) {
    die( 'Veritabanı dosyası bulunamadı.' );
}

$filename = 'pratfall-veritabani-yedek-' . date( 'Y-m-d-His' ) . '.sqlite';
$filesize = filesize( DB_FILE );

header( 'Content-Description: File Transfer' );
header( 'Content-Type: application/x-sqlite3' );
header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
header( 'Expires: 0' );
header( 'Cache-Control: must-revalidate' );
header( 'Pragma: public' );
header( 'Content-Length: ' . $filesize );

readfile( DB_FILE );
exit;
