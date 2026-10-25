    </main><!-- .admin-content-container -->
  </div><!-- .admin-main-area -->
</div><!-- .admin-app-wrapper -->

<!-- Mobil Menü JavaScript Kontrolcüsü -->
<script>
function toggleAdminSidebar(show) {
  var sb = document.getElementById('adminSidebar') || document.querySelector('.admin-sidebar');
  var bd = document.getElementById('sidebarBackdrop');
  if (!sb) return;
  var isOpen = sb.classList.contains('is-open');
  if (typeof show !== 'boolean') show = !isOpen;
  if (show) {
    sb.classList.add('is-open');
    if (bd) bd.classList.add('is-active');
    document.body.style.overflow = 'hidden';
  } else {
    sb.classList.remove('is-open');
    if (bd) bd.classList.remove('is-active');
    document.body.style.overflow = '';
  }
}

// ESC tuşuna basıldığında menüyü kapat
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') toggleAdminSidebar(false);
});

// Ekran genişliği büyüdüğünde mobil menü taşmasını temizle
window.addEventListener('resize', function() {
  if (window.innerWidth > 991) {
    toggleAdminSidebar(false);
  }
});
</script>

</body>
</html>
