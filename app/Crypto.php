<?php
/**
 * Pratfall Standalone - Kriptografik Şifreleme ve Güvenlik Motoru
 * 
 * Abonelerin e-posta adreslerini ve hassas verilerini veritabanında ASLA düz metin olarak tutmaz.
 * Askeri düzeyde AES-256-CBC ile her kayıt için rastgele 16-byte IV üretilerek şifrelenir.
 * Arama ve mükerrer kayıt engelleme için ise HMAC-SHA256 kör indeksleme (Blind Indexing) kullanılır.
 */

class Crypto {

    private const CIPHER = 'AES-256-CBC';

    /**
     * 32-Byte (256-bit) İkili Şifreleme Anahtarını Getir
     */
    private static function getKey(): string {
        if ( defined( 'PRATFALL_CRYPTO_KEY' ) && ! empty( PRATFALL_CRYPTO_KEY ) ) {
            $raw = @hex2bin( PRATFALL_CRYPTO_KEY );
            if ( $raw !== false && strlen( $raw ) === 32 ) {
                return $raw;
            }
            return hash( 'sha256', PRATFALL_CRYPTO_KEY, true );
        }
        // Yedek güvenlik anahtarı (Varsayılan dosya ve domain tuzu ile)
        return hash( 'sha256', ( defined( 'DB_FILE' ) ? DB_FILE : __DIR__ ) . '-pratfall-secret-salt-2026', true );
    }

    /**
     * Metni Askeri Seviye AES-256-CBC ile Şifrele
     * Her çağrıda kriptografik olarak güvenli yeni bir 16-byte IV üretilir.
     * 
     * @param string $plaintext Şifrelenecek düz metin (e.g. e-posta)
     * @return string Base64 formatında paketlenmiş IV + Şifreli Metin
     */
    public static function encrypt( string $plaintext ): string {
        $plaintext = trim( $plaintext );
        if ( $plaintext === '' ) {
            return '';
        }

        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length( self::CIPHER );
        $iv = random_bytes( $ivLength );

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ( $ciphertext === false ) {
            throw new RuntimeException( 'Veri şifreleme başarısız oldu.' );
        }

        // 16 byte IV + Şifreli veri birleştirilerek base64 ile kodlanır
        return base64_encode( $iv . $ciphertext );
    }

    /**
     * Şifrelenmiş Metni Çöz (Decryption)
     * 
     * @param string $payload Base64 formatında IV + Şifreli Metin
     * @return string|null Çözülen düz metin veya hata durumunda null
     */
    public static function decrypt( string $payload ): ?string {
        $payload = trim( $payload );
        if ( $payload === '' ) {
            return '';
        }

        $raw = base64_decode( $payload, true );
        if ( $raw === false ) {
            return null;
        }

        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length( self::CIPHER );

        if ( strlen( $raw ) < $ivLength ) {
            return null;
        }

        $iv = substr( $raw, 0, $ivLength );
        $ciphertext = substr( $raw, $ivLength );

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Kör İndeks (Blind Indexing) - HMAC-SHA256
     * 
     * Veritabanında e-postanın kendisi şifreli olsa dahi,
     * mükerrer kayıt kontrolü ve tam eşleşmeli aramaların $O(1)$ hızında
     * yapılabilmesi için tek yönlü ve tuzlu (keyed hash) parmak izi üretir.
     * Geri döndürülemez ve gökkuşağı tablolarına (rainbow table) karşı korumalıdır.
     * 
     * @param string $email E-posta adresi
     * @return string 64 karakterli onaltılık HMAC-SHA256 özeti
     */
    public static function hash( string $email ): string {
        $normalized = strtolower( trim( $email ) );
        return hash_hmac( 'sha256', $normalized, self::getKey() );
    }

    /**
     * Kriptografik Olarak Güvenli Rastgele Belirteç (Token) Üret
     * Abonelikten tek tıkla ayrılma bağlantıları için kullanılır.
     * 
     * @param int $bytes Bayt uzunluğu (varsayılan 24 byte = 48 karakter hex)
     * @return string
     */
    public static function generateToken( int $bytes = 24 ): string {
        return bin2hex( random_bytes( $bytes ) );
    }
}
