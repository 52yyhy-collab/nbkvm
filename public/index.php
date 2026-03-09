<?php

declare(strict_types=1);

session_name((string) (require dirname(__DIR__) . '/config/app.php')['auth']['session_name']);
session_start();

require dirname(__DIR__) . '/src/Support/helpers.php';
require dirname(__DIR__) . '/src/Support/Autoload.php';

Nbkvm\Support\Autoload::register();
(new Nbkvm\Services\SchemaService())->ensure();

use Nbkvm\Controllers\AuthController;
use Nbkvm\Controllers\ConsoleController;
use Nbkvm\Controllers\DashboardController;
use Nbkvm\Controllers\ImageController;
use Nbkvm\Controllers\ImageConvertController;
use Nbkvm\Controllers\IpPoolController;
use Nbkvm\Controllers\NetworkController;
use Nbkvm\Controllers\NodeNetworkResourceController;
use Nbkvm\Controllers\NoVncController;
use Nbkvm\Controllers\SettingController;
use Nbkvm\Controllers\SnapshotController;
use Nbkvm\Controllers\TemplateController;
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
$router->post('/settings', [SettingController::class, 'update']);
$router->post('/users', [UserController::class, 'store']);
$router->post('/users/role', [UserController::class, 'updateRole']);
$router->post('/users/delete', [UserController::class, 'delete']);

$router->get('/novnc/open', [NoVncController::class, 'open']);
$router->post('/novnc/start', [NoVncController::class, 'start']);
$router->post('/novnc/stop', [NoVncController::class, 'stop']);

$router->get('/console/open', [ConsoleController::class, 'open']);
$router->get('/console/status', [ConsoleController::class, 'status']);
$router->post('/console/start', [ConsoleController::class, 'start']);
$router->post('/console/send', [ConsoleController::class, 'send']);
$router->post('/console/stop', [ConsoleController::class, 'stop']);

$router->get('/', [DashboardController::class, 'index']);
$router->get('/vm', [VmDetailController::class, 'show']);

$router->post('/images', [ImageController::class, 'store']);
$router->post('/images/delete', [ImageController::class, 'delete']);
$router->post('/images/convert', [ImageConvertController::class, 'convert']);

$router->post('/networks', [NetworkController::class, 'store']);
$router->post('/networks/delete', [NetworkController::class, 'delete']);
$router->post('/node-network-resources', [NodeNetworkResourceController::class, 'store']);
$router->post('/node-network-resources/apply', [NodeNetworkResourceController::class, 'apply']);
$router->post('/node-network-resources/remove-host', [NodeNetworkResourceController::class, 'removeHost']);
$router->post('/node-network-resources/delete', [NodeNetworkResourceController::class, 'delete']);
$router->post('/ip-pools', [IpPoolController::class, 'store']);
$router->post('/ip-pools/delete', [IpPoolController::class, 'delete']);

$router->post('/templates', [TemplateController::class, 'store']);
$router->post('/templates/delete', [TemplateController::class, 'delete']);

$router->post('/vms', [VmController::class, 'store']);
$router->post('/vms/update', [VmController::class, 'update']);
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
