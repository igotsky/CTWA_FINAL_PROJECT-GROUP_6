<?php
// includes/footer.php
?>
        </main>
<?php if (isLoggedIn()): ?>
        <footer class="site-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></p>
        </footer>
    </div>
</div>
<?php else: ?>
        </main>
    </div>
</div>
<?php endif; ?>
<script>
  window.addEventListener('DOMContentLoaded', function() {
    if (window.lucide) lucide.createIcons();
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('.password-toggle') : null;
      if (!btn) return;
      var input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      var showIcon = btn.querySelector('.eye-show');
      var hideIcon = btn.querySelector('.eye-hide');
      if (showIcon) showIcon.style.display = show ? 'none' : '';
      if (hideIcon) hideIcon.style.display = show ? '' : 'none';
    });
  });
  (function() {
    var btn = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    if (btn && sidebar) {
      btn.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    }
  })();
</script>
</body>
</html>
