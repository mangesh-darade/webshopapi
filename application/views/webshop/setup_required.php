<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Webshop AI — API setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 640px;">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">Webshop AI cannot load yet</h1>
            <p class="text-danger mb-2"><?php echo html_escape($title); ?></p>
            <?php if (!empty($detail)): ?>
                <p class="small text-muted mb-4"><?php echo nl2br(html_escape($detail)); ?></p>
            <?php endif; ?>
            <h2 class="h6">Fix</h2>
            <ol class="small">
                <li>ElintOm must be running at the URL in <code>application/config/elintom_api.php</code> (<code>elintom_api_base_url</code>).</li>
                <li>Set <code>elintom_api_private_key</code> to the same value as <strong>ElintOm → Settings → API private key</strong> (database field used by <code>api3/eshop</code>).</li>
                <li>Optional: copy <code>elintom_api.local.example.php</code> to <code>elintom_api.local.php</code> and put your key there (keeps secrets out of git).</li>
                <li>Ensure API access is enabled and POS version is 3+ in ElintOm.</li>
            </ol>
            <p class="small mb-0"><a href="<?php echo html_escape($base_url); ?>">Retry</a></p>
        </div>
    </div>
</div>
</body>
</html>
