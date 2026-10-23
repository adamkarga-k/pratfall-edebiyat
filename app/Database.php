<?php
/**
 * Pratfall Standalone - Veritabanı Yöneticisi (SQLite PDO)
 * 
 * Sunucuda MySQL veritabanı kurmaya gerek kalmadan,
 * tek bir SQLite dosyasında ultra hafif ve hızlı çalışır.
 * Tabloları ve varsayılan içerikleri ilk çalıştırmada otomatik üretir.
 */

class Database {

    private static ?PDO $pdo = null;

    /**
     * PDO Bağlantısını Getir (Singleton)
     */
    public static function get(): PDO {
        if ( self::$pdo === null ) {
            $is_new = ! file_exists( DB_FILE );

            self::$pdo = new PDO( 'sqlite:' . DB_FILE );
            self::$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            self::$pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
            
            // SQLite Hız ve Bütünlük Optimizasyonları (Güvenli Hata Yakalamayla)
            try {
                self::$pdo->exec( 'PRAGMA journal_mode = WAL;' );
            } catch ( Throwable $e ) {
                try { self::$pdo->exec( 'PRAGMA journal_mode = DELETE;' ); } catch ( Throwable $t ) {}
            }
            try {
                self::$pdo->exec( 'PRAGMA foreign_keys = ON;' );
            } catch ( Throwable $e ) {}

            if ( $is_new || self::needs_schema() ) {
                self::init_schema();
            }

            // Vitrinin boş kalmaması için ilk yazıları kontrol et
            self::ensure_initial_posts();
        }
        return self::$pdo;
    }

    /**
     * Tablolar Var mı Kontrolü
     */
    private static function needs_schema(): bool {
        $stmt = self::$pdo->query( "SELECT name FROM sqlite_master WHERE type='table' AND name='posts'" );
        return empty( $stmt->fetch() );
    }

