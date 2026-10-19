<?php

namespace App\Services;

final class AccessPolicy
{
    public function deny(string $resource, \CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response): \CodeIgniter\HTTP\ResponseInterface
    {
        service('auditLogger')->record('access.denied', ['user_id' => (int) session('auth_user_id'), 'resource' => $resource]);
        $response->setStatusCode(403);
        if ($request->isAJAX() || $request->getHeaderLine('X-SPA-Request') === '1') return $response->setJSON(['success' => false, 'message' => 'Anda tidak memiliki izin untuk tindakan ini.']);
        return $response->setBody(view('errors/html/error_403'));
    }
    public function allows(string $permission, ?string $role = null): bool
    {
        $grants = config('AppSecurity')->permissions[$role ?? (string) session('auth_role')] ?? [];
        return in_array('*', $grants, true) || in_array($permission, $grants, true);
    }

    public function pagePermission(string $slug): string
    {
        return match ($slug) { 'template-crud' => 'users.view', 'cetak-dokumen' => 'documents.view', default => 'catalog.view' };
    }

    public function groups(): array
    {
        $groups = config('TemplateCatalog')->groups;
        foreach ($groups as &$group) $group['items'] = array_values(array_filter($group['items'], fn($item) => $this->allows($this->pagePermission($item['slug']))));
        unset($group);
        return array_values(array_filter($groups, static fn($group) => $group['items'] !== []));
    }
}
