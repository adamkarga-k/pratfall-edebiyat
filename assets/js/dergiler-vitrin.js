/**
 * Pratfall Theme - Dergilerimiz Yatay Vitrini
 * Tut-Sürükle (Drag-to-Scroll), Ok Butonları ve Akıllı Tıklama Yönetimi
 */
document.addEventListener('DOMContentLoaded', function () {
  const sections = document.querySelectorAll('.pratfall-dergiler-section');

  sections.forEach(function (section) {
    const viewport = section.querySelector('.pratfall-dergiler-viewport');
    const track = section.querySelector('.pratfall-dergiler-track');
    const prevBtn = section.querySelector('.dergi-nav-prev');
    const nextBtn = section.querySelector('.dergi-nav-next');

    if (!viewport || !track) return;

    let isDown = false;
    let startX = 0;
    let scrollLeft = 0;
    let hasDragged = false;
    const DRAG_THRESHOLD = 6; // px - bu mesafeden fazla sürüklenirse tıklama iptal edilir

    // --- Ok Butonları ile Kaydırma ---
    const getScrollStep = () => {
      // Bir veya iki kart genişliği kadar kaydır
      const card = track.querySelector('.dergi-card');
      return card ? card.offsetWidth * 2 + 32 : 360;
    };

    const updateButtonStates = () => {
      if (!prevBtn || !nextBtn) return;
      const maxScroll = viewport.scrollWidth - viewport.clientWidth;
      prevBtn.disabled = viewport.scrollLeft <= 5;
      nextBtn.disabled = viewport.scrollLeft >= maxScroll - 5;
    };

    if (prevBtn) {
      prevBtn.addEventListener('click', function (e) {
        e.preventDefault();
        viewport.scrollBy({ left: -getScrollStep(), behavior: 'smooth' });
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        e.preventDefault();
        viewport.scrollBy({ left: getScrollStep(), behavior: 'smooth' });
      });
    }

    // --- Fare ile Tut-Sürükle (Mouse Drag) ---
    viewport.addEventListener('mousedown', function (e) {
      // Yalnızca sol tık
      if (e.button !== 0) return;
      isDown = true;
      hasDragged = false;
      viewport.classList.add('is-dragging');
      startX = e.pageX - viewport.offsetLeft;
      scrollLeft = viewport.scrollLeft;
    });

    window.addEventListener('mouseup', function (e) {
      if (!isDown) return;
      isDown = false;
      viewport.classList.remove('is-dragging');
    });

    viewport.addEventListener('mousemove', function (e) {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - viewport.offsetLeft;
      const walk = (x - startX) * 1.4; // Kaydırma hızı çarpanı

      if (Math.abs(walk) > DRAG_THRESHOLD) {
        hasDragged = true;
      }

      viewport.scrollLeft = scrollLeft - walk;
      updateButtonStates();
    });

    // --- Dokunmatik Cihazlar (Touch Support & Swipe Threshold) ---
    let touchStartX = 0;
    let touchStartY = 0;

    viewport.addEventListener('touchstart', function (e) {
      if (e.touches.length === 1) {
        touchStartX = e.touches[0].pageX;
        touchStartY = e.touches[0].pageY;
        hasDragged = false;
      }
    }, { passive: true });

    viewport.addEventListener('touchmove', function (e) {
      if (e.touches.length === 1) {
        const deltaX = Math.abs(e.touches[0].pageX - touchStartX);
        const deltaY = Math.abs(e.touches[0].pageY - touchStartY);
        if (deltaX > DRAG_THRESHOLD && deltaX > deltaY) {
          hasDragged = true;
        }
      }
    }, { passive: true });

    viewport.addEventListener('touchend', function () {
      if (hasDragged) {
        setTimeout(function () {
          hasDragged = false;
        }, 120);
      }
    }, { passive: true });

    // Sürükleme yapılmışsa kart içindeki linkin açılmasını önle
    viewport.addEventListener('click', function (e) {
      if (hasDragged) {
        e.preventDefault();
        e.stopPropagation();
        hasDragged = false;
      }
    }, true);

    // Kaydırma olayını dinleyip buton durumlarını güncelle
    viewport.addEventListener('scroll', updateButtonStates, { passive: true });
    window.addEventListener('resize', updateButtonStates);

    // Başlangıç durumu kontrolü
    updateButtonStates();
  });
});
