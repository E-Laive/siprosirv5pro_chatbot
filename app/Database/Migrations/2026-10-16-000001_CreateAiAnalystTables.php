<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAiAnalystTables extends Migration
{
    public function up(): void
    {
        $id = ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true];
        $int = ['type' => 'INTEGER', 'unsigned' => true];
        $text = ['type' => 'TEXT'];
        $json = ['type' => 'LONGTEXT'];
        $date = ['type' => 'DATETIME', 'null' => true];
        $this->forge->addField([
            'id' => $id, 'conversation_uid' => ['type' => 'VARCHAR', 'constraint' => 40], 'user_id' => $int,
            'title' => ['type' => 'VARCHAR', 'constraint' => 150], 'active_context_json' => $json,
            'summary_text' => $text, 'message_count' => $int + ['default' => 0], 'is_archived' => ['type' => 'BOOLEAN', 'default' => false],
            'created_at' => $date, 'updated_at' => $date,
        ]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey('conversation_uid'); $this->forge->addKey(['user_id', 'updated_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('ai_conversations', true);
        $this->forge->addField(['id' => $id, 'conversation_id' => $int, 'role' => ['type' => 'VARCHAR', 'constraint' => 20], 'content' => $json, 'blocks_json' => $json, 'metadata_json' => $json, 'created_at' => $date]);
        $this->forge->addKey('id', true); $this->forge->addKey(['conversation_id', 'id']);
        $this->forge->addForeignKey('conversation_id', 'ai_conversations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ai_messages', true);
        $this->forge->addField([
            'id' => $id, 'provider_name' => ['type' => 'VARCHAR', 'constraint' => 60], 'base_url' => ['type' => 'VARCHAR', 'constraint' => 255],
            'model_id' => ['type' => 'VARCHAR', 'constraint' => 100], 'temperature' => ['type' => 'DECIMAL', 'constraint' => '4,2', 'default' => 0.1],
            'max_tokens' => $int, 'timeout_seconds' => $int, 'streaming_enabled' => ['type' => 'BOOLEAN', 'default' => false],
            'tools_enabled' => ['type' => 'BOOLEAN', 'default' => false], 'updated_by' => $int, 'updated_at' => $date,
        ]);
        $this->forge->addKey('id', true); $this->forge->createTable('ai_provider_configs', true);
        $this->forge->addField([
            'id' => $id, 'conversation_id' => $int + ['null' => true], 'user_id' => $int,
            'user_role' => ['type' => 'VARCHAR', 'constraint' => 40], 'domain' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'sql_query' => $text, 'bindings_json' => $text, 'row_count' => $int, 'duration_ms' => $int,
            'status' => ['type' => 'VARCHAR', 'constraint' => 20], 'error_message' => $text + ['null' => true], 'created_at' => $date,
        ]);
        $this->forge->addKey('id', true); $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addForeignKey('conversation_id', 'ai_conversations', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('ai_query_logs', true);
    }
    public function down(): void
    {
        foreach (['ai_query_logs', 'ai_provider_configs', 'ai_messages', 'ai_conversations'] as $table) $this->forge->dropTable($table, true);
    }
}
