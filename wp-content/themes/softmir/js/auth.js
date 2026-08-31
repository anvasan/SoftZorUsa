/**
 * SoftMir Auth — Frontend JS
 * Password toggle, strength indicator, user dropdown, form validation
 */
document.addEventListener('DOMContentLoaded', function () {

    // ========== Toggle Password Visibility ==========
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = this.closest('.auth-input-wrap').querySelector('input');
            if (!input) return;
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            // Swap icon
            this.innerHTML = isPassword
                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        });
    });

    // ========== Password Strength Indicator ==========
    var passwordInput = document.getElementById('password');
    var strengthBar = document.getElementById('password-strength');

    if (passwordInput && strengthBar) {
        passwordInput.addEventListener('input', function () {
            var val = this.value;
            var score = 0;
            if (val.length >= 8) score++;
            if (val.length >= 12) score++;
            if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
            if (/\d/.test(val)) score++;
            if (/[^a-zA-Z0-9]/.test(val)) score++;

            var levels = [
                { label: '', color: '', width: '0%' },
                { label: 'Weak', color: '#ef4444', width: '20%' },
                { label: 'Weak', color: '#f97316', width: '40%' },
                { label: 'Medium', color: '#eab308', width: '60%' },
                { label: 'Good', color: '#22c55e', width: '80%' },
                { label: 'Excellent', color: '#10b981', width: '100%' },
            ];

            var level = levels[score] || levels[0];
            if (val.length === 0) {
                strengthBar.innerHTML = '';
                return;
            }
            strengthBar.innerHTML =
                '<div class="strength-bar-track">' +
                '<div class="strength-bar-fill" style="width:' + level.width + ';background:' + level.color + '"></div>' +
                '</div>' +
                '<span class="strength-label" style="color:' + level.color + '">' + level.label + '</span>';
        });
    }

    // ========== User Dropdown Menu ==========
    var userMenuToggle = document.getElementById('user-menu-toggle');
    var userDropdown = document.getElementById('user-dropdown');

    if (userMenuToggle && userDropdown) {
        userMenuToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdown.classList.toggle('open');
        });

        document.addEventListener('click', function (e) {
            if (userDropdown && !userDropdown.contains(e.target) && !userMenuToggle.contains(e.target)) {
                userDropdown.classList.remove('open');
            }
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                userDropdown.classList.remove('open');
            }
        });
    }

    // ========== Client-side Form Validation ==========
    var registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            var pw = registerForm.querySelector('[name="password"]');
            var pw2 = registerForm.querySelector('[name="password_confirm"]');
            if (pw && pw2 && pw.value !== pw2.value) {
                e.preventDefault();
                showFormError(pw2, 'Passwords do not match');
            }
        });
    }

    var resetForm = document.getElementById('reset-password-form');
    if (resetForm) {
        resetForm.addEventListener('submit', function (e) {
            var pw = resetForm.querySelector('[name="password"]');
            var pw2 = resetForm.querySelector('[name="password_confirm"]');
            if (pw && pw2 && pw.value !== pw2.value) {
                e.preventDefault();
                showFormError(pw2, 'Passwords do not match');
            }
        });
    }

    function showFormError(input, message) {
        // Remove existing
        var existing = input.closest('.auth-field').querySelector('.field-error');
        if (existing) existing.remove();

        var err = document.createElement('div');
        err.className = 'field-error';
        err.textContent = message;
        input.closest('.auth-field').appendChild(err);

        input.focus();
        input.addEventListener('input', function handler() {
            if (err.parentNode) err.remove();
            input.removeEventListener('input', handler);
        });
    }

    // ========== Auto-hide flash messages ==========
    document.querySelectorAll('.flash-message').forEach(function (flash) {
        setTimeout(function () {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(function () { flash.remove(); }, 300);
        }, 5000);
    });

});
