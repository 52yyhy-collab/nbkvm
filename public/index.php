<?php

declare(strict_types=1);
session_start();
require dirname(__DIR__) . '/src/Support/helpers.php';
require dirname(__DIR__) . '/src/Support/Autoload.php';
Nbkvm\Support\Autoload::register();
use Nbkvm\Controllers\DashboardController;
use Nbkvm\Controllers\ImageController;
use Nbkvm\Controllers\TemplateController;
use Nbkvm\Controllers\VmController;
use Nbkvm\Support\Request;
use Nbkvm\Support\Router;
$router = new Router();
$router->get('/', [DashboardController::class, 'index']);
$router->post('/images', [ImageController::class, 'store']);
$router->post('/templates', [TemplateController::class, 'store']);
$router->post('/vms', [VmController::class, 'store']);
$router->post('/vms/start', [VmController::class, 'start']);
$router->post('/vms/shutdown', [VmController::class, 'shutdown']);
$router->post('/vms/destroy', [VmController::class, 'destroy']);
$router->post('/vms/delete', [VmController::class, 'delete']);
$router->dispatch(new Request());
