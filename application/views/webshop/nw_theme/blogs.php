<!-- HTML -->
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blogs</title>
    <link href="<?= $assets ?>nw_theme/css/main.css?ver=210616_01" rel="stylesheet" media="screen" charset="utf-8">
    <link rel="stylesheet" href="<?= $assets ?>nw_theme/css/common.css">
</head>
<body>
<?php include_once('header.php'); ?>
<div class="main">
    <div class="container" style="padding:30px 15px;">
        <h1 style="margin-bottom:20px;">Latest Blogs</h1>
        <?php if (!empty($blog_module_missing)): ?>
            <div style="padding:14px;border:1px solid #fecaca;background:#fff1f2;color:#9f1239;border-radius:8px;">
                Blog module is not configured yet.
            </div>
        <?php elseif (!empty($blogs)): ?>
            <?php foreach ($blogs as $post): ?>
                <article style="padding:18px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:12px;background:#fff;">
                    <h3 style="margin:0 0 10px;">
                        <a href="<?= site_url('blog/' . urlencode($post['slug'])) ?>" style="text-decoration:none;">
                            <?= htmlspecialchars($post['title']) ?>
                        </a>
                    </h3>
                    <div style="color:#64748b;font-size:13px;margin-bottom:8px;">
                        <?= !empty($post['updated_at']) ? date('d M Y', strtotime($post['updated_at'])) : '' ?>
                    </div>
                    <p style="margin:0;color:#334155;"><?= htmlspecialchars(substr(trim(strip_tags($post['content'] ?? '')), 0, 180)) ?>...</p>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No blog posts found.</p>
        <?php endif; ?>
    </div>
</div>
<?php include_once('footer.php'); ?>
</body>
</html>
