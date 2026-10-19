<?php

namespace Config;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function authService($getShared = true) { return $getShared ? static::getSharedInstance('authService') : new \App\Services\AuthService(); }
    public static function auditLogger($getShared = true) { return $getShared ? static::getSharedInstance('auditLogger') : new \App\Services\AuditLogger(); }
    public static function securityRateLimiter($getShared = true) { return $getShared ? static::getSharedInstance('securityRateLimiter') : new \App\Services\SecurityRateLimiter(); }
    public static function accessPolicy($getShared = true) { return $getShared ? static::getSharedInstance('accessPolicy') : new \App\Services\AccessPolicy(); }

    public static function posSchemaService($getShared = true) { return $getShared ? static::getSharedInstance('posSchemaService') : new \App\Services\AI\PosSchemaService(); }
    public static function businessSemanticLayer($getShared = true) { return $getShared ? static::getSharedInstance('businessSemanticLayer') : new \App\Services\AI\BusinessSemanticLayer(); }
    public static function sqlSafetyValidator($getShared = true) { return $getShared ? static::getSharedInstance('sqlSafetyValidator') : new \App\Services\AI\SqlSafetyValidator(); }
    public static function posQueryExecutor($getShared = true) { return $getShared ? static::getSharedInstance('posQueryExecutor') : new \App\Services\AI\PosQueryExecutor(); }
    public static function conversationService($getShared = true) { return $getShared ? static::getSharedInstance('conversationService') : new \App\Services\AI\ConversationService(); }
    public static function aiProviderClient($getShared = true) { return $getShared ? static::getSharedInstance('aiProviderClient') : new \App\Services\AI\AiProviderClient(); }
    public static function aiOrchestrator($getShared = true) { return $getShared ? static::getSharedInstance('aiOrchestrator') : new \App\Services\AI\AiOrchestrator(); }
}
