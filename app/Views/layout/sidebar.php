<?php
$activeMenu ??= 'dashboard';
$initial = strtoupper(substr((string) $user['name'], 0, 1));
?>
<aside class="sidebar" id="sidebar">
    <a class="sidebar-brand spa-link" href="<?= site_url('asisten-ai') ?>" aria-label="SIPROSIR, AI ASISTAN">
        <span class="brand-mark brand-mark-logo"><img src="<?= base_url('assets/img/siprosir-logo.png') ?>" alt=""></span>
        <span class="brand-text"><strong>SIPROSIR PRO V5</strong><small>SMART POS SYSTEM</small></span>
    </a>
    <nav class="sidebar-scroll" aria-label="Navigasi utama">
        <p class="sidebar-section-label">MENU UTAMA</p>
        <ul class="sidebar-nav">
            <?php if (service('accessPolicy')->allows('ai.chat')): ?>
            <li><a class="nav-link spa-link <?= $activeMenu === 'asisten-ai' ? 'active-menu' : '' ?>" data-menu="asisten-ai" href="<?= site_url('asisten-ai') ?>" aria-label="AI ASISTAN"><i class="bi bi-stars" aria-hidden="true"></i><span>AI ASISTAN</span></a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <div class="sidebar-user"><span class="user-initial"><?= esc($initial) ?></span><span class="user-copy"><strong><?= esc($user['name']) ?></strong><small><?= esc(strtoupper($user['role'])) ?></small></span></div>
</aside>
