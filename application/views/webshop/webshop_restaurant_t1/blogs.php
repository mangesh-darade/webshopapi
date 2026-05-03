<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Blogs</title>
    <link rel="stylesheet" href="<?=$assets?>restaurant/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?=$assets?>restaurant/css/style.css">
    <link rel="stylesheet" href="<?= $assets ?>restaurant/css/common.css">
</head>
<body>
<?php include_once('header.php'); ?>
<div class="container" style="padding:40px 15px;">
    <h1 class="mb-4">Latest Blogs</h1>
    <?php if (!empty($blog_module_missing)): ?>
        <div class="alert alert-danger">Blog module is not configured yet.</div>
    <?php elseif (!empty($blogs)): ?>
        <?php foreach ($blogs as $post): ?>
            <article class="mb-3 p-3 border rounded bg-white">
                <h3 class="h4 mb-2">
                    <a href="<?= site_url('blog/' . urlencode($post['slug'])) ?>">
                        <?= htmlspecialchars($post['title']) ?>
                    </a>
                </h3>
                <div class="text-muted mb-2">
                    <?= !empty($post['updated_at']) ? date('d M Y', strtotime($post['updated_at'])) : '' ?>
                </div>
                <p class="mb-0"><?= htmlspecialchars(substr(trim(strip_tags($post['content'] ?? '')), 0, 180)) ?>...</p>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No blog posts found.</p>
    <?php endif; ?>
</div>
<?php include_once('footer.php'); ?>
</body>
</html>
