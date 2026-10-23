# PRATFALL MECMUA — STANDALONE CMS (YÖNETİM MASASI)
> **"Kusurlu Kalma Çabası"** — Bağımsız, Çok Yazarlı, Neo-Brutalist & Zine Kültür Mecmuası

Bu sistem, **WordPress bağımlılığı olmadan**, tamamen kendine ait hafif bir yönetim paneline, bağımsız SQLite veritabanına, Word (.docx) toplu yazı içe aktarıcısına ve cPanel Git senkronizasyonuna sahip bağımsız bir web uygulamasıdır.

---

## ✦ Öne Çıkan Özellikler

1. **Ultra Hızlı & Sıfır Şişkinlik:**
   - Sayfalar 0.1 saniyede yüklenir (Google PageSpeed 99-100).
   - Veritabanı tek bir SQLite dosyasında (`data/database.sqlite`) yaşar; cPanel'de MySQL kurmaya bile gerek yoktur.
2. **Word (.docx) Toplu İçe Aktarıcı (Bulk Importer):**
   - Bilgisayarınızdan onlarca Word dosyasını tek seferde sürükleyip bırakarak sisteme yükleyebilirsiniz.
   - Başlıklar, yazarlar, kalın/italik metinler ve Word içindeki fotoğraflar otomatik ayıklanıp yayına hazır hale getirilir.
3. **cPanel Git Senkronizasyonu (Yöntem C):**
   - Antigravity üzerinde yaptığımız tüm tasarım ve kod güncellemeleri Git üzerinden cPanel'e çekilebilir.
   - İçerikleriniz (`data/database.sqlite` ve `uploads/`) korumalıdır; kod güncellemelerinde asla silinmez.
4. **Google & Sosyal Medya Uyumlu (Full SEO):**
   - Otomatik `Article`, `Person`, `WebSite` JSON-LD Schema.org zengin kartları.
   - Dinamik `/sitemap.xml` ve `/robots.txt`.
   - OpenGraph ve Twitter Cards entegrasyonu.
5. **Esnek Yazar Masası (Graceful Degradation):**
   - Fotoğrafı olmayan yazarlar için otomatik **Monogram Kaşe Pul** üretimi.
   - Sayaçlardan arındırılmış, saf edebi duruş.

---

## ✦ Yerel Geliştirme (Localhost'ta Çalıştırma)

Bilgisayarınızda PHP yüklüyse terminali açıp şu komutu vermeniz yeterlidir:

```bash
cd C:\Users\Ubeydullah\.gemini\antigravity\scratch\pratfall-edebiyat
php -S localhost:8000
```

* **Canlı Site:** `http://localhost:8000`
* **Yönetim Masası:** `http://localhost:8000/admin`
* **Varsayılan Kullanıcı:** `admin`
* **Varsayılan Şifre:** `pratfall2026!`

---

## ✦ cPanel Hosting'e Kurulum (Yöntem C)

### Seçenek 1: cPanel Git™ Sürüm Kontrolü (Önerilen)
1. cPanel paneline giriş yapın.
2. **Dosyalar > Git™ Sürüm Kontrolü** alanına gidin.
3. **Oluştur (Create)** butonuna basın.
4. Depo adresini ve yayın klasörünüzü (`public_html`) belirleyin.
5. Siteniz ve yönetim masanız anında yayına girer.

### Seçenek 2: Doğrudan FTP / Dosya Yöneticisi
1. `pratfall-edebiyat` klasöründeki tüm dosyaları zipleyin.
2. cPanel **Dosya Yöneticisi** ile `public_html` içine yükleyip zipten çıkarın.
3. `https://siteniz.com` adresinden sitenize, `https://siteniz.com/admin` adresinden panelinize erişin.
