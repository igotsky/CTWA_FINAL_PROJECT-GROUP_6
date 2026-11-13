<?php
// login.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$invalidUser = false;
$invalidPass = false;
$redirectUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
        if ($username === '') $invalidUser = true;
        if ($password === '') $invalidPass = true;
    } else {
        $user = loginUser($conn, $username, $password);
        if ($user) {
            $redirectUrl = ($user['role'] === 'admin')
                ? BASE_URL . 'admin/dashboard.php'
                : BASE_URL . 'student/dashboard.php';
        } else {
            $error = 'Invalid username or password.';
            $invalidUser = true;
            $invalidPass = true;
        }
    }
} else {
    $username = '';
}

if ($redirectUrl !== null) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logging in...</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
  <meta http-equiv="refresh" content="1;url=<?php echo $redirectUrl; ?>">
</head>
<body>
  <div class="loading-overlay active">
    <div class="loading-spinner"></div>
    <div class="loading-text">Logging in...</div>
  </div>
  <script>setTimeout(function(){window.location.href='<?php echo $redirectUrl; ?>';},900);</script>
</body>
</html>
    <?php
    exit;
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>
<?php if (isset($_GET['forgot']) && isset($_GET['msg'])): ?>
<script>
  (function(){
    var t = <?php echo json_encode($_GET['type'] ?? 'success'); ?>;
    showMessage(<?php echo json_encode($_GET['msg']); ?>, t);
  })();
