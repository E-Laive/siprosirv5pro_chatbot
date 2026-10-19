<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('authService')->principal()) return null;
        if ($request->isAJAX() || $request->getHeaderLine('X-SPA-Request') === '1') return service('response')->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Sesi Anda telah berakhir.', 'redirect' => site_url('login')]);
        return redirect()->to(site_url('login'));
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void {}
}
