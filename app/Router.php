<?php
/**
 * Pratfall Standalone - Hafif & Hızlı URL Yönlendirici (Router)
 */

class Router {

    private static array $routes = [];

    public static function get( string $pattern, callable|string $handler ): void {
        self::add( 'GET', $pattern, $handler );
    }

    public static function post( string $pattern, callable|string $handler ): void {
        self::add( 'POST', $pattern, $handler );
    }

    public static function any( string $pattern, callable|string $handler ): void {
        self::add( 'ANY', $pattern, $handler );
    }

    private static function add( string $method, string $pattern, callable|string $handler ): void {
        self::$routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public static function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

        // Script yolunu temizle (Subdirectory desteği)
        $script_dir = str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ) );
        if ( $script_dir !== '/' && strpos( $uri, $script_dir ) === 0 ) {
            $uri = substr( $uri, strlen( $script_dir ) );
        }
        $uri = '/' . trim( $uri, '/' );

        foreach ( self::$routes as $route ) {
            if ( $route['method'] !== 'ANY' && $route['method'] !== $method ) {
                continue;
            }

            // Regex kalıbı oluştur: /eser/{slug} -> /eser/(?P<slug>[^/]+)
            $regex = preg_replace( '/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['pattern'] );
            $regex = '#^' . $regex . '$#u';

            if ( preg_match( $regex, $uri, $matches ) ) {
                $params = array_filter( $matches, 'is_string', ARRAY_FILTER_USE_KEY );

                if ( is_callable( $route['handler'] ) ) {
                    call_user_func_array( $route['handler'], $params );
                    return;
                } elseif ( is_string( $route['handler'] ) && file_exists( $route['handler'] ) ) {
                    // Parametreleri değişken olarak sayfaya aktar
                    extract( $params );
                    require $route['handler'];
                    return;
                }
            }
        }

        // 404 Sayfası
        http_response_code( 404 );
        if ( file_exists( PATH_VIEWS . '/404.php' ) ) {
            require PATH_VIEWS . '/404.php';
        } else {
            echo '<div style="font-family:sans-serif; text-align:center; padding:5rem;"><h1>404 - Sayfa Bulunamadı</h1><p>Aradığınız metin silinmiş veya taşınmış olabilir.</p><a href="' . SITE_URL . '">← Ana Sayfaya Dön</a></div>';
        }
    }
}