</script>
<?php endif; ?>
    <h1 style="text-align:center;">Login</h1>
    <?php if ($error !== ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <label>Student Number</label>
        <input type="text" name="username" required class="<?php echo $invalidUser ? 'invalid' : ''; ?>" value="<?php echo htmlspecialchars($username ?? ''); ?>">
        <label>Password</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="pwdLogin" required class="<?php echo $invalidPass ? 'invalid' : ''; ?>">
            <button type="button" class="password-toggle" data-target="pwdLogin" aria-label="Toggle password visibility">
                <i data-lucide="eye" class="eye-show"></i>
                <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
            </button>
        </div>
        <input type="submit" value="Login">
    </form>
    <p style="text-align:center; color:var(--text-secondary); font-size:0.85rem; margin-top:8px;">
      <a href="#" id="forgotLink">Forgot password?</a>
    </p>
    <p style="text-align:center; color:var(--text-secondary);">No account? <a href="<?php echo BASE_URL; ?>register.php" style="color:var(--text-primary); text-decoration:underline;">Register here</a>.</p>

    <!-- Step 1: Student number -->
    <div id="fpStep1" class="msg-overlay" style="display:none;">
      <div class="msg-box">
        <div class="msg-text" style="margin-bottom:12px; font-weight:600;">Reset your password</div>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin:0 0 12px;">Enter your student number to continue.</p>
        <input type="text" id="fpStudentNumber" placeholder="Student Number" style="width:100%; margin-bottom:8px;">
        <div id="fpStep1Err" style="display:none; color:#e74c3c; font-size:0.85rem; margin-bottom:8px;"></div>
        <div style="display:flex; gap:8px; justify-content:center;">
          <button type="button" class="msg-ok" style="background:#fff; color:var(--text-primary); border:1px solid var(--border);" onclick="fpCloseAll()">Cancel</button>
          <button type="button" class="msg-ok" id="fpStep1Submit">Submit</button>
        </div>
      </div>
    </div>

    <!-- Step 2: 6-digit code -->
    <div id="fpStep2" class="msg-overlay" style="display:none;">
      <div class="msg-box">
        <div class="msg-text" style="margin-bottom:12px; font-weight:600;">Enter verification code</div>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin:0 0 12px;">A 6-digit code was sent to your email.</p>
        <div style="display:flex; gap:8px; justify-content:center; margin-bottom:8px;">
          <input type="text" inputmode="numeric" maxlength="1" class="code-box">
          <input type="text" inputmode="numeric" maxlength="1" class="code-box">
          <input type="text" inputmode="numeric" maxlength="1" class="code-box">
          <input type="text" inputmode="numeric" maxlength="1" class="code-box">
          <input type="text" inputmode="numeric" maxlength="1" class="code-box">
          <input type="text" inputmode="numeric" maxlength="1" class="code-box">
        </div>
        <div id="fpStep2Err" style="display:none; color:#e74c3c; font-size:0.85rem; margin:8px 0;"></div>
        <div style="display:flex; gap:8px; justify-content:center; margin-top:12px;">
          <button type="button" class="msg-ok" style="background:#fff; color:var(--text-primary); border:1px solid var(--border);" onclick="fpCloseAll()">Cancel</button>
          <button type="button" class="msg-ok" id="fpStep2Confirm">Confirm</button>
        </div>
      </div>
    </div>

    <!-- Step 3: New password -->
    <div id="fpStep3" class="msg-overlay" style="display:none;">
      <div class="msg-box">
        <div class="msg-text" style="margin-bottom:12px; font-weight:600;">Set new password</div>
        <div class="password-wrapper" style="max-width:100%;">
          <input type="password" id="fpNewPwd" placeholder="New password (min 8 chars)" style="width:100%;">
          <button type="button" class="password-toggle" data-target="fpNewPwd" aria-label="Toggle password visibility">
            <i data-lucide="eye" class="eye-show"></i>
            <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
          </button>
        </div>
        <div class="password-wrapper" style="max-width:100%; margin-top:8px;">
          <input type="password" id="fpConfirmPwd" placeholder="Confirm password" style="width:100%;">
          <button type="button" class="password-toggle" data-target="fpConfirmPwd" aria-label="Toggle password visibility">
            <i data-lucide="eye" class="eye-show"></i>
            <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
          </button>
        </div>
        <div id="fpStep3Err" style="display:none; color:#e74c3c; font-size:0.85rem; margin:8px 0;"></div>
        <div style="display:flex; gap:8px; justify-content:center; margin-top:12px;">
          <button type="button" class="msg-ok" style="background:#fff; color:var(--text-primary); border:1px solid var(--border);" onclick="fpCloseAll()">Cancel</button>
          <button type="button" class="msg-ok" id="fpStep3Confirm">Confirm</button>
        </div>
      </div>
    </div>

    <!-- Step 4: Success -->
    <div id="fpStep4" class="msg-overlay" style="display:none;">
      <div class="msg-box">
        <div class="msg-text" style="margin-bottom:12px; font-weight:600; color:#16a34a;">Password updated successfully!</div>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin:0;">You can now log in with your new password.</p>
      </div>
    </div>

    <script>
      (function() {
        var step1 = document.getElementById('fpStep1');
        var step2 = document.getElementById('fpStep2');
        var step3 = document.getElementById('fpStep3');
        var step4 = document.getElementById('fpStep4');
        function show(el){ if(el) el.style.display='flex'; }
        function hide(el){ if(el) el.style.display='none'; }
        window.fpCloseAll = function(){ hide(step1); hide(step2); hide(step3); hide(step4); };

        var link = document.getElementById('forgotLink');
        if (link) link.addEventListener('click', function(e){
          e.preventDefault();
          document.getElementById('fpStudentNumber').value = '';
          document.getElementById('fpStep1Err').style.display = 'none';
          show(step1);
        });

        document.getElementById('fpStep1Submit').addEventListener('click', function() {
          var sn = document.getElementById('fpStudentNumber').value.trim();
          var err = document.getElementById('fpStep1Err');
          if (sn === '') { err.textContent = 'Please enter your student number.'; err.style.display = 'block'; return; }
          var fd = new FormData();
          fd.append('action', 'verify_student');
          fd.append('student_number', sn);
          fetch('<?php echo BASE_URL; ?>reset_password.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
              if (data.success) {
                err.style.display = 'none';
                document.querySelectorAll('#fpStep2 .code-box').forEach(function(b){ b.value = ''; });
                document.getElementById('fpStep2Err').style.display = 'none';
                hide(step1); show(step2);
                var first = document.querySelector('#fpStep2 .code-box');
                if (first) first.focus();
              } else {
                err.textContent = data.message;
                err.style.display = 'block';
              }
            })
            .catch(function(){ err.textContent = 'Request failed. Please try again.'; err.style.display = 'block'; });
        });

        var codeBoxes = document.querySelectorAll('#fpStep2 .code-box');
        codeBoxes.forEach(function(box, idx) {
          box.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0,1);
            if (this.value && idx < codeBoxes.length - 1) codeBoxes[idx+1].focus();
          });
          box.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && idx > 0) codeBoxes[idx-1].focus();
          });
        });

        document.getElementById('fpStep2Confirm').addEventListener('click', function() {
          var code = '';
          codeBoxes.forEach(function(b){ code += b.value; });
          var err = document.getElementById('fpStep2Err');
          if (code.length !== 6 || !/^\d{6}$/.test(code)) {
            err.textContent = 'Please enter all 6 digits.';
            err.style.display = 'block';
            return;
          }
          err.style.display = 'none';
          document.getElementById('fpNewPwd').value = '';
          document.getElementById('fpConfirmPwd').value = '';
          document.getElementById('fpStep3Err').style.display = 'none';
          hide(step2); show(step3);
        });

        document.getElementById('fpStep3Confirm').addEventListener('click', function() {
          var np = document.getElementById('fpNewPwd').value;
          var cp = document.getElementById('fpConfirmPwd').value;
          var err = document.getElementById('fpStep3Err');
          if (np.length < <?php echo (int)MIN_PASSWORD_LENGTH; ?>) {
            err.textContent = 'Password must be at least <?php echo (int)MIN_PASSWORD_LENGTH; ?> characters.';
            err.style.display = 'block';
            return;
          }
          if (np !== cp) {
            err.textContent = 'Passwords do not match.';
            err.style.display = 'block';
            return;
          }
          var fd = new FormData();
          fd.append('action', 'reset');
          fd.append('new_password', np);
          fd.append('confirm_password', cp);
          fetch('<?php echo BASE_URL; ?>reset_password.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
              if (data.success) {
                err.style.display = 'none';
                hide(step3); show(step4);
                setTimeout(function(){ fpCloseAll(); }, 3000);
              } else {
                err.textContent = data.message;
                err.style.display = 'block';
              }
            })
            .catch(function(){ err.textContent = 'Request failed. Please try again.'; err.style.display = 'block'; });
        });

        // Dedicated toggle handler (stops propagation so global handler doesn't double-toggle)
        document.querySelectorAll('.password-toggle').forEach(function(btn) {
          btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var target = document.getElementById(this.getAttribute('data-target'));
            if (!target) return;
            var show = target.type === 'password';
            target.type = show ? 'text' : 'password';
            var eyeShow = this.querySelector('.eye-show');
            var eyeHide = this.querySelector('.eye-hide');
            if (eyeShow) eyeShow.style.display = show ? 'none' : '';
            if (eyeHide) eyeHide.style.display = show ? '' : 'none';
          });
        });

        [step1, step2, step3, step4].forEach(function(m) {
          if (m) m.addEventListener('click', function(e) { if (e.target === m) fpCloseAll(); });
        });
      })();
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>