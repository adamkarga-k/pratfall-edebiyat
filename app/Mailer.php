<?php
/**
 * Pratfall Standalone - E-Posta Dağıtım & SMTP Motoru
 * 
 * Sıfır harici kütüphane bağımlılığı (PHPMailer gerektirmez).
 * Standart PHP soketleri üzerinden cPanel/Kurumsal SMTP sunucularına doğrudan bağlanır.
 * SMTP ayarlanmamışsa yerel PHP mail() fonksiyonunu yedek olarak kullanır.
 */

class Mailer {

    /**
     * E-Posta Gönder
     * 
     * @param string $to Alıcı e-posta adresi
     * @param string $subject E-posta konusu
     * @param string $htmlContent HTML gövde içeriği
     * @param string $textContent Düz metin yedeği (opsiyonel)
     * @return array [ 'success' => bool, 'message' => string ]
     */
    public static function send( string $to, string $subject, string $htmlContent, string $textContent = '' ): array {
        $to = trim( $to );
        if ( ! filter_var( $to, FILTER_VALIDATE_EMAIL ) ) {
            return [ 'success' => false, 'message' => 'Geçersiz alıcı e-posta adresi.' ];
        }

        $host       = Database::get_setting( 'smtp_host', '' );
        $port       = (int) Database::get_setting( 'smtp_port', '465' );
        $secure     = strtolower( Database::get_setting( 'smtp_secure', 'ssl' ) ); // ssl, tls veya none
        $user       = Database::get_setting( 'smtp_user', '' );
        $pass       = Database::get_setting( 'smtp_pass', '' );
        $fromEmail  = Database::get_setting( 'smtp_from_email', '' );
        $fromName   = Database::get_setting( 'smtp_from_name', 'Pratfall Edebiyat Bülteni' );

        if ( empty( $fromEmail ) ) {
            $fromEmail = ! empty( $user ) && filter_var( $user, FILTER_VALIDATE_EMAIL ) ? $user : 'bulten@pratfall.com.tr';
        }

        // 1. SMTP Yapılandırılmışsa Doğrudan SMTP Soketi ile Gönder
        if ( ! empty( $host ) && ! empty( $user ) ) {
            return self::sendSmtp( $host, $port, $secure, $user, $pass, $fromEmail, $fromName, $to, $subject, $htmlContent, $textContent );
        }

        // 2. SMTP Yoksa Standart PHP mail() ile Gönder
        return self::sendPhpMail( $fromEmail, $fromName, $to, $subject, $htmlContent, $textContent );
    }

    /**
     * Yerel PHP mail() Fonksiyonu ile Gönderim
     */
    private static function sendPhpMail( string $fromEmail, string $fromName, string $to, string $subject, string $htmlContent, string $textContent ): array {
        $boundary = 'pratfall_' . md5( time() );

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: =?UTF-8?B?' . base64_encode( $fromName ) . '?= <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: Pratfall CMS Mailer';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split( base64_encode( ! empty( $textContent ) ? $textContent : strip_tags( $htmlContent ) ) ) . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split( base64_encode( $htmlContent ) ) . "\r\n";
        $body .= "--{$boundary}--";

        $encodedSubject = '=?UTF-8?B?' . base64_encode( $subject ) . '?=';
        $success = @mail( $to, $encodedSubject, $body, implode( "\r\n", $headers ) );

        if ( $success ) {
            return [ 'success' => true, 'message' => 'PHP mail() ile başarıyla gönderildi.' ];
        } else {
            return [ 'success' => false, 'message' => 'Sunucu yerel mail() fonksiyonu başarısız oldu. Lütfen cPanel SMTP ayarlarını girin.' ];
        }
    }

