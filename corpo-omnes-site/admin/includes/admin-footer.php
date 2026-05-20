  </main>
  <script>

    (function () {
      const burger  = document.getElementById('adminBurger');
      const overlay = document.getElementById('adminOverlay');
      const sidebar = document.getElementById('adminSidebar');
      if (!burger || !sidebar) return;
      function setOpen(state) {
        document.body.classList.toggle('admin-sidebar-open', state);
        burger.setAttribute('aria-expanded', state ? 'true' : 'false');
        overlay.setAttribute('aria-hidden', state ? 'false' : 'true');
      }
      burger.addEventListener('click',  () => setOpen(!document.body.classList.contains('admin-sidebar-open')));
      overlay.addEventListener('click', () => setOpen(false));

      sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setOpen(false)));

      window.addEventListener('resize', () => {
        if (window.innerWidth > 980 && document.body.classList.contains('admin-sidebar-open')) setOpen(false);
      });

      document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });
    })();
  </script>
  <?php
    $dateFrJs = __DIR__ . '/../../js/admin-date-fr.js';
    if (file_exists($dateFrJs)):
  ?>
  <script src="../js/admin-date-fr.js?v=<?= filemtime($dateFrJs) ?>"></script>
  <?php endif; ?>
</body>
</html>
