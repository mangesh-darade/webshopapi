<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$autoload['packages'] = array();
$autoload['libraries'] = array('session', 'elintom_api_client');
$autoload['drivers'] = array();
$autoload['helper'] = array('url', 'form', 'html', 'webshop');
/* Do not autoload elintom_api here — must load with use_sections=TRUE; Elintom_api_client does that first. */
$autoload['config'] = array();
$autoload['language'] = array();
$autoload['model'] = array();
