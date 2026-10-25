<?php
/**
 * Pratfall Standalone - SEO & Meta Veri Motoru
 * 
 * Google ve arama motorları için %100 uyumlu:
 * - JSON-LD Schema.org Yapısal Verileri (Article, Person, Organization)
 * - OpenGraph & Twitter Card Sosyal Medya Önizlemeleri
 * - Dinamik XML Sitemap ve Robots.txt
 * - Otomatik Kanonik (Canonical) URL'ler
 */

class Seo {

    /**
     * Sayfa Tipine Göre Tüm Meta Etiketlerini HTML Olarak Basar
     */
    public static function render_head( array $page_data = [] ): string {
        $site_name = trim( str_ireplace( 'Mecmua', '', Database::get_setting( 'site_name', 'PRATFALL' ) ) );
        if ( empty( $site_name ) ) {
            $site_name = 'PRATFALL';
        }
        $site_slogan = trim( str_ireplace( 'Mecmua', 'Edebiyat Dergisi & Platformu', Database::get_setting( 'site_slogan', 'Kusurlu Kalma Çabası' ) ) );
        $raw_slogan  = trim( Database::get_setting( 'site_slogan', 'Kusurlu Kalma Çabası' ) );

        $type        = $page_data['type'] ?? 'website';
        if ( ! empty( $page_data['is_home'] ) || empty( $page_data['title'] ) || $page_data['title'] === $site_slogan || $page_data['title'] === $raw_slogan ) {
            // Önce dergi adı, sonra slogan
            $title = $site_name . ' ✦ ' . $site_slogan;
        } else {
            $title = $page_data['title'] . ' ✦ ' . $site_name;
        }
        $description = $page_data['description'] ?? Database::get_setting( 'footer_desc', 'Kusursuzluk sanayileşmiş bir yanılsamadır; hakikat daima çatlaklarda gizlenir. Edebiyat dergisi ve yayınevinin bağımsız edebiyat platformu.' );
        $description = str_ireplace( [ 'kültür mecmuasıdır', 'Pratfall Mecmua', 'mecmua' ], [ 'edebiyat dergisi ve yayınevinin bağımsız edebiyat platformudur', 'Pratfall', 'edebiyat platformu' ], $description );
        $url         = $page_data['url'] ?? SITE_URL;
        $image       = ! empty( $page_data['image'] ) ? $page_data['image'] : SITE_URL . '/assets/images/og-share.jpg';

        $description = htmlspecialchars( mb_substr( strip_tags( $description ), 0, 160 ), ENT_QUOTES, 'UTF-8' );
        $title       = htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' );

        $html = [];
        $html[] = "<title>{$title}</title>";
        $html[] = "<meta name=\"description\" content=\"{$description}\" />";
        $html[] = "<link rel=\"canonical\" href=\"{$url}\" />";
        $html[] = "<meta name=\"robots\" content=\"index, follow, max-image-preview:large\" />";

        // Open Graph (Facebook, WhatsApp, LinkedIn)
        $html[] = "<meta property=\"og:locale\" content=\"tr_TR\" />";
        $html[] = "<meta property=\"og:site_name\" content=\"{$site_name}\" />";
        $html[] = "<meta property=\"og:type\" content=\"{$type}\" />";
        $html[] = "<meta property=\"og:title\" content=\"{$title}\" />";
        $html[] = "<meta property=\"og:description\" content=\"{$description}\" />";
        $html[] = "<meta property=\"og:url\" content=\"{$url}\" />";
        if ( ! empty( $image ) ) {
            $html[] = "<meta property=\"og:image\" content=\"{$image}\" />";
        }

        // Twitter Cards
        $html[] = "<meta name=\"twitter:card\" content=\"summary_large_image\" />";
        $html[] = "<meta name=\"twitter:title\" content=\"{$title}\" />";
        $html[] = "<meta name=\"twitter:description\" content=\"{$description}\" />";
        if ( ! empty( $image ) ) {
            $html[] = "<meta name=\"twitter:image\" content=\"{$image}\" />";
        }

        // RSS & Atom Edebi Akış Keşfi
        $html[] = '<link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars( $site_name . ' ✦ Edebi Akış (RSS 2.0)', ENT_QUOTES, 'UTF-8' ) . '" href="' . SITE_URL . '/rss.xml" />';
        $html[] = '<link rel="alternate" type="application/atom+xml" title="' . htmlspecialchars( $site_name . ' ✦ Edebi Akış (Atom)', ENT_QUOTES, 'UTF-8' ) . '" href="' . SITE_URL . '/feed" />';

        // JSON-LD Schema.org Yapısal Veri
        $schema = self::build_schema( $page_data, $site_name, $site_slogan, $url, $title, $description, $image );
        if ( ! empty( $schema ) ) {
            $html[] = '<script type="application/ld+json">' . json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>';
        }

        return implode( "\n  ", $html );
    }

