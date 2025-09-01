<?php

/**
 * Turn off ngrok browser warning
 */
header("ngrok-skip-browser-warning: true");
ob_start();


/*
| Detect Environment
|------------------------------------------------------------------------------------------------
| This script detects the environment based on the APP_ENV variable.
| It sets the environment to 'production' if the variable is not set or invalid.
|------------------------------------------------------------------------------------------------
*/
$environment = $_SERVER['APP_ENV'] ?? 'production';
if (!in_array($environment, ['local', 'development', 'staging', 'production'])) {
    $environment = 'production';
}

/*
| Define ROOT_DIR
|------------------------------------------------------------------------------------------------
| This defines the root directory of the application.
| It checks if the directory exists and is readable.
| If not, it returns a 500 error.
|------------------------------------------------------------------------------------------------
*/
if (!defined('ROOT_DIR')) {
    $rootDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    if (!is_dir($rootDir) || !is_readable($rootDir)) {
        http_response_code(500);
        die('Application root directory not accessible');
    }
    /** Define the root directory constant of CraftLite application */
    define('ROOT_DIR', $rootDir);
}

/*
| Autoloading
|------------------------------------------------------------------------------------------------
| This loads the Composer autoloader to include all dependencies.
| If the autoloader is not found, it returns a 500 error.
|------------------------------------------------------------------------------------------------
*/
$autoloadFile = ROOT_DIR . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    http_response_code(500);
    die('Composer autoloader not found. Please run "composer install"');
}
require_once $autoloadFile;


/*
| Initialize the Craft web application
|------------------------------------------------------------------------------------------------
| This sets up the application environment and prepares it for web requests.
|------------------------------------------------------------------------------------------------
*/
\Craft\Application\App::initializeWeb(ROOT_DIR . '/public/logs/');

/*
| Boot the Craft web application
|------------------------------------------------------------------------------------------------
| This starts the application and handles the request.
| It returns the response to be sent to the client.
|------------------------------------------------------------------------------------------------
*/
return \Craft\Application\App::bootWeb();