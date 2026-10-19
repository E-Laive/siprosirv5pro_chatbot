<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddSecurityVersion extends Migration
{
    public function up(): void { if (!$this->db->fieldExists('security_version', 'users')) $this->forge->addColumn('users', ['security_version' => ['type' => 'INTEGER', 'unsigned' => true, 'default' => 1]]); }
    public function down(): void { if ($this->db->fieldExists('security_version', 'users')) $this->forge->dropColumn('users', 'security_version'); }
}