    /**
     * Doğrudan Soket Tabanlı SMTP Gönderici (TLS / SSL Destekli & cPanel Uyumlu)
     */
    private static function sendSmtp( string $host, int $port, string $secure, string $user, string $pass, string $fromEmail, string $fromName, string $to, string $subject, string $htmlContent, string $textContent ): array {
        $prefix = ( $secure === 'ssl' ) ? 'ssl://' : 'tcp://';
        $timeout = 15;
        $errno = 0;
        $errstr = '';

        // cPanel / Paylaşımlı Hostinglerde sunucu sertifika uyuşmazlığını (*.guzelhosting.com) aşmak için SSL bağlamı
        $context = stream_context_create( [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ] );

        $remote_socket = $prefix . $host . ':' . $port;
        $socket = @stream_socket_client( $remote_socket, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context );
        
        // Eğer ssl:// veya belirtilen host ile bağlanamazsa ve host localhost değilse, yerel sunucuyu (localhost) dene
        if ( ! $socket && $host !== 'localhost' && $host !== '127.0.0.1' ) {
            $fallback_socket = $prefix . 'localhost:' . $port;
            $socket = @stream_socket_client( $fallback_socket, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context );
        }

        if ( ! $socket ) {
            return [ 'success' => false, 'message' => "SMTP sunucusuna bağlanılamadı ({$host}:{$port}) - Hata: {$errstr} ({$errno})" ];
        }

        stream_set_timeout( $socket, $timeout );

        $readResponse = function() use ( $socket ) {
            $data = '';
            while ( $str = fgets( $socket, 515 ) ) {
                $data .= $str;
                if ( substr( $str, 3, 1 ) === ' ' ) {
                    break;
                }
            }
            return $data;
        };

        $sendCommand = function( $cmd ) use ( $socket ) {
            fputs( $socket, $cmd . "\r\n" );
        };

        $res = $readResponse();
        if ( substr( $res, 0, 3 ) !== '220' ) {
            fclose( $socket );
            return [ 'success' => false, 'message' => 'SMTP sunucusu hazır yanıtı vermedi: ' . trim( $res ) ];
        }

        // EHLO
        $sendCommand( 'EHLO ' . ( $_SERVER['SERVER_NAME'] ?? 'localhost' ) );
        $readResponse();

        // STARTTLS
        if ( $secure === 'tls' ) {
            $sendCommand( 'STARTTLS' );
            $tlsRes = $readResponse();
            if ( substr( $tlsRes, 0, 3 ) !== '220' ) {
                fclose( $socket );
                return [ 'success' => false, 'message' => 'STARTTLS başlatılamadı: ' . trim( $tlsRes ) ];
            }
            stream_socket_enable_crypto( $socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT );
            $sendCommand( 'EHLO ' . ( $_SERVER['SERVER_NAME'] ?? 'localhost' ) );
            $readResponse();
        }

        // AUTH LOGIN
        $sendCommand( 'AUTH LOGIN' );
        $authRes = $readResponse();
        if ( substr( $authRes, 0, 3 ) !== '334' ) {
            fclose( $socket );
            return [ 'success' => false, 'message' => 'AUTH LOGIN reddedildi: ' . trim( $authRes ) ];
        }

        $sendCommand( base64_encode( $user ) );
        $userRes = $readResponse();
        if ( substr( $userRes, 0, 3 ) !== '334' ) {
            fclose( $socket );
            return [ 'success' => false, 'message' => 'SMTP Kullanıcı adı kabul edilmedi: ' . trim( $userRes ) ];
        }

        $sendCommand( base64_encode( $pass ) );
        $passRes = $readResponse();
        if ( substr( $passRes, 0, 3 ) !== '235' ) {
            fclose( $socket );
            return [ 'success' => false, 'message' => 'SMTP Şifresi hatalı veya kimlik doğrulanamadı: ' . trim( $passRes ) ];
        }

        // MAIL FROM & RCPT TO
        $sendCommand( "MAIL FROM: <{$fromEmail}>" );
        $fromRes = $readResponse();
        if ( substr( $fromRes, 0, 3 ) !== '250' ) {
            fclose( $socket );
            return [ 'success' => false, 'message' => 'MAIL FROM reddedildi: ' . trim( $fromRes ) ];
        }

        $sendCommand( "RCPT TO: <{$to}>" );
        $toRes = $readResponse();
        if ( substr( $toRes, 0, 3 ) !== '250' && substr( $toRes, 0, 3 ) !== '251' ) {
            fclose( $socket );
            return [ 'success' => false, 'message' => "Alıcı reddedildi ({$to}): " . trim( $toRes ) ];
        }

        // DATA
        $sendCommand( 'DATA' );
        $dataRes = $readResponse();
        if ( substr( $dataRes, 0, 3 ) !== '354' ) {
            fclose( $socket );
            return [ 'success' => false, 'message' => 'DATA komutu kabul edilmedi: ' . trim( $dataRes ) ];
        }

        $boundary = 'pratfall_boundary_' . md5( uniqid( time() ) );

        $headers = [];
        $headers[] = 'Date: ' . date( 'r' );
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'From: =?UTF-8?B?' . base64_encode( $fromName ) . '?= <' . $fromEmail . '>';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode( $subject ) . '?=';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'X-Mailer: Pratfall Native SMTP';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = implode( "\r\n", $headers ) . "\r\n\r\n";

        // Düz Metin Bölümü
        $plain = ! empty( $textContent ) ? $textContent : strip_tags( $htmlContent );
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split( base64_encode( $plain ) ) . "\r\n";

        // HTML Bölümü
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split( base64_encode( $htmlContent ) ) . "\r\n";
        $body .= "--{$boundary}--\r\n.\r\n";

        $sendCommand( $body );
        $sendRes = $readResponse();

        $sendCommand( 'QUIT' );
        fclose( $socket );

        if ( substr( $sendRes, 0, 3 ) === '250' ) {
            return [ 'success' => true, 'message' => 'E-posta SMTP ile başarıyla iletildi.' ];
        }

        return [ 'success' => false, 'message' => 'Mesaj iletimi başarısız oldu: ' . trim( $sendRes ) ];
    }

