<?php

use App\Controller\WelcomeController;

use Craft\Application\Router;

$router = new Router();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method'])) {
    $_SERVER['REQUEST_METHOD'] = strtoupper($_POST['_method']);
}

$router->get('/', [WelcomeController::class, 'welcome'])->name('welcome');

$router->scanControllerAttributes([
    WelcomeController::class
]);

$router->run();