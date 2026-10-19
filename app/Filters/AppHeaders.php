<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class AppHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) {}
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN')->setHeader('X-Content-Type-Options', 'nosniff')->setHeader('Referrer-Policy', 'same-origin')->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')->setHeader('Cache-Control', 'private, no-store');
        // Scripts are external/self only. Bootstrap/SVG examples need style attributes.
        $response->setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        if ($request instanceof \CodeIgniter\HTTP\IncomingRequest && $request->isSecure()) $response->setHeader('Strict-Transport-Security', 'max-age=31536000');
        $response->removeHeader('X-Powered-By');
        return $response;
    }
}