    /**
     * Schema.org JSON-LD Yapısı Oluştur
     */
    private static function build_schema( array $page_data, string $site_name, string $slogan, string $url, string $title, string $desc, string $image ): array {
        $type = $page_data['type'] ?? 'website';

        if ( $type === 'article' ) {
            return [
                '@context'         => 'https://schema.org',
                '@type'            => 'Article',
                'headline'         => $page_data['title'] ?? '',
                'description'      => $desc,
                'image'            => $image,
                'datePublished'    => $page_data['published_at'] ?? date( 'c' ),
                'dateModified'     => $page_data['updated_at'] ?? date( 'c' ),
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id'   => $url,
                ],
                'author'           => [
                    '@type' => 'Person',
                    'name'  => $page_data['author_name'] ?? 'Pratfall Yazarı',
                    'url'   => $page_data['author_url'] ?? SITE_URL,
                ],
                'publisher'        => [
                    '@type' => 'Organization',
                    'name'  => $site_name,
                    'url'   => SITE_URL,
                ],
            ];
        }

        if ( $type === 'profile' ) {
            return [
                '@context'    => 'https://schema.org',
                '@type'       => 'ProfilePage',
                'mainEntity'  => [
                    '@type'       => 'Person',
                    'name'        => $page_data['author_name'] ?? '',
                    'description' => $desc,
                    'jobTitle'    => $page_data['author_title'] ?? 'Yazar',
                    'url'         => $url,
                ],
            ];
        }

