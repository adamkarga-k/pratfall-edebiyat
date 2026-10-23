/**
 * Pratfall Theme - Genel Tema İşlevleri
 * Gece Modu, Okuma İlerleme Çubuğu, Yazar Eser Filtreleme
 */
document.addEventListener('DOMContentLoaded', function () {
  
  // ------------------------------------------------------------------------
  // 1. GECE / GÜNDÜZ MODU (DARK MODE TOGGLE)
  // ------------------------------------------------------------------------
  const themeToggleBtns = document.querySelectorAll('.theme-toggle-btn');
  const htmlRoot = document.documentElement;

  // Kayıtlı tercihi veya sistem tercihini kontrol et
  const savedTheme = localStorage.getItem('pratfall_theme');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

  if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
    htmlRoot.classList.add('dark');
    updateToggleButtons(true);
  } else {
    htmlRoot.classList.remove('dark');
    updateToggleButtons(false);
  }

  function updateToggleButtons(isDark) {
    themeToggleBtns.forEach(btn => {
      btn.innerHTML = isDark ? '☀ Açık Mod' : '🌙 Gece Modu';
      btn.setAttribute('aria-label', isDark ? 'Açık renge geç' : 'Karanlık renge geç');
    });
  }

  themeToggleBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      const isCurrentlyDark = htmlRoot.classList.toggle('dark');
      localStorage.setItem('pratfall_theme', isCurrentlyDark ? 'dark' : 'light');
      updateToggleButtons(isCurrentlyDark);
    });
  });

  // ------------------------------------------------------------------------
  // 2. OKUMA İLERLEME ÇUBUĞU (READING PROGRESS BAR)
  // ------------------------------------------------------------------------
  const progressBar = document.querySelector('.reading-progress-bar');
  if (progressBar) {
    window.addEventListener('scroll', function () {
      const winScroll = document.documentElement.scrollTop || document.body.scrollTop;
      const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
      const scrolled = height > 0 ? (winScroll / height) * 100 : 0;
      progressBar.style.width = scrolled + '%';
    }, { passive: true });
  }

  // ------------------------------------------------------------------------
  // 3. YAZAR ESERLERİ FİLTRELEME (AUTHOR.PHP CATEGORY TABS)
  // ------------------------------------------------------------------------
  const filterButtons = document.querySelectorAll('.author-filter-btn');
  const workCards = document.querySelectorAll('.author-work-item');

  if (filterButtons.length > 0 && workCards.length > 0) {
    filterButtons.forEach(btn => {
      btn.addEventListener('click', function () {
        const filterCategory = this.getAttribute('data-filter');

        // Buton aktiflik durumunu güncelle
        filterButtons.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        // Kartları filtrele
        workCards.forEach(card => {
          const cardCategories = (card.getAttribute('data-categories') || '').split(',');
          if (filterCategory === 'all' || cardCategories.includes(filterCategory)) {
            card.style.display = 'flex';
            card.style.animation = 'fadeInCard 0.3s ease';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });
  }

  // ------------------------------------------------------------------------
  // 4. MOBİL MENÜ TOGGLE & GELİŞMİŞ ERİŞİLEBİLİRLİK
  // ------------------------------------------------------------------------
  const mobileMenuBtn = document.querySelector('.mobile-menu-toggle');
  const navBar = document.querySelector('.pratfall-nav-bar');

  if (mobileMenuBtn && navBar) {
    mobileMenuBtn.addEventListener('click', function () {
      const isOpen = navBar.classList.toggle('is-open');
      mobileMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      const iconSpan = mobileMenuBtn.querySelector('.menu-icon');
      const labelSpan = mobileMenuBtn.querySelector('.menu-label');
      if (iconSpan) iconSpan.textContent = isOpen ? '✕' : '☰';
      if (labelSpan) labelSpan.textContent = isOpen ? 'Kapat' : 'Menü';
    });

    // Mobil menüdeki bir linke tıklandığında menüyü kapat
    const navLinks = navBar.querySelectorAll('.pratfall-menu a');
    navLinks.forEach(link => {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 768 && navBar.classList.contains('is-open')) {
          navBar.classList.remove('is-open');
          mobileMenuBtn.setAttribute('aria-expanded', 'false');
          const iconSpan = mobileMenuBtn.querySelector('.menu-icon');
          const labelSpan = mobileMenuBtn.querySelector('.menu-label');
          if (iconSpan) iconSpan.textContent = '☰';
          if (labelSpan) labelSpan.textContent = 'Menü';
        }
      });
    });
  }
});
