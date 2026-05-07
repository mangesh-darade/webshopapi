<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Us</title>
</head>
<body>
    <?php include_once('header.php'); ?>
    <div class="main">
        <div class="container">
            <div class="main-row">
                <div class="main-content contact-us" style="padding:20px 0;">
                    <h2>Contact Us</h2>
                    <p>Please fill out the form below and we will get back to you shortly.</p>

                    <?php if ($this->session->flashdata('success')): ?>
                        <div class="alert alert-success"><?= $this->session->flashdata('success') ?></div>
                    <?php endif; ?>
                    <?php if ($this->session->flashdata('error')): ?>
                        <div class="alert alert-danger"><?= $this->session->flashdata('error') ?></div>
                    <?php endif; ?>

                    <form action="<?= base_url('webshop/submit_contact') ?>" method="post" class="contact-form">
                        <?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?>
                        <div class="row g-4 mt-3 mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control" value="<?= set_value('name') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="email" class="form-control" value="<?= set_value('email') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" name="phone" id="phone" class="form-control" value="<?= set_value('phone') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" id="subject" class="form-control" value="<?= set_value('subject') ?>" required>
                            </div>
                            <div class="col-md-12">
                                <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea name="message" id="message" rows="5" class="form-control" required><?= set_value('message') ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include_once('footer.php'); ?>
</body>
</html>
