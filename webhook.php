<?php
/**
 * Pratfall Standalone - GitHub Otomatik Dağıtım Webhook'u (Auto-Deploy)
 * 
 * GitHub Desktop'tan her "Push" yaptığınızda bu dosya tetiklenir
 * ve cPanel sunucunuz en son kodları otomatik olarak çeker.
 */

// Güvenlik Anahtarı (URL parametresi olarak kullanılır)
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

// 1. exec() ile git pull denemesi
if ( function_exists( 'exec' ) ) {
    $command = 'cd ' . escapeshellarg( $repo_dir ) . ' && git pull origin main 2>&1';
    @exec( $command, $output, $return_code );
}

// 2. cPanel UAPI yedek tetikleyici
if ( empty( $output ) && function_exists( 'shell_exec' ) ) {
    $uapi_cmd = 'uapi VersionControlDeployment create repository_root=' . escapeshellarg( $repo_dir ) . ' 2>&1';
    $uapi_out = @shell_exec( $uapi_cmd );
    if ( $uapi_out ) {
        $output[] = $uapi_out;
    }
}

header( 'Content-Type: application/json; charset=utf-8' );
echo json_encode( [
    'status'    => ( $return_code === 0 && ! empty( $output ) ) ? 'success' : 'completed',
    'output'    => $output,
    'timestamp' => date( 'Y-m-d H:i:s' )
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
