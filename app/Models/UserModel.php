<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'username',
        'password_hash',
        'display_name',
        'role',
        'is_active',
        'login_count',
        'last_login_at',
        'security_version',
    ];

    protected array $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'login_count' => 'integer',
        'security_version' => 'integer',
    ];

    protected $validationRules = [
        'username' => 'required|min_length[4]|max_length[80]|regex_match[/^[a-zA-Z0-9._-]+$/]',
        'display_name' => 'required|min_length[3]|max_length[120]',
        'password_hash' => 'required|min_length[50]|max_length[255]',
        'role' => 'required|in_list[Administrator,Kasir,Gudang]',
    ];
    protected $beforeUpdate = ['revokeOnSecurityChange'];

    protected function revokeOnSecurityChange(array $event): array
    {
        if (isset($event['data']['security_version']) || !array_intersect(['password_hash', 'role', 'is_active'], array_keys($event['data']))) return $event;
        $ids = (array) ($event['id'] ?? []);
        if (count($ids) !== 1) throw new \InvalidArgumentException('Update keamanan harus dilakukan per pengguna.');
        $current = $this->find($ids[0]);
        if ($current) $event['data']['security_version'] = (int) $current['security_version'] + 1;
        return $event;
    }
}
