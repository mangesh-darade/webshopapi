<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$formTitle = isset($title) ? (string) $title : 'Contact Us';
$formSubtitle = isset($subtitle) ? (string) $subtitle : '';
$buttonText = isset($button_text) ? (string) $button_text : 'Send Message';
$submitUrl = isset($submit_url) && trim((string) $submit_url) !== '' ? (string) $submit_url : site_url('webshop/contact_us_submit');
$contactSuccess = $this->session->flashdata('contact_success');
$contactErrors = $this->session->flashdata('contact_errors');
$showNotice = ((string) $this->input->get('contact_notice', true) === '1');
$csrfName = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();

$nameValue = (string) $this->input->post('name', true);
$phoneValue = (string) $this->input->post('phone', true);
$emailValue = (string) $this->input->post('email', true);
$messageValue = (string) $this->input->post('message', true);
?>
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/contact-us-form.css?ver=20260528a') ?>">
<section class="contact-us-component">
    <h3 class="contact-us-title"><?= htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8') ?></h3>
    <?php if ($formSubtitle !== ''): ?>
        <p class="contact-us-subtitle"><?= htmlspecialchars($formSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <div class="contact-us-alert-wrap" data-contact-alert-wrap>
        <?php if ($showNotice && !empty($contactSuccess)): ?>
            <div class="contact-us-alert contact-us-alert-success" role="alert"><?= htmlspecialchars((string) $contactSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($showNotice && !empty($contactErrors) && is_array($contactErrors)): ?>
            <div class="contact-us-alert contact-us-alert-error" role="alert">
                <ul>
                    <?php foreach ($contactErrors as $err): ?>
                        <li><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <form method="post"
          action="<?= htmlspecialchars($submitUrl, ENT_QUOTES, 'UTF-8') ?>"
          class="contact-us-form"
          data-contact-us-form="1">
        <input type="hidden" name="<?= htmlspecialchars($csrfName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($csrfHash, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars(current_url(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="contact-us-grid">
            <div class="contact-us-field">
                <label class="contact-us-label" for="cf_name">Name *</label>
                <input type="text" class="contact-us-input" id="cf_name" name="name" value="<?= htmlspecialchars($nameValue, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter your name" required>
            </div>
            <div class="contact-us-field">
                <label class="contact-us-label" for="cf_phone">Phone No *</label>
                <input type="tel" class="contact-us-input" id="cf_phone" name="phone" value="<?= htmlspecialchars($phoneValue, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter phone number" inputmode="tel" minlength="7" maxlength="20" pattern="[0-9+\-\s]{7,20}" required>
            </div>
            <div class="contact-us-field contact-us-field-full">
                <label class="contact-us-label" for="cf_email">Email</label>
                <input type="email" class="contact-us-input" id="cf_email" name="email" value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter email address">
            </div>
            <div class="contact-us-field contact-us-field-full">
                <label class="contact-us-label" for="cf_message">Message</label>
                <textarea class="contact-us-textarea" id="cf_message" name="message" rows="4" placeholder="Write your message"><?= htmlspecialchars($messageValue, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="contact-us-submit-wrap">
                <button type="submit" class="contact-us-submit" data-contact-submit-btn><?= htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </form>
    <script defer src="<?= htmlspecialchars(webshop_theme_assets_url('js/contact_us.js?ver=20260528b'), ENT_QUOTES, 'UTF-8') ?>"></script>
</section>

