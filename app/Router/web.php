<?php

use Craft\Application\Router;
use Craft\Application\View;

$router = new Router();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method'])) {
    $_SERVER['REQUEST_METHOD'] = strtoupper($_POST['_method']);
}

$router->get('/', function() {
    return View::render('welcome');
})->name('home');

$router->run();