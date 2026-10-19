<?php
namespace App\Controllers;
use App\Services\PageRenderer;
class Dashboard extends BaseController
{
    public function index() { return (new PageRenderer($this->request, $this->response))->render('pages/dashboard', 'Dashboard', 'Komponen, pola halaman, dan ekspor data contoh', 'dashboard'); }
}