        // Ana Sayfa için WebSite & Periodical
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => $site_name,
            'alternateName'   => $slogan,
            'url'             => SITE_URL,
            'description'     => $desc,
            'inLanguage'      => 'tr-TR',
        ];
    }

    /**
     * Dinamik XML Sitemap Üretir (/sitemap.xml)
     */
    public static function generate_sitemap(): void {
        header( 'Content-Type: application/xml; charset=utf-8' );
        $db = Database::get();

        $posts = $db->query( "SELECT slug, updated_at, published_at FROM posts WHERE status = 'published' ORDER BY published_at DESC" )->fetchAll();
        $authors = $db->query( "SELECT slug, created_at FROM authors ORDER BY name ASC" )->fetchAll();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Ana Sayfa
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars( SITE_URL, ENT_XML1 ) . "/</loc>\n";
        echo "    <changefreq>daily</changefreq>\n";
        echo "    <priority>1.0</priority>\n";
        echo "  </url>\n";

        // Dergiler Sayfası
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars( SITE_URL . '/dergiler', ENT_XML1 ) . "</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";

        // Kitaplık Sayfası
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars( SITE_URL . '/kitaplik', ENT_XML1 ) . "</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";

        // Tüm Yazarlarımız Sayfası
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars( SITE_URL . '/yazarlar', ENT_XML1 ) . "</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";

        // Kategoriler
        $cats = $db->query( "SELECT slug FROM categories" )->fetchAll( PDO::FETCH_COLUMN );
        foreach ( $cats as $c_slug ) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars( SITE_URL . '/kategori/' . $c_slug, ENT_XML1 ) . "</loc>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.7</priority>\n";
            echo "  </url>\n";
        }

        // Yazılar
        foreach ( $posts as $p ) {
            $loc = SITE_URL . '/eser/' . $p['slug'];
            $date = date( 'Y-m-d', strtotime( $p['updated_at'] ?? $p['published_at'] ) );
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars( $loc, ENT_XML1 ) . "</loc>\n";
            echo "    <lastmod>{$date}</lastmod>\n";
            echo "    <changefreq>monthly</changefreq>\n";
            echo "    <priority>0.9</priority>\n";
            echo "  </url>\n";
        }

        // Yazarlar & Şairler
        foreach ( $authors as $a ) {
            $loc = Helpers::author_url( $a );
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars( $loc, ENT_XML1 ) . "</loc>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.7</priority>\n";
            echo "  </url>\n";
        }

        echo '</urlset>';
        exit;
    }

    /**
     * Dinamik Robots.txt Üretir
     */
    public static function generate_robots(): void {
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin/\n";
        echo "Disallow: /data/\n";
        echo "\nSitemap: " . SITE_URL . "/sitemap.xml\n";
        echo "RSS: " . SITE_URL . "/rss.xml\n";
        exit;
    }

    /**
     * Dinamik RSS 2.0 & Atom Edebi Akışı Üretir (/rss.xml, /feed)
     */
    public static function generate_rss(): void {
        header( 'Content-Type: application/rss+xml; charset=utf-8' );
        $db = Database::get();

        $site_name = trim( str_ireplace( 'Mecmua', '', Database::get_setting( 'site_name', 'PRATFALL' ) ) );
        if ( empty( $site_name ) ) {
            $site_name = 'PRATFALL';
        }
        $site_slogan = trim( str_ireplace( 'Mecmua', 'Edebiyat Dergisi & Platformu', Database::get_setting( 'site_slogan', 'Kusurlu Kalma Çabası' ) ) );
        $desc = Database::get_setting( 'footer_desc', 'Kusursuzluk sanayileşmiş bir yanılsamadır; hakikat daima çatlaklarda gizlenir. Bağımsız edebiyat platformu.' );

        $stmt = $db->query( "
            SELECT p.*, a.name AS author_name, a.title AS author_title, a.photo AS author_photo
            FROM posts p
            LEFT JOIN authors a ON p.author_id = a.id
            WHERE p.status = 'published'
            ORDER BY p.published_at DESC
            LIMIT 50
        " );
        $posts = $stmt->fetchAll();

        $latest_pub_date = ! empty( $posts[0]['published_at'] ) ? date( 'r', strtotime( $posts[0]['published_at'] ) ) : date( 'r' );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
        echo "  <channel>\n";
        echo "    <title><![CDATA[" . $site_name . " ✦ " . $site_slogan . "]]></title>\n";
        echo "    <link>" . htmlspecialchars( SITE_URL, ENT_XML1 ) . "/</link>\n";
        echo "    <description><![CDATA[" . strip_tags( $desc ) . "]]></description>\n";
        echo "    <language>tr</language>\n";
        echo "    <lastBuildDate>" . $latest_pub_date . "</lastBuildDate>\n";
        echo "    <atom:link href=\"" . htmlspecialchars( SITE_URL . '/rss.xml', ENT_XML1 ) . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
        echo "    <generator>Pratfall Standalone Literary Engine</generator>\n";
        echo "    <image>\n";
        echo "      <url>" . htmlspecialchars( SITE_URL . '/assets/images/og-share.jpg', ENT_XML1 ) . "</url>\n";
        echo "      <title><![CDATA[" . $site_name . "]]></title>\n";
        echo "      <link>" . htmlspecialchars( SITE_URL, ENT_XML1 ) . "/</link>\n";
        echo "    </image>\n";

        foreach ( $posts as $p ) {
            $link = SITE_URL . '/eser/' . $p['slug'];
            $pub_date = date( 'r', strtotime( $p['published_at'] ) );
            $author = ! empty( $p['author_name'] ) ? $p['author_name'] : ( Helpers::is_poet( $p ) ? 'Pratfall Şairi' : 'Pratfall Yazarı' );
            $category = $p['category'] ?? 'Edebiyat';
            $excerpt = ! empty( $p['excerpt'] ) ? $p['excerpt'] : mb_substr( strip_tags( $p['content'] ?? '' ), 0, 280 ) . '...';
            
            $is_poem = ( mb_stripos( $category, 'şiir' ) !== false || mb_stripos( $category, 'siir' ) !== false );
            $content_html = $is_poem ? Helpers::format_poem_content( $p['content'] ?? '' ) : ( $p['content'] ?? '' );
            if ( ! empty( $p['featured_image'] ) ) {
                $content_html = '<p><img src="' . htmlspecialchars( $p['featured_image'] ) . '" alt="' . htmlspecialchars( $p['title'] ) . '" style="max-width:100%; height:auto;" /></p>' . $content_html;
            }

            echo "    <item>\n";
            echo "      <title><![CDATA[" . $p['title'] . "]]></title>\n";
            echo "      <link>" . htmlspecialchars( $link, ENT_XML1 ) . "</link>\n";
            echo "      <guid isPermaLink=\"true\">" . htmlspecialchars( $link, ENT_XML1 ) . "</guid>\n";
            echo "      <pubDate>" . $pub_date . "</pubDate>\n";
            echo "      <dc:creator><![CDATA[" . $author . "]]></dc:creator>\n";
            echo "      <category><![CDATA[" . $category . "]]></category>\n";
            echo "      <description><![CDATA[" . $excerpt . "]]></description>\n";
            echo "      <content:encoded><![CDATA[" . $content_html . "]]></content:encoded>\n";
            if ( ! empty( $p['featured_image'] ) ) {
                echo "      <enclosure url=\"" . htmlspecialchars( $p['featured_image'], ENT_XML1 ) . "\" type=\"image/jpeg\" length=\"0\" />\n";
            }
            echo "    </item>\n";
        }

        echo "  </channel>\n";
        echo "</rss>";
        exit;
    }
}
