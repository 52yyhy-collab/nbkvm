<?php

declare(strict_types=1);
session_name((string) (require dirname(__DIR__) . '/config/app.php')['auth']['session_name']);
session_start();
require dirname(__DIR__) . '/src/Support/helpers.php';
require dirname(__DIR__) . '/src/Support/Autoload.php';
Nbkvm\Support\Autoload::register();
use Nbkvm\Controllers\AuthController;
use Nbkvm\Controllers\DashboardController;
use Nbkvm\Controllers\ImageController;
use Nbkvm\Controllers\SnapshotController;
use Nbkvm\Controllers\TemplateController;
use Nbkvm\Controllers\NoVncController;
use Nbkvm\Controllers\UserController;
use Nbkvm\Controllers\VmController;
use Nbkvm\Controllers\VmDetailController;
use Nbkvm\Support\Request;
use Nbkvm\Support\Router;
$router = new Router();
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->post('/password', [AuthController::class, 'changePassword']);
$router->post('/users', [UserController::class, 'store']);
$router->post('/users/delete', [UserController::class, 'delete']);
$router->post('/novnc/start', [NoVncController::class, 'start']);
$router->post('/novnc/stop', [NoVncController::class, 'stop']);
$router->get('/', [DashboardController::class, 'index']);
$router->get('/vm', [VmDetailController::class, 'show']);
$router->post('/images', [ImageController::class, 'store']);
$router->post('/templates', [TemplateController::class, 'store']);
$router->post('/vms', [VmController::class, 'store']);
$router->post('/vms/start', [VmController::class, 'start']);
$router->post('/vms/shutdown', [VmController::class, 'shutdown']);
$router->post('/vms/destroy', [VmController::class, 'destroy']);
$router->post('/vms/delete', [VmController::class, 'delete']);
$router->post('/snapshots', [SnapshotController::class, 'store']);
$router->post('/snapshots/revert', [SnapshotController::class, 'revert']);
$router->post('/snapshots/delete', [SnapshotController::class, 'delete']);
$request = new Request();
$path = $request->path();
if (!auth_check() && !in_array($path, ['/login'], true)) {
    redirect('/login');
}
if (auth_check() && $path === '/login' && $request->method() === 'GET') {
    redirect('/');
}
$router->dispatch($request);