    /**
     * Pratfall Özel Edebi Bülten Şablonu Oluştur
     */
    public static function createTemplate( string $title, string $contentHtml, string $unsubscribeUrl, string $buttonText = '', string $buttonUrl = '' ): string {
        $siteUrl = defined( 'SITE_URL' ) ? SITE_URL : 'https://pratfall.com.tr';
        $siteName = 'PRATFALL';

        $buttonHtml = '';
        if ( ! empty( $buttonText ) && ! empty( $buttonUrl ) ) {
            $buttonHtml = '
            <div style="margin:28px 0; text-align:center;">
                <a href="' . htmlspecialchars( $buttonUrl, ENT_QUOTES, 'UTF-8' ) . '" style="display:inline-block; background:#1a1a1a; color:#fbf7ee; text-decoration:none; padding:12px 26px; font-family:\'Courier New\', Courier, monospace; font-size:14px; font-weight:bold; letter-spacing:1px; border:2px solid #1a1a1a;">
                    ' . htmlspecialchars( $buttonText, ENT_QUOTES, 'UTF-8' ) . ' ↗
                </a>
            </div>';
        }

        return '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '</title>
</head>
<body style="margin:0; padding:0; background-color:#f4eee1; font-family:Georgia, serif; color:#1a1a1a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4eee1; padding:30px 15px;">
    <tr>
      <td align="center">
        <!-- Ana Kart -->
        <table role="presentation" width="100%" max-width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border:2px solid #1a1a1a; box-shadow:6px 6px 0px #1a1a1a;">
          
          <!-- Üst Edebi Başlık / Logo -->
          <tr>
            <td style="padding:28px 32px 20px 32px; border-bottom:2px solid #1a1a1a; background-color:#fbf7ee;">
              <div style="font-family:\'Courier New\', Courier, monospace; font-size:11px; font-weight:bold; letter-spacing:2px; color:#8b1e0f; margin-bottom:6px;">✦ MATBAA & EDEBİYAT BÜLTENİ</div>
              <div style="font-family:Georgia, serif; font-size:24px; font-weight:bold; letter-spacing:3px; color:#1a1a1a;">' . $siteName . '</div>
              <div style="font-family:\'Courier New\', Courier, monospace; font-size:11px; color:#666666; margin-top:4px;">Kusurlu Kalma Çabası · Bağımsız Edebiyat Platformu</div>
            </td>
          </tr>

          <!-- Bülten Gövdesi -->
          <tr>
            <td style="padding:32px; font-size:16px; line-height:1.75; color:#2c2c2c;">
              <h1 style="font-family:Georgia, serif; font-size:22px; font-weight:normal; line-height:1.3; margin:0 0 20px 0; color:#111111; border-left:3px solid #8b1e0f; padding-left:12px;">
                ' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '
              </h1>

              <div style="font-size:15px; line-height:1.8; color:#2a2a2a;">
                ' . $contentHtml . '
              </div>

              ' . $buttonHtml . '
            </td>
          </tr>

          <!-- Altbilgi & Ayrılma Linki (KVKK / GDPR Uyumlu) -->
          <tr>
            <td style="padding:22px 32px; background-color:#faf7f0; border-top:1px solid #e0dacb; font-family:\'Courier New\', Courier, monospace; font-size:11px; color:#777777; line-height:1.6;">
              <div>Bu e-posta, Pratfall Edebiyat Platformu bültenine kayıt olduğunuz için iletilmiştir.</div>
              <div style="margin-top:10px;">
                <a href="' . $siteUrl . '" style="color:#1a1a1a; text-decoration:underline;">pratfall.com.tr</a> · 
                <a href="' . $unsubscribeUrl . '" style="color:#8b1e0f; text-decoration:underline; font-weight:bold;">Abonelikten Ayrıl</a>
              </div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}
