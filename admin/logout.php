<?php
/**
 * Pratfall Standalone - Oturum Kapatma
 */
require_once dirname( __DIR__ ) . '/config.php';
Auth::logout();
header( 'Location: ' . SITE_URL . '/admin/login.php' );
exit;
