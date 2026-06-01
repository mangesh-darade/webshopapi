<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$varName = isset($var_name) ? trim((string) $var_name) : '';
$varValue = isset($var_value) ? $var_value : null;
if ($varName === '') return;
?>
<script>window.<?= htmlspecialchars($varName, ENT_QUOTES, 'UTF-8') ?>=<?= json_encode($varValue, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