    /**
     * Veritabanı Şemasını ve Tabloları Oluştur
     */
    private static function init_schema(): void {
        $db = self::$pdo;

        // 1. Kullanıcılar (Adminler)
        $db->exec( "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                email TEXT,
                role TEXT DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        " );

        // 2. Yazarlar
        $db->exec( "
            CREATE TABLE IF NOT EXISTS authors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                title TEXT,
                quote TEXT,
                bio TEXT,
                photo TEXT,
                twitter TEXT,
                instagram TEXT,
                website TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        " );

        // 3. Yazılar (Eserler)
        $db->exec( "
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                author_id INTEGER,
                title TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                category TEXT DEFAULT 'Deneme',
                excerpt TEXT,
                content TEXT,
                featured_image TEXT,
                reading_time INTEGER DEFAULT 5,
                status TEXT DEFAULT 'published',
                published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL
            );
        " );

        // İndeksler (Hızlı arama ve SEO için)
        $db->exec( "CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);" );
        $db->exec( "CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status);" );
        $db->exec( "CREATE INDEX IF NOT EXISTS idx_authors_slug ON authors(slug);" );

        // 4. Dergiler (Yatay Vitrin)
        $db->exec( "
            CREATE TABLE IF NOT EXISTS magazines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                issue_no TEXT NOT NULL,
                title TEXT NOT NULL,
                period TEXT,
                price TEXT,
                cover_image TEXT,
                buy_url TEXT,
                order_num INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        " );

        // 5. Site Ayarları
        $db->exec( "
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            );
        " );

        // Başlangıç Verilerini Ekle
        self::seed_data();
    }

    /**
     * Başlangıç / Demo Verilerini Yükle
     */
    public static function ensure_initial_posts(): void {
        try {
            $count = (int) self::$pdo->query( "SELECT COUNT(*) FROM posts" )->fetchColumn();
            if ( $count === 0 ) {
                self::seed_posts();
            }
        } catch ( Throwable $e ) {}
    }

    public static function seed_posts(): void {
        $db = self::$pdo;
        $posts = [
            [
                'author_id'      => 1,
                'title'          => 'Kusursuzluğun İflası: Matbaanın ve Ham Edebiyatın Dirilişi',
                'slug'           => 'kusursuzlugun-iflasi-matbaanin-ve-ham-edebiyatin-dirilisi',
                'category'       => 'DENEME',
                'excerpt'        => 'Mükemmel pürüzsüzlükte tasarlanmış dijital çağın karşısında, kağıdın kusurlu liflerini ve daktilonun atlayan tuşlarını savunuyoruz.',
                'content'        => '<p>Mükemmel pürüzsüzlükte tasarlanmış dijital çağın karşısında, kağıdın kusurlu liflerini ve daktilonun atlayan tuşlarını savunuyoruz.</p><p>Kusursuzluk sanayileşmiş bir yanılsamadır; hakikat ve samimiyet daima çatlaklarda gizlenir. Pratfall, "Kusurlu Kalma Çabası" şiarıyla yola çıkan, matbaa mürekkebinin kokusunu, sokakların dağınıklığını ve kusurlu estetiğin cazibesini bir araya getiren bağımsız bir kültür mecmuasıdır.</p><p>Sözcüklerin kusuru, insanın hakiki yarasına denk gelir. Kusursuz bir dil, henüz acı çekmemiş bir zihnin ezberidir.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=1200&q=80',
                'reading_time'   => 7,
            ],
            [
                'author_id'      => 3,
                'title'          => 'Kelimelerin Tozu: Sahaflarda Unutulan Elyazmaları',
                'slug'           => 'kelimelerin-tozu-sahaflarda-unutulan-elyazmalari',
                'category'       => 'İNCELEME',
                'excerpt'        => 'Eski bir kitabın kenarına kurşun kalemle düşülen şerh, bazen kitabın ana metninden daha derin bir insanlık gerçeğini fısıldar.',
                'content'        => '<p>Eski bir kitabın kenarına kurşun kalemle düşülen şerh, bazen kitabın ana metninden daha derin bir insanlık gerçeğini fısıldar.</p><p>Sahafların tozlu raflarında unutulan her sayfa, geçmişin nefesini taşır. Matbaa mürekkebi kağıda değdiğinde zaman durur gibi olur.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?auto=format&fit=crop&w=1200&q=80',
                'reading_time'   => 5,
            ],
            [
                'author_id'      => 2,
                'title'          => 'Kırık Harflerin Şiiri ve Gece Nöbeti',
                'slug'           => 'kirik-harflerin-siiri-ve-gece-nobeti',
                'category'       => 'ŞİİR',
                'excerpt'        => 'Daktiloda sıkışan "a" harfi, şairin boğazında düğümlenen o kelimenin en somut tanığıdır.',
                'content'        => '<p>Daktiloda sıkışan "a" harfi, şairin boğazında düğümlenen o kelimenin en somut tanığıdır.</p><p>Cam kırıkları arasından gökyüzüne bakmak; bütün olanın yalanına karşılık parçalanmış olanın dürüstlüğüdür.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1516979187457-637abb4f9353?auto=format&fit=crop&w=1200&q=80',
                'reading_time'   => 4,
            ],
            [
                'author_id'      => 4,
                'title'          => 'Olric’e Mektuplar: Beceriksizliğimizin Estetiği',
                'slug'           => 'olrice-mektuplar-beceriksizligimizin-estetigi',
                'category'       => 'ANLATI',
                'excerpt'        => 'Becerikli insanlar her şeyi hallederken ruhlarını da hallediverirler Olric. Biz sakar kalalım.',
                'content'        => '<p>Becerikli insanlar her şeyi hallederken ruhlarını da hallediverirler Olric. Biz sakar kalalım.</p><p>Tutunamamak, düşmekten korkmayanların tek imtiyazıdır.</p>',
                'featured_image' => 'https://images.unsplash.com/photo-1506880018603-83d5b814b5a6?auto=format&fit=crop&w=1200&q=80',
                'reading_time'   => 6,
            ],
        ];

        $stmt = $db->prepare( "
            INSERT OR IGNORE INTO posts (author_id, title, slug, category, excerpt, content, featured_image, reading_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        " );

        foreach ( $posts as $p ) {
            $stmt->execute( [
                $p['author_id'], $p['title'], $p['slug'], $p['category'],
                $p['excerpt'], $p['content'], $p['featured_image'], $p['reading_time']
            ] );
        }
    }

    /**
     * Başlangıç / Demo Verilerini Yükle
     */
    private static function seed_data(): void {
        $db = self::$pdo;

        // İlk Kurulum Yönetici Hesabı (Varsayılan geçici şifre)
        $admin_pass = password_hash( 'changeme123', PASSWORD_BCRYPT );
        $stmt = $db->prepare( "INSERT OR IGNORE INTO users (username, password_hash, email, role) VALUES ('admin', ?, 'admin@localhost', 'admin')" );
        $stmt->execute( [ $admin_pass ] );

        // Varsayılan Site Ayarları
        $defaults = [
            'site_name'             => 'PRATFALL',
            'site_slogan'           => 'Kusurlu Kalma Çabası',
            'site_location_badge'   => '✦ İSTANBUL · KÜLTÜR & MATBAA',
            'site_issue_badge'      => 'CİLT I · SAYI 18',
            'site_press_badge'      => 'BAĞIMSIZ BASIN',
            'ticker_1'              => 'PRATFALL: Kusurlu Kalma Çabası · Bağımsız kültür & sanat yayını.',
            'ticker_2'              => 'YENİ SAYI: Son sayımız matbaadan çıktı! Hemen vitrinden inceleyip sipariş verebilirsiniz.',
            'ticker_3'              => 'SLOGAN: "Kusurlu Kalma Çabası" — Mükemmellik yanılsamasına karşı ham edebiyat.',
            'ticker_4'              => 'EDİTORYAL: "Kusursuzluk sanayileşmiş bir yanılsamadır; hakikat daima çatlaklarda gizlenir."',
            'authors_badge'         => '✦ KİMLER YAZMIŞ?',
            'authors_title'         => 'Yazarlar Masası',
            'authors_desc'          => 'Burası sadece yoklama listesi.',
            'feed_badge'            => 'YENİ METİNLER',
            'feed_title'            => 'Son Eserler & Yazılar',
            'manifesto_title'       => 'Pratfall Ne Demek?',
            'manifesto_badge'       => '✦ EDİTORYAL MANİFESTO',
            'manifesto_tag'         => 'ELLIOT ARONSON · 1966',
            'manifesto_p1'          => '1966 yılında sosyal psikolog Elliot Aronson tarafından keşfedilen Pratfall Etkisi (Sakarlık/Kusur Etkisi), kusursuzluğun soğuk bir yabancılaşma ürettiğini, küçük tökezlemelerin ise hakiki bir yakınlık doğurduğunu kanıtladı. Deneyde, soruları hatasız yanıtlayan parlak bir adayın üzerine kahve döküp sakarlık yapması, onu izleyicilerin gözünde çok daha sevimli, samimi ve sahici kılmıştır. Mükemmel olan yapay ve mesafelidir; kusurlarını saklamayan ise bizden biridir.',
            'manifesto_p2'          => 'Pratfall Mecmua, adını bu sarsıcı hakikatten alır. Dijital çağın yapay pürüzsüzlüğüne ve steril estetiğine karşı; silinip yeniden yazılan satırların tereddüdünü, dökülen kahvenin lekesini, matbaa mürekkebinin bulaşıklığını ve insan olmanın o kırılgan sakarlıklarını savunuyoruz. Çünkü hakikat daima çatlaklarda gizlenir. Bizim edebiyatımız, tek bir direniş cümlesinden ibarettir: "Kusurlu Kalma Çabası".',
            'footer_slogan'         => '✦ Kusurlu Kalma Çabası',
            'footer_desc'           => 'Kusursuzluk sanayileşmiş bir yanılsamadır; hakikat ve samimiyet daima çatlaklarda gizlenir. Pratfall, "Kusurlu Kalma Çabası" şiarıyla yola çıkan, matbaa mürekkebinin kokusunu, sokakların dağınıklığını ve kusurlu estetiğin cazibesini bir araya getiren bağımsız bir kültür mecmuasıdır.',
            'footer_newsletter_title'=> 'Matbaa Bülteni',
            'footer_newsletter_desc' => 'Yeni sayılar matbaaya girdiğinde ve yeni eserler yayınlandığında haberdar olun.',
            'footer_copyright'      => 'Tüm hakları yazarlara ve mecmuaya aittir.',
        ];

        $stmt = $db->prepare( "INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)" );
        foreach ( $defaults as $k => $v ) {
            $stmt->execute( [ $k, $v ] );
        }

        // Demo Yazarlar
        $authors = [
            [
                'name'      => 'Mecmua Editörü',
                'slug'      => 'editor',
                'title'     => 'Yayın Kurulu & Deneme',
                'quote'     => 'Sözcüklerin kusuru, insanın hakiki yarasına denk gelir. Kusursuz bir dil, henüz acı çekmemiş bir zihnin ezberidir.',
                'bio'       => 'Mecmuamızın bağımsız yazarı. Edebiyat, felsefe, matbaa ve kültür yazılarıyla mecmua sayfalarında yer almaktadır.',
                'photo'     => '',
                'twitter'   => '',
                'instagram' => '',
                'website'   => '',
            ],
            [
                'name'      => 'Nilgün Marmara',
                'slug'      => 'nilgun-marmara',
                'title'     => 'Şair & Eleştirmen',
                'quote'     => 'Cam kırıkları arasından gökyüzüne bakmak; bütün olanın yalanına karşılık parçalanmış olanın dürüstlüğüdür.',
                'bio'       => 'Şiir ve varoluş fragmanları üzerine metinler.',
                'photo'     => '', // Fotoğrafsız (Monogram Kaşe)
                'twitter'   => '',
                'instagram' => '',
                'website'   => '',
            ],
            [
                'name'      => 'İlhan Berk',
                'slug'      => 'ilhan-berk',
                'title'     => 'Görsel Şiir & Fragmanlar',
                'quote'     => 'Kelimelerin tozu, eski sahafların loş koridorlarında en güzel şiir gibi asılı kalır.',
                'bio'       => 'Sokakların, daktilo harflerinin ve görsel tipografinin izinde.',
                'photo'     => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80',
                'twitter'   => '',
                'instagram' => '',
                'website'   => '',
            ],
            [
                'name'      => 'Oğuz Atay',
                'slug'      => 'oguz-atay',
                'title'     => 'Roman & İroni Araştırmaları',
                'quote'     => 'Beceriksizliğimiz bizim tek sığınağımızdır Olric. Becerikli insanlar her şeyi hallederken ruhlarını da hallediverirler.',
                'bio'       => 'Tutunamayanların ve ironinin edebi sözlüğü.',
                'photo'     => '', // Fotoğrafsız (Monogram Kaşe)
                'twitter'   => '',
                'instagram' => '',
                'website'   => '',
            ],
        ];

        $stmt = $db->prepare( "
            INSERT OR IGNORE INTO authors (name, slug, title, quote, bio, photo, twitter, instagram, website)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        " );

        foreach ( $authors as $a ) {
            $stmt->execute( [
                $a['name'], $a['slug'], $a['title'], $a['quote'],
                $a['bio'], $a['photo'], $a['twitter'], $a['instagram'], $a['website']
            ] );
        }

        // Demo Dergiler
        $magazines = [
            [ 'Sayı #18', 'Kusursuzluğun İflası', 'Güz / Kış 2026', '120 ₺', 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=500&q=80', '#', 1 ],
            [ 'Sayı #17', 'Çatlaklardan Sızan Şiir', 'İlkbahar / Yaz 2026', '110 ₺', 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=500&q=80', '#', 2 ],
            [ 'Sayı #16', 'Daktilo ve Sokak Fanzinleri', 'Kış 2025', '95 ₺', 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?auto=format&fit=crop&w=500&q=80', '#', 3 ],
            [ 'Sayı #15', 'Matbaanın ve Mürekkebin Külü', 'Güz 2025', '95 ₺', 'https://images.unsplash.com/photo-1516979187457-637abb4f9353?auto=format&fit=crop&w=500&q=80', '#', 4 ],
            [ 'Sayı #14', 'Kelimelerin Anatomisi', 'Yaz 2025', '85 ₺', 'https://images.unsplash.com/photo-1506880018603-83d5b814b5a6?auto=format&fit=crop&w=500&q=80', '#', 5 ],
            [ 'Sayı #13', 'Gece Vardiyası Metinleri', 'İlkbahar 2025', '85 ₺', 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?auto=format&fit=crop&w=500&q=80', '#', 6 ],
        ];

        $stmt = $db->prepare( "
            INSERT OR IGNORE INTO magazines (issue_no, title, period, price, cover_image, buy_url, order_num)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        " );

        foreach ( $magazines as $m ) {
            $stmt->execute( $m );
        }

        // Demo Yazıları Yükle
        self::seed_posts();
    }

    /**
     * Site Ayarını Getir
     */
    public static function get_setting( string $key, string $default = '' ): string {
        $stmt = self::get()->prepare( "SELECT value FROM settings WHERE key = ?" );
        $stmt->execute( [ $key ] );
        $row = $stmt->fetch();
        return $row ? (string) $row['value'] : $default;
    }

    /**
     * Site Ayarını Güncelle
     */
    public static function set_setting( string $key, string $value ): void {
        $stmt = self::get()->prepare( "INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)" );
        $stmt->execute( [ $key, $value ] );
    }

    /**
     * Güvenli Slug Üretici (Türkçe Karakter Uyumlu)
     */
    public static function slugify( string $text ): string {
        $turkish = [ 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç' ];
        $latin   = [ 'i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c' ];
        $text = str_replace( $turkish, $latin, $text );
        $text = preg_replace( '~[^\pL\d]+~u', '-', $text );
        $text = iconv( 'utf-8', 'us-ascii//TRANSLIT', $text );
        $text = preg_replace( '~[^-\w]+~', '', $text );
        $text = trim( $text, '-' );
        $text = preg_replace( '~-+~', '-', $text );
        $text = strtolower( $text );
        return empty( $text ) ? 'n-a-' . time() : $text;
    }
}
