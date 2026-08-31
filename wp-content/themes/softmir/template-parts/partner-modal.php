<?php
// Global Vendor Partnership Modal
// Included in footer.php, triggered via openPartnerModal()
$pm_title = __('Place your product', 'softmir');
$pm_subtitle = __('Fill out the form — we will create a card for your product and find clients for it. For free.', 'softmir');
$pm_company = __('Company name', 'softmir');
$pm_product = __('Product name', 'softmir');
$pm_url = __('Product website URL', 'softmir');
$pm_category = __('Category', 'softmir');
$pm_select_cat = __('— Select category —', 'softmir');
$pm_contact = __('Contact person', 'softmir');
$pm_phone = __('Phone', 'softmir');
$pm_comment = __('Comment', 'softmir');
$pm_comment_ph = __('Tell us briefly about your product...', 'softmir');
$pm_submit = __('Send request', 'softmir');
$pm_hr = __('HR / Personnel Management', 'softmir');
$pm_marketing = __('Marketing and Analytics', 'softmir');
$pm_finance = __('Finance and Accounting', 'softmir');
$pm_pm = __('Project Management', 'softmir');
$pm_security = __('Security', 'softmir');
$pm_ai = __('AI / Automation', 'softmir');
$pm_devtools = __('Development Tools', 'softmir');
$pm_other = __('Other', 'softmir');
$pm_fill_err = __('Fill in all required fields.', 'softmir');
?>
<div id="partner-modal" class="gb-modal-overlay" onclick="if(event.target===this)closePartnerModal()">
    <div class="gb-modal-box" style="max-width:480px;">
        <button type="button" onclick="closePartnerModal()" class="gb-modal-close">&times;</button>

        <h3 class="gb-modal-title gb-modal-title--discount">🤝 <?php echo esc_html($pm_title); ?></h3>
        <p class="gb-modal-subtitle" style="color:#155724;"><?php echo esc_html($pm_subtitle); ?></p>

        <div id="partner-form-wrapper">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 10px;">
                <div>
                    <label class="gb-label"><?php echo esc_html($pm_company); ?> <span
                            class="gb-required">*</span></label>
                    <input type="text" id="partner-company"
                        placeholder="<?php esc_attr_e('LLC Company', 'softmir'); ?>" class="gb-input" required>
                </div>
                <div>
                    <label class="gb-label"><?php echo esc_html($pm_product); ?> <span
                            class="gb-required">*</span></label>
                    <input type="text" id="partner-product" placeholder="MyCRM Pro" class="gb-input" required>
                </div>
            </div>

            <label class="gb-label"><?php echo esc_html($pm_url); ?> <span class="gb-required">*</span></label>
            <input type="url" id="partner-url" placeholder="https://example.com" class="gb-input" required>

            <label class="gb-label"><?php echo esc_html($pm_category); ?></label>
            <select id="partner-category" class="gb-input">
                <option value=""><?php echo esc_html($pm_select_cat); ?></option>
                <option value="CRM">CRM</option>
                <option value="ERP">ERP</option>
                <option value="HRM"><?php echo esc_html($pm_hr); ?></option>
                <option value="Marketing"><?php echo esc_html($pm_marketing); ?></option>
                <option value="Finance"><?php echo esc_html($pm_finance); ?></option>
                <option value="PM"><?php echo esc_html($pm_pm); ?></option>
                <option value="Security"><?php echo esc_html($pm_security); ?></option>
                <option value="AI"><?php echo esc_html($pm_ai); ?></option>
                <option value="DevTools"><?php echo esc_html($pm_devtools); ?></option>
                <option value="Other"><?php echo esc_html($pm_other); ?></option>
            </select>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 10px;">
                <div>
                    <label class="gb-label"><?php echo esc_html($pm_contact); ?> <span
                            class="gb-required">*</span></label>
                    <input type="text" id="partner-name" placeholder="<?php esc_attr_e('John Doe', 'softmir'); ?>"
                        class="gb-input" required>
                </div>
                <div>
                    <label class="gb-label">Email <span class="gb-required">*</span></label>
                    <input type="email" id="partner-email" placeholder="ivan@company.com" class="gb-input" required>
                </div>
            </div>

            <label class="gb-label"><?php echo esc_html($pm_phone); ?></label>
            <input type="tel" id="partner-phone" placeholder="+1 XX XXX XX XX" class="gb-input">

            <label class="gb-label"><?php echo esc_html($pm_comment); ?></label>
            <textarea id="partner-message" placeholder="<?php echo esc_attr($pm_comment_ph); ?>"
                class="gb-input gb-input-last" rows="3" style="resize:vertical;"></textarea>

            <button type="button" class="btn btn-primary w-full gb-submit-btn" id="partner-submit-btn"
                onclick="submitPartnerRequest()"><?php echo esc_html($pm_submit); ?></button>
        </div>
        <div id="partner-result-msg" class="gb-result-msg"></div>
    </div>
</div>

<script>
    var _pmStrings = {
        fillErr: <?php echo json_encode($pm_fill_err); ?>,
        submit: <?php echo json_encode($pm_submit); ?>
    };

    function openPartnerModal() {
        document.getElementById('partner-form-wrapper').style.display = 'block';
        document.getElementById('partner-result-msg').style.display = 'none';
        var btn = document.getElementById('partner-submit-btn');
        btn.disabled = false;
        btn.innerText = _pmStrings.submit;
        document.getElementById('partner-modal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closePartnerModal() {
        document.getElementById('partner-modal').style.display = 'none';
        document.body.style.overflow = '';
    }

    function submitPartnerRequest() {
        var btn = document.getElementById('partner-submit-btn');
        var wrapper = document.getElementById('partner-form-wrapper');
        var msgBox = document.getElementById('partner-result-msg');

        var company = document.getElementById('partner-company').value.trim();
        var product = document.getElementById('partner-product').value.trim();
        var url = document.getElementById('partner-url').value.trim();
        var category = document.getElementById('partner-category').value;
        var name = document.getElementById('partner-name').value.trim();
        var email = document.getElementById('partner-email').value.trim();
        var phone = document.getElementById('partner-phone').value.trim();
        var message = document.getElementById('partner-message').value.trim();

        if (!company || !product || !url || !name || !email) {
            msgBox.innerHTML = '<span class="gb-error">' + _pmStrings.fillErr + '</span>';
            msgBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerText = '...';

        var headers = { 'Content-Type': 'application/json' };
        if (typeof softmirGroupBuy !== 'undefined' && softmirGroupBuy.nonce) {
            headers['X-WP-Nonce'] = softmirGroupBuy.nonce;
        }

        fetch('/wp-json/softmir/v1/partner-request', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                company_name: company,
                product_name: product,
                product_url: url,
                category: category,
                contact_name: name,
                contact_email: email,
                contact_phone: phone,
                message: message
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.message) {
                    wrapper.style.display = 'none';
                    msgBox.innerHTML = '<span class="gb-success">' + data.message + '</span>';
                    msgBox.style.display = 'block';
                    setTimeout(function () { closePartnerModal(); }, 3000);
                } else {
                    throw new Error(data.message || 'Error');
                }
            })
            .catch(function (e) {
                btn.disabled = false;
                btn.innerText = _pmStrings.submit;
                msgBox.innerHTML = '<span class="gb-error">' + (e.message || 'Error') + '</span>';
                msgBox.style.display = 'block';
            });
    }
</script>