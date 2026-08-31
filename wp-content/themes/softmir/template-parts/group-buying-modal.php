<?php
// Global Group Buying Modal
// Included in footer.php, triggered via openGbModal(id, name, discount)
?>
<div id="gb-global-modal" class="gb-modal-overlay" onclick="if(event.target===this)closeGbModal()">
    <div class="gb-modal-box">
        <button type="button" onclick="closeGbModal()" class="gb-modal-close">&times;</button>

        <h3 id="gb-modal-title" class="gb-modal-title">🤝 Group buying request</h3>
        <p class="gb-modal-subtitle">Leave a request — we will negotiate an<br>exclusive price with the developer.</p>

        <p id="gb-modal-software-name" class="gb-modal-software-name"></p>

        <div id="gb-form-wrapper">
            <input type="hidden" id="gb-global-sw-id" value="">
            <label class="gb-label">Full name <span class="gb-required">*</span></label>
            <input type="text" id="gb-global-name" placeholder="John Doe" class="gb-input" required>

            <label class="gb-label">Email <span class="gb-required">*</span></label>
            <input type="email" id="gb-global-email" placeholder="name@company.com" class="gb-input" required>

            <label class="gb-label">Phone</label>
            <input type="tel" id="gb-global-phone" placeholder="+1 XX XXX XX XX" class="gb-input">

            <label class="gb-label">Organization <span class="gb-required">*</span></label>
            <input type="text" id="gb-global-org" placeholder="LLC Company" class="gb-input gb-input-last" required>

            <button type="button" class="btn btn-primary w-full gb-submit-btn" id="gb-submit-btn-global"
                onclick="submitGlobalGroupBuy(this)">Send request</button>
        </div>
        <div id="gb-result-msg" class="gb-result-msg"></div>
    </div>
</div>

<script>
    function openGbModal(sw_id, sw_name, discount_amount) {
        discount_amount = discount_amount || '';
        document.getElementById('gb-global-sw-id').value = sw_id;
        document.getElementById('gb-modal-software-name').innerText = sw_name;

        var title = document.getElementById('gb-modal-title');
        if (discount_amount && discount_amount !== '' && discount_amount !== 'индивидуальную discount') {
            title.innerHTML = '🔥 Discount request ' + discount_amount;
            title.className = 'gb-modal-title gb-modal-title--discount';
        } else {
            title.innerHTML = '🤝 Group buying request';
            title.className = 'gb-modal-title';
        }

        // reset form
        document.getElementById('gb-form-wrapper').style.display = 'block';
        document.getElementById('gb-result-msg').style.display = 'none';
        var btn = document.getElementById('gb-submit-btn-global');
        btn.disabled = false;
        btn.innerText = 'Send request';

        document.getElementById('gb-global-modal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeGbModal() {
        document.getElementById('gb-global-modal').style.display = 'none';
        document.body.style.overflow = '';
    }

    function submitGlobalGroupBuy(btn) {
        var sw_id = document.getElementById('gb-global-sw-id').value;
        var wrapper = document.getElementById('gb-form-wrapper');
        var msgBox = document.getElementById('gb-result-msg');

        var name = document.getElementById('gb-global-name').value.trim();
        var email = document.getElementById('gb-global-email').value.trim();
        var phone = document.getElementById('gb-global-phone').value.trim();
        var org = document.getElementById('gb-global-org').value.trim();

        if (!name || !email || !org) {
            msgBox.innerHTML = '<span class="gb-error">Please fill in Full name, Email and Organization.</span>';
            msgBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerText = 'Sending...';

        var headers = { 'Content-Type': 'application/json' };
        if (typeof softmirGroupBuy !== 'undefined' && softmirGroupBuy.nonce) {
            headers['X-WP-Nonce'] = softmirGroupBuy.nonce;
        }

        fetch('/wp-json/softmir/v1/group-buy', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                software_id: sw_id,
                contact_name: name,
                contact_email: email,
                contact_phone: phone,
                organization: org,
                seats_needed: 1
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.message) {
                    wrapper.style.display = 'none';
                    msgBox.innerHTML = '<span class="gb-success">' + data.message + '</span>';
                    msgBox.style.display = 'block';

                    var sidebarMsg = document.getElementById('gb-result-sidebar-' + sw_id);
                    if (sidebarMsg) {
                        sidebarMsg.innerHTML = '<span class="gb-success">✓ Request accepted!</span>';
                        sidebarMsg.style.display = 'block';
                    }

                    setTimeout(function () { closeGbModal(); }, 2500);
                } else if (data.message === undefined && data.code) {
                    throw new Error(data.message || 'Error');
                } else {
                    throw new Error('err');
                }
            })
            .catch(function (e) {
                btn.disabled = false;
                btn.innerText = 'Send request';
                msgBox.innerHTML = '<span class="gb-error">' + (e.message || 'Error. Please try again later.') + '</span>';
                msgBox.style.display = 'block';
            });
    }
</script>