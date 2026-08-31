<?php
// CTA Module — AJAX email collector
$title = __('Need help choosing software?', 'softmir');
$text = __('Leave your email and our experts will help you find the perfect solution for your business.', 'softmir');
$btn_text = __('Submit', 'softmir');
$success_text = __('Thank you! We will contact you.', 'softmir');
$error_text = __('Error. Please try again.', 'softmir');
$invalid_text = __('Please enter a valid email.', 'softmir');
?>
<section class="section">
    <div class="container">
        <div class="cta-box">
            <h2><?php echo esc_html($title); ?></h2>
            <p><?php echo esc_html($text); ?></p>
            <form class="email-form" id="softmir-cta-form" novalidate>
                <?php wp_nonce_field('softmir_cta_subscribe', 'softmir_cta_nonce'); ?>
                <input type="email" name="email" placeholder="<?php esc_attr_e('Enter your email', 'softmir'); ?>"
                    class="email-input" required>
                <button type="submit" class="btn btn-primary" id="softmir-cta-btn">
                    <?php echo esc_html($btn_text); ?>
                </button>
            </form>
            <div class="cta-message" id="softmir-cta-msg" style="display:none;"></div>
        </div>
    </div>
</section>
<script>
    (function () {
        var form = document.getElementById('softmir-cta-form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('softmir-cta-btn');
            var msg = document.getElementById('softmir-cta-msg');
            var email = form.querySelector('input[name="email"]').value.trim();
            var nonce = form.querySelector('input[name="softmir_cta_nonce"]').value;

            // Basic email validation
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                msg.textContent = <?php echo json_encode($invalid_text); ?>;
                msg.className = 'cta-message cta-message--error';
                msg.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = '...';

            var fd = new FormData();
            fd.append('action', 'softmir_cta_subscribe');
            fd.append('nonce', nonce);
            fd.append('email', email);

            fetch(<?php echo json_encode(admin_url('admin-ajax.php')); ?>, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        msg.textContent = <?php echo json_encode($success_text); ?>;
                        msg.className = 'cta-message cta-message--success';
                        form.style.display = 'none';
                    } else {
                        msg.textContent = data.data && data.data.message ? data.data.message : <?php echo json_encode($error_text); ?>;
                        msg.className = 'cta-message cta-message--error';
                    }
                    msg.style.display = 'block';
                })
                .catch(function () {
                    msg.textContent = <?php echo json_encode($error_text); ?>;
                    msg.className = 'cta-message cta-message--error';
                    msg.style.display = 'block';
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = <?php echo json_encode($btn_text); ?>;
                });
        });
    })();
</script>