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

        $boundary = 'pratfall_boundary_' . md5( uniqid( (string) time(), true ) );
        $domain = ! empty( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'pratfall.com.tr';
        $messageId = '<' . time() . '.' . bin2hex( random_bytes( 8 ) ) . '@' . $domain . '>';

        $headers = [];
        $headers[] = 'Date: ' . date( 'r' );
        $headers[] = 'Message-ID: ' . $messageId;
        $headers[] = 'From: =?UTF-8?B?' . base64_encode( $fromName ) . '?= <' . $fromEmail . '>';
        $headers[] = 'Reply-To: <' . $fromEmail . '>';
        $headers[] = 'Return-Path: <' . $fromEmail . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode( $subject ) . '?=';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'X-Mailer: Pratfall Standalone Engine';
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
        $body .= "--{$boundary}--\r\n";

        // E-Posta gövdesini ve ardından RFC 5321 bitiş işaretini (.) gönder
        fputs( $socket, $body );
        $sendCommand( '.' );
        $sendRes = $readResponse();

        $sendCommand( 'QUIT' );
        fclose( $socket );

        if ( substr( $sendRes, 0, 3 ) === '250' ) {
            return [ 'success' => true, 'message' => 'E-posta SMTP ile başarıyla iletildi.' ];
        }

        return [ 'success' => false, 'message' => 'Mesaj iletimi başarısız oldu: ' . trim( $sendRes ) ];
    }

    /**
     * Edebi E-Posta Şablonu Üretir (4 Farklı Tema: Kusurlu Matbaa, Noir, Kraft Mektup, Minimal Zen)
     */
    public static function createTemplate( string $title, string $contentHtml, string $unsubscribeUrl, string $buttonText = '', string $buttonUrl = '', array $custom = [] ): string {
        $siteUrl   = defined( 'SITE_URL' ) ? SITE_URL : 'https://pratfall.com.tr';
        
        $topBadge  = isset( $custom['top_badge'] ) ? $custom['top_badge'] : Database::get_setting( 'newsletter_top_badge', '✦' );
        $brandName = ! empty( $custom['brand_name'] ) ? $custom['brand_name'] : Database::get_setting( 'newsletter_brand_name', 'PRATFALL' );
        $slogan    = isset( $custom['slogan'] ) ? $custom['slogan'] : Database::get_setting( 'newsletter_slogan', 'Kusurlu Kalma Çabası' );
        $footerNote= ! empty( $custom['footer_note'] ) ? $custom['footer_note'] : Database::get_setting( 'newsletter_footer_note', 'Bu e-posta, Pratfall Dergimize eser gönderdiğiniz için gelmektedir. Rahatsızlık verdiysek aşağıdaki bağlantıyı ziyaret edebilirsin.' );
        $style     = ! empty( $custom['style'] ) ? $custom['style'] : Database::get_setting( 'newsletter_template_style', 'brutalist' );

        // 4 Farklı Edebi Tema Paletleri
        switch ( $style ) {
            case 'noir': // Daktilo Noir (Koyu / Monokrom)
                $bodyBg          = '#141416';
                $cardBg          = '#1c1c20';
                $cardBorder      = '1.5px solid #2e2e36';
                $cardShadow      = '0 8px 30px rgba(0,0,0,0.7)';
                $headerBg        = '#18181b';
                $headerBorder    = '1.5px solid #2e2e36';
                $topBadgeColor   = '#ea580c';
                $brandColor      = '#ffffff';
                $sloganColor     = '#a1a1aa';
                $headingColor    = '#ffffff';
                $headingBorder   = '3px solid #ea580c';
                $textColor       = '#d4d4d8';
                $btnBg           = '#ea580c';
                $btnColor        = '#ffffff';
                $btnBorder       = '2px solid #ea580c';
                $footerBg        = '#18181b';
                $footerBorder    = '1px solid #27272a';
                $footerTextColor = '#71717a';
                $footerLinkColor = '#a1a1aa';
                $unsubscribeColor= '#ea580c';
                break;

            case 'kraft': // Edebi Mektup (Kraft & Sıcak Parşömen)
                $bodyBg          = '#eae3d2';
                $cardBg          = '#fcfaf5';
                $cardBorder      = '1.5px solid #cfc2a9';
                $cardShadow      = '4px 4px 18px rgba(78,50,30,0.1)';
                $headerBg        = '#f4eee1';
                $headerBorder    = '1.5px solid #cfc2a9';
                $topBadgeColor   = '#8c351e';
                $brandColor      = '#38160d';
                $sloganColor     = '#785b4f';
                $headingColor    = '#2c120a';
                $headingBorder   = '3px solid #8c351e';
                $textColor       = '#3d2b22';
                $btnBg           = '#8c351e';
                $btnColor        = '#faf7ee';
                $btnBorder       = '2px solid #8c351e';
                $footerBg        = '#f2ebd9';
                $footerBorder    = '1px solid #dfd4bf';
                $footerTextColor = '#755c4d';
                $footerLinkColor = '#38160d';
                $unsubscribeColor= '#8c351e';
                break;

            case 'minimal': // Minimalist Zen (Modern / Substack Tarzı)
                $bodyBg          = '#fafafa';
                $cardBg          = '#ffffff';
                $cardBorder      = '1px solid #e5e7eb';
                $cardShadow      = '0 2px 10px rgba(0,0,0,0.03)';
                $headerBg        = '#ffffff';
                $headerBorder    = '1px solid #f3f4f6';
                $topBadgeColor   = '#6b7280';
                $brandColor      = '#111827';
                $sloganColor     = '#6b7280';
                $headingColor    = '#111827';
                $headingBorder   = 'none';
                $textColor       = '#374151';
                $btnBg           = '#111827';
                $btnColor        = '#ffffff';
                $btnBorder       = '1px solid #111827';
                $footerBg        = '#f9fafb';
                $footerBorder    = '1px solid #f3f4f6';
                $footerTextColor = '#9ca3af';
                $footerLinkColor = '#4b5563';
                $unsubscribeColor= '#ef4444';
                break;

            case 'brutalist': // Kusurlu Matbaa (Varsayılan)
            default:
                $bodyBg          = '#f4eee1';
                $cardBg          = '#ffffff';
                $cardBorder      = '2px solid #1a1a1a';
                $cardShadow      = '6px 6px 0px #1a1a1a';
                $headerBg        = '#fbf7ee';
                $headerBorder    = '2px solid #1a1a1a';
                $topBadgeColor   = '#8b1e0f';
                $brandColor      = '#1a1a1a';
                $sloganColor     = '#666666';
                $headingColor    = '#111111';
                $headingBorder   = '3px solid #8b1e0f';
                $textColor       = '#2a2a2a';
                $btnBg           = '#1a1a1a';
                $btnColor        = '#fbf7ee';
                $btnBorder       = '2px solid #1a1a1a';
                $footerBg        = '#faf7f0';
                $footerBorder    = '1px solid #e0dacb';
                $footerTextColor = '#777777';
                $footerLinkColor = '#1a1a1a';
                $unsubscribeColor= '#8b1e0f';
                break;
        }

        $buttonHtml = '';
        if ( ! empty( $buttonText ) && ! empty( $buttonUrl ) ) {
            $buttonHtml = '
            <div style="margin:28px 0; text-align:center;">
                <a href="' . htmlspecialchars( $buttonUrl, ENT_QUOTES, 'UTF-8' ) . '" style="display:inline-block; background:' . $btnBg . '; color:' . $btnColor . '; text-decoration:none; padding:12px 26px; font-family:\'Courier New\', Courier, monospace; font-size:14px; font-weight:bold; letter-spacing:1px; border:' . $btnBorder . ';">
                    ' . htmlspecialchars( $buttonText, ENT_QUOTES, 'UTF-8' ) . ' ↗
                </a>
            </div>';
        }

        $headingStyle = ( $headingBorder !== 'none' ) ? 'border-left:' . $headingBorder . '; padding-left:12px;' : '';

        return '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '</title>
</head>
<body style="margin:0; padding:0; background-color:' . $bodyBg . '; font-family:Georgia, serif; color:' . $textColor . ';">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:' . $bodyBg . '; padding:30px 15px;">
    <tr>
      <td align="center">
        <!-- Ana Kart -->
        <table role="presentation" width="100%" max-width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; width:100%; background-color:' . $cardBg . '; border:' . $cardBorder . '; box-shadow:' . $cardShadow . ';">
          
          <!-- Üst Edebi Başlık / Logo -->
          <tr>
            <td style="padding:28px 32px 20px 32px; border-bottom:' . $headerBorder . '; background-color:' . $headerBg . ';">
              <div style="font-family:\'Courier New\', Courier, monospace; font-size:11px; font-weight:bold; letter-spacing:2px; color:' . $topBadgeColor . '; margin-bottom:6px;">' . htmlspecialchars( $topBadge, ENT_QUOTES, 'UTF-8' ) . '</div>
              <div style="font-family:Georgia, serif; font-size:24px; font-weight:bold; letter-spacing:3px; color:' . $brandColor . ';">' . htmlspecialchars( $brandName, ENT_QUOTES, 'UTF-8' ) . '</div>
              <div style="font-family:\'Courier New\', Courier, monospace; font-size:11px; color:' . $sloganColor . '; margin-top:4px;">' . htmlspecialchars( $slogan, ENT_QUOTES, 'UTF-8' ) . '</div>
            </td>
          </tr>

          <!-- Bülten Gövdesi -->
          <tr>
            <td style="padding:32px; font-size:16px; line-height:1.75; color:' . $textColor . ';">
              <h1 style="font-family:Georgia, serif; font-size:22px; font-weight:normal; line-height:1.3; margin:0 0 20px 0; color:' . $headingColor . '; ' . $headingStyle . '">
                ' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '
              </h1>

              <div style="font-size:15px; line-height:1.8; color:' . $textColor . ';">
                ' . $contentHtml . '
              </div>

              ' . $buttonHtml . '
            </td>
          </tr>

          <!-- Altbilgi & Ayrılma Linki (KVKK / GDPR Uyumlu) -->
          <tr>
            <td style="padding:22px 32px; background-color:' . $footerBg . '; border-top:' . $footerBorder . '; font-family:\'Courier New\', Courier, monospace; font-size:11px; color:' . $footerTextColor . '; line-height:1.6;">
              <div>' . htmlspecialchars( $footerNote, ENT_QUOTES, 'UTF-8' ) . '</div>
              <div style="margin-top:10px;">
                <a href="' . $siteUrl . '" style="color:' . $footerLinkColor . '; text-decoration:underline;">pratfall.com.tr</a> · 
                <a href="' . $unsubscribeUrl . '" style="color:' . $unsubscribeColor . '; text-decoration:underline; font-weight:bold;">Abonelikten Ayrıl</a>
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
