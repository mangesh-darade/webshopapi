<?php
/**
 * Webshop AI — CodeIgniter 3 front controller (THIS is what loads for http://localhost/elintomapi/).
 *
 * Uses: system/ + application/ + assets/  (CodeIgniter 3 only in this project.)
 * See FOLDER_LAYOUT.txt in this folder.
 */
	$timezone = "Asia/Kolkata";
	if (function_exists('date_default_timezone_set')) {
		date_default_timezone_set($timezone);
	}
	define('TIMEZONE', $timezone);
	define('DEMO', 0);
	$is_local = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])/i', $_SERVER['HTTP_HOST']);
	define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : ($is_local ? 'development' : 'production'));

switch (ENVIRONMENT)
{
	case 'development':
		error_reporting(-1);
		ini_set('display_errors', 1);
	break;
	case 'testing':
	case 'production':
		ini_set('display_errors', 0);
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
	break;
	default:
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'The application environment is not set correctly.';
		exit(1);
}

	define('WEBSHOP_AI_NAME', 'Webshop AI');

	$system_path = 'system';
	$application_folder = 'application';
	$view_folder = 'application/views';

	if (defined('STDIN')) {
		chdir(dirname(__FILE__));
	}

	if (($_temp = realpath($system_path)) !== FALSE) {
		$system_path = $_temp.DIRECTORY_SEPARATOR;
	} else {
		$system_path = strtr(rtrim($system_path, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
	}

	if ( ! is_dir($system_path)) {
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'System folder not found. Expected CodeIgniter `system` directory beside this index.';
		exit(3);
	}

	define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
	define('BASEPATH', $system_path);
	define('FCPATH', dirname(__FILE__).DIRECTORY_SEPARATOR);
	define('SYSDIR', basename(BASEPATH));

	if (is_dir($application_folder)) {
		if (($_temp = realpath($application_folder)) !== FALSE) {
			$application_folder = $_temp;
		} else {
			$application_folder = strtr(rtrim($application_folder, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
		}
	} else {
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'Application folder not found.';
		exit(3);
	}

	define('APPPATH', $application_folder.DIRECTORY_SEPARATOR);

	if ( ! isset($view_folder[0]) && is_dir(APPPATH.'views'.DIRECTORY_SEPARATOR)) {
		$view_folder = APPPATH.'views';
	} elseif (is_dir($view_folder)) {
		if (($_temp = realpath($view_folder)) !== FALSE) {
			$view_folder = $_temp;
		} else {
			$view_folder = strtr(rtrim($view_folder, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
		}
	} elseif (is_dir(APPPATH.$view_folder.DIRECTORY_SEPARATOR)) {
		$view_folder = APPPATH.strtr(trim($view_folder, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
	} else {
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'View folder not found.';
		exit(3);
	}

	define('VIEWPATH', $view_folder.DIRECTORY_SEPARATOR);

require_once BASEPATH.'core/CodeIgniter.php';
