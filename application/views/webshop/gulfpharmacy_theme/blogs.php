<!-- HTML -->
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blogs</title>
    <link href="<?= $assets ?>gulfpharmacy_theme/css/main.css?ver=210616_01" rel="stylesheet" media="screen" charset="utf-8">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <style>
        .blog-card {
            padding: 18px;
            border: 1px solid #dbe7d6;
            border-radius: 10px;
            margin-bottom: 14px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        }
        .blog-card h3 {
            margin: 0 0 8px;
            font-size: 24px;
        }
        .blog-card h3 a {
            text-decoration: none;
            color: #2a6a37;
        }
        .blog-card h3 a:hover {
            text-decoration: underline;
        }
        .blog-meta {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .blog-excerpt {
            margin: 0 0 14px;
            color: #334155;
            line-height: 1.7;
        }
        .blog-read-more {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 6px;
            background: #f0fdf4;
            color: #166534;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
    </style>
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
                <article class="blog-card">
                    <h3>
                        <a href="<?= site_url('blog/' . urlencode($post['slug'])) ?>">
                            <?= htmlspecialchars($post['title']) ?>
                        </a>
                    </h3>
                    <div class="blog-meta">
                        <?= !empty($post['updated_at']) ? date('d M Y', strtotime($post['updated_at'])) : '' ?>
                    </div>
                    <p class="blog-excerpt"><?= htmlspecialchars(substr(trim(strip_tags($post['content'] ?? '')), 0, 220)) ?>...</p>
                    <a class="blog-read-more" href="<?= site_url('blog/' . urlencode($post['slug'])) ?>">Read More</a>
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
