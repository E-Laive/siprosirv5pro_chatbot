<?php

namespace App\Services;

use CodeIgniter\HTTP\ResponseInterface;

final class PageRenderer
{
    public function __construct(private \CodeIgniter\HTTP\RequestInterface $request, private ResponseInterface $response) {}

    /**
     * @param array<string, mixed> $extraData
     */
    public function render(string $view, string $title, string $subtitle, string $activeMenu, array $extraData = []): ResponseInterface
    {
        helper(['form', 'url']);
        $data = $extraData + [
            'title' => $title,
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle,
            'activeMenu' => $activeMenu,
            'user' => [
                'id' => (int) session('auth_user_id'),
                'name' => (string) session('auth_display_name'),
                'role' => (string) session('auth_role'),
            ],
        ];
        $response = $this->response->setHeader('Cache-Control', 'private, no-store');
        $request = $this->request;
        if ($request->isAJAX() || $request->getHeaderLine('X-SPA-Request') === '1') {
            return $response->setJSON([
                'success' => true,
                'title' => $title,
                'pageTitle' => $title,
                'pageSubtitle' => $subtitle,
                'activeMenu' => $activeMenu,
                'url' => current_url(),
                'content' => view($view, $data),
                'csrf_token' => csrf_hash(),
            ]);
        }
        return $response->setBody(view('layout/main', $data + ['contentView' => $view]));
    }
}
