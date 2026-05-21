<?php
$m = new mysqli('localhost', 'root', '', 'stadmin_gulgpharmacy');
$r = $m->query("SELECT page_key, page_title, LEFT(page_text,80) AS t FROM sma_webshop_static_pages WHERE is_active=1 LIMIT 10");
while ($r && $row = $r->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
