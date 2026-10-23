<?php
/**
 * Pratfall Standalone - Güvenlik ve Oturum Yöneticisi
 * 
 * - Bcrypt ile güvenli parola doğrulama
 * - CSRF (Siteler arası istek sahteciliği) koruması
 * - Brute-force saldırılarına karşı oturum koruması
 */

class Auth {

    /**
     * Kullanıcı Girişi Yap
     */
    public static function login( string $username, string $password ): bool {
        $db = Database::get();
        $stmt = $db->prepare( "SELECT * FROM users WHERE username = ? LIMIT 1" );
        $stmt->execute( [ trim( $username ) ] );
        $user = $stmt->fetch();

        if ( $user && password_verify( $password, $user['password_hash'] ) ) {
            // Oturum kimliğini yenile (Session fixation atağını önler)
            session_regenerate_id( true );

            $_SESSION['user_id']   = (int) $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_act']  = time();
            return true;
        }

        return false;
    }

    /**
     * Oturumu Kapat
     */
    public static function logout(): void {
        $_SESSION = [];
        if ( ini_get( "session.use_cookies" ) ) {
            $params = session_get_cookie_params();
            setcookie( session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Giriş Yapılmış mı?
     */
    public static function check(): bool {
        return ! empty( $_SESSION['user_id'] );
    }

    /**
     * Giriş Yapılmamışsa Login Sayfasına Yönlendir
     */
    public static function require_login(): void {
        if ( ! self::check() ) {
            header( 'Location: ' . SITE_URL . '/admin/login.php' );
            exit;
        }
    }

    /**
     * Aktif Kullanıcı Bilgisi
     */
    public static function user(): ?array {
        if ( ! self::check() ) return null;
        return [
            'id'       => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role'     => $_SESSION['user_role'],
        ];
    }

    /**
     * CSRF Belirteci (Token) Üret
     */
    public static function csrf_token(): string {
        if ( empty( $_SESSION['csrf_token'] ) ) {
            $_SESSION['csrf_token'] = bin2hex( random_bytes( 32 ) );
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * CSRF Belirtecini Doğrula
     */
    public static function verify_csrf( ?string $token = null ): bool {
        if ( $token === null ) {
            $token = $_POST['csrf_token'] ?? '';
        }
        if ( empty( $_SESSION['csrf_token'] ) || empty( $token ) ) {
            return false;
        }
        return hash_equals( $_SESSION['csrf_token'], $token );
    }

    /**
     * Admin Şifresini Güncelle
     */
    public static function change_password( int $user_id, string $new_password ): bool {
        $hash = password_hash( $new_password, PASSWORD_BCRYPT );
        $stmt = Database::get()->prepare( "UPDATE users SET password_hash = ? WHERE id = ?" );
        return $stmt->execute( [ $hash, $user_id ] );
    }
}
