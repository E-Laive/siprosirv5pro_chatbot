<?php
namespace App\Controllers;
use CodeIgniter\HTTP\ResponseInterface;
class Auth extends BaseController
{
    protected $helpers = ['form', 'url'];
    public function setup(): ResponseInterface { return $this->response->setStatusCode(403)->setBody(view('auth/setup', ['title' => 'Provisioning Administrator'])); }
    public function createFirstUser(): ResponseInterface
    {
        service('auditLogger')->record('setup.web_denied');
        return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Setup web ditutup. Gunakan provisioning CLI.']);
    }
    public function login(): ResponseInterface
    {
        if (service('authService')->principal()) return redirect()->to(site_url('asisten-ai'));
        return $this->response->setHeader('Cache-Control', 'no-store')->setBody(view('auth/login', ['title' => 'Login Kerangka SIPROSIR']));
    }
    public function authenticate(): ResponseInterface
    {
        $post = $this->request->getPost();
        $username = is_string($post['username'] ?? null) ? strtolower(trim($post['username'])) : '';
        try {
            $limiter = service('securityRateLimiter');
            $retry = $limiter->check('login-ip:' . $this->request->getIPAddress(), 5, 60);
            if (!$retry) $retry = $limiter->check('login-account:' . $username, 15, 900);
        } catch (\Throwable) {
            service('auditLogger')->record('security.storage_unavailable', ['resource' => 'login']);
            return $this->respond(503, 'Layanan keamanan belum tersedia. Coba kembali nanti.', 60);
        }
        if ($retry) {
            service('auditLogger')->record('login.limited', ['retry_after' => $retry]);
            return $this->respond(429, 'Terlalu banyak percobaan. Tunggu sebentar lalu coba kembali.', $retry);
        }
        $result = service('authService')->authenticate($post);
        return $this->respond($result['status'], $result['message']);
    }
    public function logout(): ResponseInterface
    {
        service('auditLogger')->record('logout', ['user_id' => (int) session('auth_user_id')]);
        session()->remove(['auth_user_id', 'auth_display_name', 'auth_role', 'auth_version', 'auth_logged_in_at', 'auth_last_seen_at']);
        session()->destroy();
        return redirect()->to(site_url('login'));
    }
    private function respond(int $status, string $message, int $retry = 0): ResponseInterface
    {
        if ($retry) $this->response->setHeader('Retry-After', (string) $retry);
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store')->setJSON(['success' => $status === 200, 'message' => $message, 'redirect' => $status === 200 ? site_url('asisten-ai') : null, 'retry_after' => $retry, 'csrf_token' => csrf_hash()]);
    }
}
