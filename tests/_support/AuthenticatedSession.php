<?php
namespace Tests\Support;
trait AuthenticatedSession
{
    protected function setUpAuthenticatedSession(): void
    {
        $this->securityTestDirectory = sys_get_temp_dir() . '/kerangka-security-' . bin2hex(random_bytes(8));
        \Config\Services::injectMock('securityRateLimiter', new \App\Services\SecurityRateLimiter($this->securityTestDirectory));
    }
    protected string $securityTestDirectory;
    protected function tearDownAuthenticatedSession(): void
    {
        foreach (glob($this->securityTestDirectory . '/*') ?: [] as $file) unlink($file);
        if (is_dir($this->securityTestDirectory)) rmdir($this->securityTestDirectory);
    }
    protected function asUser(array $values): static
    {
        $model = new \App\Models\UserModel();
        $user = $model->find((int) ($values['auth_user_id'] ?? 1));
        if (!$user) {
            $id = $model->insert(['username' => 'test.user', 'display_name' => $values['auth_display_name'] ?? 'Test User', 'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT), 'role' => $values['auth_role'] ?? 'Administrator', 'is_active' => true], true);
            $user = $model->find($id);
        }
        return $this->withSession($values + ['auth_user_id' => $user['id'], 'auth_version' => (int) $user['security_version'], 'auth_logged_in_at' => time(), 'auth_last_seen_at' => time()]);
    }
}
