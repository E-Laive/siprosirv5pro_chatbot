<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!$arguments || !service('accessPolicy')->allows($arguments[0])) {
            return service('accessPolicy')->deny($arguments[0] ?? 'unknown', $request, service('response'));
        }
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void {}
}
