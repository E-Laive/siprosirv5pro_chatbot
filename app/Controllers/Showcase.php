<?php
namespace App\Controllers;
use App\Services\PageRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;
class Showcase extends BaseController
{
    public function page(string $slug)
    {
        $page = config('TemplateCatalog')->find($slug);
        if (!$page) throw PageNotFoundException::forPageNotFound();
        if (!service('accessPolicy')->allows(service('accessPolicy')->pagePermission($slug))) return service('accessPolicy')->deny('page.' . $slug, $this->request, $this->response);
        return (new PageRenderer($this->request, $this->response))->render('pages/catalog/' . $page['view'], $page['title'], $page['subtitle'], $slug);
    }
}
