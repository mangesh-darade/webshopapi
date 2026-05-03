<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= !empty($blog['title']) ? htmlspecialchars($blog['title']) : 'Blog' ?></title>
    <link rel="stylesheet" href="<?=$assets?>restaurant/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?=$assets?>restaurant/css/style.css">
    <link rel="stylesheet" href="<?= $assets ?>restaurant/css/common.css">
</head>
<body>
<?php include_once('header.php'); ?>
<div class="container" style="padding:40px 15px;">
    <p><a href="<?= site_url('blogs') ?>">&larr; Back to blogs</a></p>
    <h1 class="mb-2"><?= htmlspecialchars($blog['title']) ?></h1>
    <div class="text-muted mb-3">
        <?= !empty($blog['updated_at']) ? date('d M Y', strtotime($blog['updated_at'])) : '' ?>
    </div>
    <?php if (!empty($blog['image'])): ?>
        <img src="<?= htmlspecialchars($blog['image']) ?>" alt="<?= htmlspecialchars($blog['title']) ?>" style="max-width:100%;height:auto;border-radius:8px;margin-bottom:20px;">
    <?php endif; ?>
    <div style="line-height:1.8;">
        <?= !empty($blog['content']) ? $blog['content'] : '' ?>
    </div>
</div>
<?php include_once('footer.php'); ?>
</body>
</html>
