<?php defined('BASEPATH') OR exit('No direct script access allowed');
$title          = isset($error_title) ? (string) $error_title : 'Store unavailable';
$message        = isset($error_message) ? (string) $error_message : '';
$error_code     = isset($error_code) ? $error_code : null;
$detail_lines   = isset($detail_lines) && is_array($detail_lines) ? $detail_lines : array();
$help_steps     = isset($help_steps) && is_array($help_steps) ? $help_steps : array();
$show_technical = !empty($show_technical);
$technical      = isset($technical) && is_array($technical) ? $technical : array();
$http_host      = isset($http_host) ? (string) $http_host : '';
$api_base       = isset($api_base) ? (string) $api_base : '';
$key_configured = !empty($key_configured);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root {
            --gp-primary: #0f766e;
            --gp-primary-dark: #0d5c56;
            --gp-bg: #f4f7f6;
            --gp-card: #fff;
            --gp-text: #1f2937;
            --gp-muted: #6b7280;
            --gp-danger: #dc2626;
            --gp-danger-bg: #fef2f2;
            --gp-border: #e5e7eb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: var(--gp-bg);
            color: var(--gp-text);
            line-height: 1.55;
        }
        .wrap {
            max-width: 720px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }
        .card {
            background: var(--gp-card);
            border: 1px solid var(--gp-border);
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(15, 118, 110, 0.08);
            overflow: hidden;
        }
        .card-head {
            background: linear-gradient(135deg, var(--gp-primary) 0%, var(--gp-primary-dark) 100%);
            color: #fff;
            padding: 28px 28px 24px;
        }
        .card-head h1 {
            margin: 0 0 8px;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .card-head p {
            margin: 0;
            opacity: 0.92;
            font-size: 0.95rem;
        }
        .badge {
            display: inline-block;
            margin-top: 14px;
            padding: 4px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.2);
            font-size: 0.8rem;
            font-weight: 600;
        }
        .card-body { padding: 24px 28px 28px; }
        .alert {
            background: var(--gp-danger-bg);
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 22px;
        }
        .alert strong {
            display: block;
            color: var(--gp-danger);
            font-size: 1.05rem;
            margin-bottom: 6px;
        }
        .alert p { margin: 0; color: #991b1b; }
        .meta {
            display: grid;
            gap: 10px;
            margin-bottom: 22px;
            font-size: 0.9rem;
        }
        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            padding: 10px 14px;
            background: #f9fafb;
            border-radius: 10px;
            border: 1px solid var(--gp-border);
        }
        .meta-row dt {
            margin: 0;
            font-weight: 600;
            color: var(--gp-muted);
            min-width: 140px;
        }
        .meta-row dd {
            margin: 0;
            flex: 1;
            word-break: break-all;
        }
        .meta-row dd.ok { color: #0a7c3f; font-weight: 600; }
        .meta-row dd.no { color: var(--gp-danger); font-weight: 600; }
        h2 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--gp-muted);
            margin: 0 0 12px;
        }
        ol.steps {
            margin: 0 0 24px;
            padding-left: 1.25rem;
        }
        ol.steps li { margin-bottom: 8px; }
        ol.steps code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.88em;
        }
        details.tech {
            border: 1px solid var(--gp-border);
            border-radius: 10px;
            background: #fafafa;
        }
        details.tech summary {
            cursor: pointer;
            padding: 12px 16px;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gp-muted);
        }
        details.tech .tech-inner { padding: 0 16px 16px; }
        details.tech pre {
            margin: 0 0 12px;
            padding: 12px;
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 8px;
            font-size: 0.78rem;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        details.tech dl { margin: 0; font-size: 0.88rem; }
        details.tech dt { font-weight: 600; color: var(--gp-muted); margin-top: 8px; }
        details.tech dd { margin: 4px 0 0; word-break: break-word; }
        .actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: var(--gp-primary);
            color: #fff;
        }
        .btn-primary:hover { background: var(--gp-primary-dark); }
        .btn-ghost {
            background: #fff;
            color: var(--gp-primary);
            border: 1px solid var(--gp-border);
        }
        .foot {
            margin-top: 20px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--gp-muted);
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <header class="card-head">
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <p>The storefront could not load settings from ElintOm.</p>
            <?php if ($error_code !== null && $error_code !== '') : ?>
                <span class="badge">Error code <?= htmlspecialchars((string) $error_code, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </header>
        <div class="card-body">
            <div class="alert">
                <strong><?= htmlspecialchars($message !== '' ? $message : 'Connection failed', ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (!empty($detail_lines)) : ?>
                    <?php foreach ($detail_lines as $line) : ?>
                        <p><?= htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <dl class="meta">
                <div class="meta-row">
                    <dt>Your site (HTTP_HOST)</dt>
                    <dd><?= htmlspecialchars($http_host !== '' ? $http_host : '(unknown)', ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="meta-row">
                    <dt>ElintOm API URL</dt>
                    <dd><?= htmlspecialchars($api_base !== '' ? $api_base : '(not configured)', ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="meta-row">
                    <dt>API private key</dt>
                    <dd class="<?= $key_configured ? 'ok' : 'no' ?>"><?= $key_configured ? 'Configured in webshopapi' : 'Missing — check elintom_api_switch.php' ?></dd>
                </div>
            </dl>

            <?php if (!empty($help_steps)) : ?>
                <h2>How to fix</h2>
                <ol class="steps">
                    <?php foreach ($help_steps as $step) : ?>
                        <li><?= $step ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if ($show_technical) : ?>
                <details class="tech">
                    <summary>Technical details (for developers)</summary>
                    <div class="tech-inner">
                        <?php foreach ($technical as $block) :
                            if (!is_array($block)) { continue; }
                            $label = isset($block['label']) ? (string) $block['label'] : '';
                            $content = isset($block['content']) ? (string) $block['content'] : '';
                            if ($content === '') { continue; }
                        ?>
                            <?php if ($label !== '') : ?><dt><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt><?php endif; ?>
                            <dd><pre><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></pre></dd>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <div class="actions">
                <button type="button" class="btn btn-primary" onclick="location.reload()">Reload page</button>
                <?php if ($api_base !== '') : ?>
                    <a class="btn btn-ghost" href="<?= htmlspecialchars(rtrim($api_base, '/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open ElintOm POS</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <p class="foot">webshopapi · ElintOm connection</p>
</div>
</body>
</html>
