<!-- HTML -->
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= !empty($blog['title']) ? htmlspecialchars($blog['title']) : 'Blog' ?></title>
    <link href="<?= $assets ?>gulfpharmacy_theme/css/main.css?ver=210616_01" rel="stylesheet" media="screen" charset="utf-8">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
</head>
<body>
<?php include_once('header.php'); ?>
<div class="main">
    <div class="container" style="padding:30px 15px;">
        <p><a href="<?= site_url('blogs') ?>">&larr; Back to blogs</a></p>
        <h1 style="margin-bottom:10px;"><?= htmlspecialchars($blog['title']) ?></h1>
        <div style="color:#64748b;font-size:13px;margin-bottom:20px;">
            <?= !empty($blog['updated_at']) ? date('d M Y', strtotime($blog['updated_at'])) : '' ?>
        </div>
        <?php if (!empty($blog['image'])): ?>
            <img src="<?= htmlspecialchars($blog['image']) ?>" alt="<?= htmlspecialchars($blog['title']) ?>" style="max-width:100%;height:auto;border-radius:8px;margin-bottom:20px;">
        <?php endif; ?>
        <div style="line-height:1.8;">
            <?= !empty($blog['content']) ? $blog['content'] : '' ?>
        </div>
    </div>
</div>
<?php include_once('footer.php'); ?>
</body>
</html>
