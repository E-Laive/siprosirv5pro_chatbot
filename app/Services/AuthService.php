<?php

namespace App\Services;

use App\Models\UserModel;

final class AuthService
{
    public function principal(): ?array
    {
        $session = session();
        $id = $session->get('auth_user_id');
        if (!is_numeric($id) || (int) $id < 1) return null;
        $user = (new UserModel())->find((int) $id);
        $now = time();
        $start = (int) $session->get('auth_logged_in_at');
        $last = (int) $session->get('auth_last_seen_at');
        $policy = config('AppSecurity');
        $valid = $user && $user['is_active'] && (int) $session->get('auth_version') === (int) $user['security_version']
            && $start > 0 && $last > 0 && $now >= $start && $now >= $last && $now - $start < $policy->absoluteTimeout && $now - $last < $policy->idleTimeout;
        if (!$valid) {
            service('auditLogger')->record('session.rejected', ['user_id' => (int) $id, 'reason' => 'inactive_missing_revoked_or_expired']);
            $session->remove(['auth_user_id', 'auth_display_name', 'auth_role', 'auth_logged_in_at', 'auth_last_seen_at', 'auth_version']);
            $session->regenerate(true);
            return null;
        }
        $session->set(['auth_display_name' => $user['display_name'], 'auth_role' => $user['role'], 'auth_last_seen_at' => $now]);
        return $user;
    }

    public function authenticate(array $post): array
    {
        $data = ['username' => $post['username'] ?? null, 'password' => $post['password'] ?? null];
        if (is_string($data['username'])) $data['username'] = strtolower(trim($data['username']));
        $validation = service('validation');
        $validation->reset();
        if (!$validation->setRules(['username' => 'required|max_length[80]|regex_match[/^[a-zA-Z0-9._-]+$/]', 'password' => 'required|max_length[128]'])->run($data)) return ['status' => 422, 'message' => 'Username dan password tidak valid.'];
        $model = new UserModel();
        $user = $model->where('username', $data['username'])->first();
        // Unknown accounts perform password verification too; no fast existence check.
        $dummy = self::algorithm() === PASSWORD_BCRYPT ? '$2y$12$KIXxGQw2Py.KvJnT1oXk5e3zFJdh9XS1wR6oGpU8uXWk5s4r9vDuK' : '$argon2id$v=19$m=65536,t=4,p=1$YVRKNk1LbVhOZmRaaEMwVQ$X9t+5RVwIXAmbM+SJRFA8ZP8vjCUs4jdCpx9xUmQR4c';
        $hash = $user['password_hash'] ?? $dummy;
        $oversizedLegacyPassword = str_starts_with($hash, '$2') && strlen($data['password']) > 72;
        $valid = password_verify($data['password'], $hash) && !$oversizedLegacyPassword;
        if (!$user || !$user['is_active'] || !$valid) {
            service('auditLogger')->record('login.failed', ['reason' => 'invalid_credentials']);
            return ['status' => 401, 'message' => 'Username atau password tidak sesuai.'];
        }
        $algorithm = self::algorithm();
        $updates = ['last_login_at' => date('Y-m-d H:i:s')];
        if (password_needs_rehash($hash, $algorithm)) {
            $updates['password_hash'] = self::hashPassword($data['password']);
            $updates['security_version'] = (int) $user['security_version'] + 1;
            $user['security_version'] = $updates['security_version'];
        }
        $model->update($user['id'], $updates);
        $model->builder()->set('login_count', 'login_count + 1', false)->where('id', $user['id'])->update();
        session()->regenerate(true);
        session()->set(['auth_user_id' => $user['id'], 'auth_display_name' => $user['display_name'], 'auth_role' => $user['role'], 'auth_version' => (int) $user['security_version'], 'auth_logged_in_at' => time(), 'auth_last_seen_at' => time()]);
        service('auditLogger')->record('login.success', ['user_id' => $user['id']]);
        return ['status' => 200, 'message' => 'Login berhasil.'];
    }

    public static function algorithm(): string|int { return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT; }
    public static function hashPassword(string $password): string
    {
        if (self::algorithm() === PASSWORD_BCRYPT && strlen($password) > 72) throw new \InvalidArgumentException('Runtime bcrypt mendukung maksimal 72 byte password.');
        return password_hash($password, self::algorithm());
    }
}
