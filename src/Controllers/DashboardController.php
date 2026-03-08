<?php

declare(strict_types=1);
namespace Nbkvm\Controllers;
use Nbkvm\Repositories\ImageRepository;
use Nbkvm\Repositories\TemplateRepository;
use Nbkvm\Repositories\VmRepository;
use Nbkvm\Services\VmService;
use Nbkvm\Support\BaseController;
use Nbkvm\Support\Request;
class DashboardController extends BaseController
{
    public function index(Request $request): void
    {
        (new VmService())->refreshStates();
        $this->view('dashboard', [
            'images' => (new ImageRepository())->all(),
            'templates' => (new TemplateRepository())->all(),
            'vms' => (new VmRepository())->all(),
            'libvirtAvailable' => function_exists('libvirt_connect'),
        ]);
    }
}
