<?php

namespace App\Services;

use App\Models\UserModel;
use RuntimeException;

/** Provisioning is invoked only by CLI, serialized independently of usernames. */
final class SetupService
{
    public function createAdministrator(array $input): int
    {
        $data = array_intersect_key($input, array_flip(['display_name', 'username', 'password', 'password_confirm']));
        foreach (['display_name', 'username'] as $key) if (isset($data[$key]) && is_string($data[$key])) $data[$key] = trim($data[$key]);
        if (isset($data['username']) && is_string($data['username'])) $data['username'] = strtolower($data['username']);
        $validator = service('validation'); $validator->reset();
        if (!$validator->setRules(['display_name' => 'required|min_length[3]|max_length[120]', 'username' => 'required|min_length[4]|max_length[80]|regex_match[/^[a-zA-Z0-9._-]+$/]', 'password' => 'required|min_length[12]|max_length[128]', 'password_confirm' => 'required|matches[password]'])->run($data)) throw new \InvalidArgumentException('Nama/username/password tidak memenuhi aturan atau konfirmasi berbeda.');
        $hash = AuthService::hashPassword($data['password']);
        $lock = @fopen(WRITEPATH . 'setup.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Kunci provisioning tidak tersedia.');
        try {
            $db = db_connect();
            if (!$db->tableExists('users')) throw new RuntimeException('Jalankan migration terlebih dahulu.');
            $db->transException(true)->transStart();
            if ($db->table('users')->countAllResults() !== 0) { $db->transRollback(); throw new RuntimeException('Administrator pertama sudah tersedia.'); }
            $model = new UserModel();
            $id = $model->insert(['display_name' => $data['display_name'], 'username' => $data['username'], 'password_hash' => $hash, 'role' => 'Administrator', 'is_active' => true, 'security_version' => 1], true);
            if (!$id) { $db->transRollback(); throw new RuntimeException('Provisioning gagal.'); }
            $db->transComplete();
            service('auditLogger')->record('setup.completed', ['user_id' => (int) $id]);
            return (int) $id;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
