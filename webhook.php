<?php
/**
 * Pratfall Standalone - GitHub Otomatik Dağıtım Webhook'u (Auto-Deploy)
 * 
 * GitHub Desktop'tan her "Push" yaptığınızda bu dosya tetiklenir
 * ve cPanel sunucunuz en son kodları otomatik olarak çeker.
 */

define( 'DEPLOY_TOKEN', 'pratfall-live-deploy-key' );

$token = $_GET['token'] ?? '';
if ( empty( $token ) || ! hash_equals( DEPLOY_TOKEN, $token ) ) {
    http_response_code( 403 );
    header( 'Content-Type: application/json; charset=utf-8' );
    echo json_encode( [ 'error' => 'Yetkisiz istek. Geçersiz güvenlik belirteci.' ] );
    exit;
}

$repo_dir = __DIR__;
$output = [];
$return_code = 0;

// 1. Git Pull ile Kodları Çek
if ( function_exists( 'exec' ) ) {
    $command = 'cd ' . escapeshellarg( $repo_dir ) . ' && git pull origin main 2>&1';
    @exec( $command, $output, $return_code );
}

// 2. cPanel UAPI ile Dağıtımı Tetikle
if ( function_exists( 'shell_exec' ) ) {
    $uapi_cmd = 'uapi VersionControlDeployment create repository_root=' . escapeshellarg( $repo_dir ) . ' 2>&1';
    $uapi_out = @shell_exec( $uapi_cmd );
    if ( $uapi_out ) {
        $output[] = $uapi_out;
    }
}

// 3. Dosya ve Dizin İzinlerini Otomatik Onar
@chmod( $repo_dir . '/data', 0755 );
@chmod( $repo_dir . '/uploads', 0755 );
@chmod( $repo_dir . '/assets', 0755 );
@chmod( $repo_dir . '/style.css', 0644 );
@chmod( $repo_dir . '/.htaccess', 0644 );

header( 'Content-Type: application/json; charset=utf-8' );
echo json_encode( [
    'status'    => 'success',
    'output'    => $output,
    'timestamp' => date( 'Y-m-d H:i:s' )
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
