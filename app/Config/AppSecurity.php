<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class AppSecurity extends BaseConfig
{
    public int $idleTimeout = 1800;
    public int $absoluteTimeout = 28800;
    public int $maxExportRows = 5000;
    public int $maxPdfRows = 1000;
    public int $maxConcurrentExports = 2;
    public array $permissions = [
        'Administrator' => ['*'],
        'Kasir' => [
            'catalog.view',
            'documents.view',
            'export.produk',
            'export.pelanggan',
            'export.transaksi',
            'ai.chat',
            'ai.export',
            'ai.domain.sales',
            'ai.domain.returns_receivables',
            'ai.domain.products_inventory',
            'ai.domain.members',
        ],
        'Gudang' => [
            'catalog.view',
            'export.produk',
            'ai.chat',
            'ai.export',
            'ai.domain.products_inventory',
            'ai.domain.purchasing_payables',
        ],
    ];
}
