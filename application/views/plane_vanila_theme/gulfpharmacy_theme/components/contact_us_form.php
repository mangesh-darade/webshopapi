<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$formFields = isset($fields) && is_array($fields) ? $fields : array();
$formTitle = isset($title) ? (string) $title : 'Contact Us';
$formSubtitle = isset($subtitle) ? (string) $subtitle : '';
$buttonText = isset($button_text) ? (string) $button_text : 'Send Message';
$submitUrl = isset($submit_url) && trim((string) $submit_url) !== '' ? (string) $submit_url : site_url('webshop/contact_us_submit');
$contactSuccess = $this->session->flashdata('contact_success');
$contactErrors = $this->session->flashdata('contact_errors');
?>
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/contact-us-form.css?ver=20260528a') ?>">
<section class="contact-us-component">
    <h3 class="contact-us-title"><?= htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8') ?></h3>
    <?php if ($formSubtitle !== ''): ?>
        <p class="contact-us-subtitle"><?= htmlspecialchars($formSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if (!empty($contactSuccess)): ?>
        <div class="contact-us-alert contact-us-alert-success" role="alert"><?= htmlspecialchars((string) $contactSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($contactErrors) && is_array($contactErrors)): ?>
        <div class="contact-us-alert contact-us-alert-error" role="alert">
            <ul>
                <?php foreach ($contactErrors as $err): ?>
                    <li><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($submitUrl, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars(current_url(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="contact_field_meta" value="<?= htmlspecialchars(json_encode($formFields), ENT_QUOTES, 'UTF-8') ?>">
        <div class="contact-us-grid">
            <?php foreach ($formFields as $f): ?>
                <?php
                $fieldKey = isset($f['field_key']) ? (string) $f['field_key'] : '';
                if ($fieldKey === '') {
                    continue;
                }
                $fieldType = isset($f['field_type']) ? strtolower((string) $f['field_type']) : 'text';
                if ($fieldType === 'mobile') {
                    $fieldType = 'tel';
                }
                $label = isset($f['field_label']) ? (string) $f['field_label'] : ucfirst(str_replace('_', ' ', $fieldKey));
                $placeholder = isset($f['placeholder']) ? (string) $f['placeholder'] : '';
                $required = !empty($f['is_required']);
                $options = isset($f['options']) && is_array($f['options']) ? $f['options'] : array();
                $value = $this->input->post($fieldKey);
                $value = is_array($value) ? $value : (string) $this->input->post($fieldKey, true);
                ?>
                <div class="contact-us-field <?= $fieldType === 'textarea' ? 'contact-us-field-full' : '' ?>">
                    <label class="contact-us-label" for="cf_<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?= $required ? ' *' : '' ?>
                    </label>

                    <?php if ($fieldType === 'textarea'): ?>
                        <textarea
                            class="contact-us-textarea"
                            id="cf_<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>"
                            name="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
                            rows="4"
                            <?= $required ? 'required' : '' ?>
                        ><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <?php elseif ($fieldType === 'select'): ?>
                        <select
                            class="contact-us-select"
                            id="cf_<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>"
                            name="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $required ? 'required' : '' ?>
                        >
                            <option value="">Select</option>
                            <?php foreach ($options as $opt): ?>
                                <?php $optValue = (string) $opt; ?>
                                <option value="<?= htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8') ?>" <?= ((string) $value === $optValue) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($fieldType === 'checkbox' || $fieldType === 'radio'): ?>
                        <div class="contact-us-check-wrap">
                            <?php foreach ($options as $idx => $opt): ?>
                                <?php
                                $optValue = (string) $opt;
                                $isChecked = false;
                                if ($fieldType === 'checkbox' && is_array($value)) {
                                    $isChecked = in_array($optValue, $value, true);
                                } elseif ($fieldType === 'radio' && (string) $value === $optValue) {
                                    $isChecked = true;
                                }
                                ?>
                                <label class="contact-us-check-item" for="cf_<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>_<?= (int) $idx ?>">
                                    <input
                                        type="<?= $fieldType ?>"
                                        id="cf_<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>_<?= (int) $idx ?>"
                                        name="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?><?= $fieldType === 'checkbox' ? '[]' : '' ?>"
                                        value="<?= htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $isChecked ? 'checked' : '' ?>
                                    >
                                    <span><?= htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <input
                            type="<?= htmlspecialchars($fieldType, ENT_QUOTES, 'UTF-8') ?>"
                            class="contact-us-input"
                            id="cf_<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>"
                            name="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>"
                            value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $required ? 'required' : '' ?>
                        >
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="contact-us-submit-wrap">
                <button type="submit" class="contact-us-submit"><?= htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </form>
</section>

