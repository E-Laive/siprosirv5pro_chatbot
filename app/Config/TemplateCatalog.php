<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class TemplateCatalog extends BaseConfig
{
    /** @var list<array{label:string,icon:string,items:list<array{slug:string,label:string,icon:string,title:string,subtitle:string,view:string}>}> */
    public array $groups = [
        ['label' => 'Fondasi', 'icon' => 'bi-palette', 'items' => [
            ['slug' => 'warna-tema', 'label' => 'Warna & Tema', 'icon' => 'bi-circle-half', 'title' => 'Warna & Tema', 'subtitle' => 'Token visual untuk tema terang dan gelap', 'view' => 'foundations_colors'],
            ['slug' => 'tipografi-ikon', 'label' => 'Tipografi & Ikon', 'icon' => 'bi-fonts', 'title' => 'Tipografi & Ikon', 'subtitle' => 'Hierarki teks, ikon, spacing, dan grid', 'view' => 'foundations_typography'],
        ]],
        ['label' => 'Komponen', 'icon' => 'bi-boxes', 'items' => [
            ['slug' => 'button-badge', 'label' => 'Button & Badge', 'icon' => 'bi-ui-radios-grid', 'title' => 'Button & Badge', 'subtitle' => 'Aksi, status, ukuran, dan kondisi komponen', 'view' => 'components_actions'],
            ['slug' => 'card-media', 'label' => 'Card & Media', 'icon' => 'bi-card-heading', 'title' => 'Card & Media', 'subtitle' => 'Card, avatar, gambar, dan statistik', 'view' => 'components_cards'],
            ['slug' => 'progress-indicator', 'label' => 'Progress & Indicator', 'icon' => 'bi-bar-chart-steps', 'title' => 'Progress & Indicator', 'subtitle' => 'Progress bar, spinner, skeleton, dan status', 'view' => 'components_progress'],
        ]],
        ['label' => 'Form', 'icon' => 'bi-input-cursor-text', 'items' => [
            ['slug' => 'elemen-form', 'label' => 'Elemen Form', 'icon' => 'bi-ui-checks', 'title' => 'Elemen Form', 'subtitle' => 'Input, select, choice, switch, dan upload', 'view' => 'forms_controls'],
            ['slug' => 'validasi-form', 'label' => 'Validasi Form', 'icon' => 'bi-check2-square', 'title' => 'Validasi Form', 'subtitle' => 'Contoh validasi dan kondisi field lengkap', 'view' => 'forms_validation'],
            ['slug' => 'form-lanjutan', 'label' => 'Form Lanjutan', 'icon' => 'bi-calendar-range', 'title' => 'Form Lanjutan', 'subtitle' => 'Rentang tanggal, rupiah, pemilih dan preview gambar', 'view' => 'forms_advanced'],
        ]],
        ['label' => 'Data', 'icon' => 'bi-database', 'items' => [
            ['slug' => 'tabel-data', 'label' => 'Tabel Data', 'icon' => 'bi-table', 'title' => 'Tabel Data', 'subtitle' => 'Tabel empat data dengan filter dan aksi', 'view' => 'data_tables'],
            ['slug' => 'list-detail', 'label' => 'List & Detail', 'icon' => 'bi-list-ul', 'title' => 'List & Detail', 'subtitle' => 'List group, timeline, dan detail record', 'view' => 'data_lists'],
        ]],
        ['label' => 'Chart', 'icon' => 'bi-graph-up-arrow', 'items' => [
            ['slug' => 'chart', 'label' => 'Koleksi Chart', 'icon' => 'bi-pie-chart', 'title' => 'Koleksi Chart', 'subtitle' => 'Bar, line, area, pie, dan doughnut chart', 'view' => 'charts'],
        ]],
        ['label' => 'Navigasi', 'icon' => 'bi-signpost-split', 'items' => [
            ['slug' => 'navigasi', 'label' => 'Navigasi', 'icon' => 'bi-menu-button-wide', 'title' => 'Navigasi', 'subtitle' => 'Tabs, breadcrumb, dropdown, dan pagination', 'view' => 'navigation'],
        ]],
        ['label' => 'Feedback', 'icon' => 'bi-bell', 'items' => [
            ['slug' => 'galeri-error', 'label' => 'Galeri Halaman Error', 'icon' => 'bi-window-x', 'title' => 'Galeri Halaman Error', 'subtitle' => 'Pilih kondisi dan lihat halaman pemulihan yang sesuai', 'view' => 'error_gallery'],
            ['slug' => 'feedback', 'label' => 'Feedback', 'icon' => 'bi-chat-square-dots', 'title' => 'Feedback', 'subtitle' => 'Alert, toast, modal, tooltip, dan empty state', 'view' => 'feedback'],
            ['slug' => 'status-aplikasi', 'label' => 'Status & Pemulihan', 'icon' => 'bi-arrow-clockwise', 'title' => 'Status & Pemulihan', 'subtitle' => 'Contoh error, offline, izin akses, sesi dan coba lagi', 'view' => 'recovery'],
        ]],
        ['label' => 'Template Halaman', 'icon' => 'bi-window-stack', 'items' => [
            ['slug' => 'template-dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'title' => 'Template Dashboard', 'subtitle' => 'Susunan dashboard statistik siap pakai', 'view' => 'template_dashboard'],
            ['slug' => 'template-crud', 'label' => 'Daftar & CRUD', 'icon' => 'bi-layout-text-window-reverse', 'title' => 'Template Daftar & CRUD', 'subtitle' => 'Daftar empat data, filter, status, dan aksi', 'view' => 'template_crud'],
            ['slug' => 'template-form', 'label' => 'Form & Detail', 'icon' => 'bi-file-earmark-text', 'title' => 'Template Form & Detail', 'subtitle' => 'Form editor, detail data, dan pengaturan', 'view' => 'template_form'],
            ['slug' => 'cetak-dokumen', 'label' => 'Invoice & Struk', 'icon' => 'bi-printer', 'title' => 'Invoice & Struk', 'subtitle' => 'Pratinjau invoice A4 dan struk 58/80 mm', 'view' => 'print_documents'],
        ]],
    ];

    /** @return array{slug:string,label:string,icon:string,title:string,subtitle:string,view:string}|null */
    public function find(string $slug): ?array
    {
        foreach ($this->groups as $group) {
            foreach ($group['items'] as $item) {
                if ($item['slug'] === $slug) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function pageCount(): int
    {
        return array_sum(array_map(static fn (array $group): int => count($group['items']), $this->groups));
    }
}
