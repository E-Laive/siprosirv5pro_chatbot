<?php
$conversations ??= [];
$activeConversation ??= null;
$allowedDomains ??= [];
$posStatus ??= ['connected' => true, 'mode' => 'pos_verification_fixture', 'database' => 'dbsiprosir_online_v5_x2', 'message' => 'Siap menganalisis'];
$providerConfig ??= ['provider_name' => 'openai-compatible', 'base_url' => 'https://api.openai.com/v1', 'model_id' => 'gpt-4o-mini', 'temperature' => 0.1, 'max_tokens' => 2048, 'timeout_seconds' => 25, 'streaming_enabled' => true, 'tools_enabled' => true, 'has_api_key' => false, 'api_key_masked' => 'Belum diatur'];
$isAdmin = ($user['role'] ?? '') === 'Administrator';
$aiEndpointPath = parse_url(site_url('asisten-ai'), PHP_URL_PATH) ?: '/asisten-ai';

$domainBadges = [
    'sales' => 'Penjualan & Laba',
    'returns_receivables' => 'Retur & Piutang',
    'products_inventory' => 'Produk & Stok',
    'members' => 'Member',
    'purchasing_payables' => 'Pembelian & Supplier',
    'cash_finance' => 'Kas & Bank',
    'hr_payroll' => 'SDM & Gaji',
];
?>
<section class="ai-workspace" id="aiWorkspace" data-ai-workspace
 data-show-query-audit="<?= ENVIRONMENT === 'production' ? '0' : '1' ?>"
 data-endpoint-base="<?= esc(rtrim($aiEndpointPath, '/'), 'attr') ?>"
 data-active-uid="<?= esc((string) ($activeConversation['conversation_uid'] ?? ''), 'attr') ?>"
 data-initial-conversation="<?= esc(json_encode($activeConversation, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>"
 data-user-role="<?= esc((string) ($user['role'] ?? ''), 'attr') ?>">
 <aside class="ai-sidebar" id="aiSidebar" aria-label="Riwayat percakapan AI">
  <div class="ai-sidebar-top">
   <div class="d-flex gap-2"><button type="button" class="btn btn-primary flex-grow-1" id="btnNewAiChat"><i class="bi bi-plus-lg" aria-hidden="true"></i> Percakapan Baru</button><button type="button" class="btn btn-soft d-lg-none" data-ai-close-history aria-label="Tutup riwayat"><i class="bi bi-x-lg"></i></button></div>
   <label class="visually-hidden" for="aiHistorySearchInput">Cari riwayat</label><input type="search" class="form-control form-control-sm" id="aiHistorySearchInput" placeholder="Cari percakapan..." autocomplete="off">
  </div>
  <div class="ai-conversation-list" id="aiConversationList" role="list">
  <?php if ($conversations === []): ?><p class="ai-history-empty" id="aiHistoryEmpty">Percakapan Anda akan tersimpan di sini.</p><?php endif ?>
  <?php foreach ($conversations as $conv): ?>
   <div class="ai-conv-item <?= ($activeConversation['conversation_uid'] ?? '') === $conv['uid'] ? 'active' : '' ?>" data-conv-item="<?= esc($conv['uid'], 'attr') ?>" role="listitem">
    <button type="button" class="ai-conv-select" data-conv-select="<?= esc($conv['uid'], 'attr') ?>"><span class="ai-conv-copy"><strong data-conv-title><?= esc($conv['title']) ?></strong><small><?= (int) $conv['message_count'] ?> pesan</small></span></button>
    <div class="dropdown ai-conv-actions"><button type="button" class="icon-button" data-bs-toggle="dropdown" aria-label="Tindakan percakapan <?= esc($conv['title'], 'attr') ?>"><i class="bi bi-three-dots"></i></button><div class="dropdown-menu dropdown-menu-end">
      <button type="button" class="dropdown-item" data-conv-rename="<?= esc($conv['uid'], 'attr') ?>" data-conv-current-title="<?= esc($conv['title'], 'attr') ?>">Ganti nama</button>
      <button type="button" class="dropdown-item text-danger" data-conv-delete="<?= esc($conv['uid'], 'attr') ?>">Hapus percakapan</button>
    </div></div>
   </div>
  <?php endforeach ?>
  </div>
 </aside>
 <div class="ai-main">
  <header class="ai-main-header">
   <div class="ai-main-title-wrap">
    <button type="button" class="btn btn-soft btn-sm d-lg-none" id="btnToggleAiSidebar" aria-label="Buka riwayat" aria-expanded="false"><i class="bi bi-clock-history"></i></button>
    <div><h2 id="aiActiveTitle"><?= esc((string) ($activeConversation['title'] ?? 'AI ASISTAN')) ?></h2><small class="text-muted" id="aiSourceStatus"><?= esc($posStatus['mode'] === 'live_pos_db' ? 'POS live' : ($posStatus['mode'] === 'pos_verification_fixture' ? 'Data simulasi' : 'POS offline')) ?></small><span id="aiActiveContextBadge" hidden></span></div>
   </div>
   <div class="d-flex gap-2 align-items-center">
    <div class="dropdown" id="aiExportToolbar" <?= empty($activeConversation['context']['last_rows']) ? 'hidden' : '' ?>>
     <button type="button" class="btn btn-soft btn-sm" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download"></i> Ekspor</button>
     <div class="dropdown-menu dropdown-menu-end"><span class="dropdown-header">Hasil terakhir yang berhasil</span>
     <?php foreach (['csv' => 'CSV', 'excel' => 'Excel', 'pdf' => 'PDF'] as $format => $label): ?>
      <a class="dropdown-item" data-ai-export="<?= $format ?>" href="<?= esc(rtrim($aiEndpointPath, '/') . '/export/' . rawurlencode((string) ($activeConversation['conversation_uid'] ?? 'none')) . '/' . $format, 'attr') ?>" download><?= $label ?></a>
     <?php endforeach ?>
     </div>
    </div>
    <button class="btn btn-soft btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#aiInformationModal" aria-label="Informasi AI ASISTAN"><i class="bi bi-info-circle"></i><span class="d-none d-sm-inline"> Informasi</span></button>
   </div>
  </header>
  <div class="ai-messages-viewport" id="aiMessagesViewport" role="log" aria-live="polite">
   <?php if ($posStatus['mode'] !== 'live_pos_db'): ?><p class="ai-source-notice" role="status"><?= $posStatus['mode'] === 'pos_verification_fixture' ? 'Mode simulasi aktif. Angka berasal dari dataset verifikasi.' : 'POS belum terhubung. Analisis data menunggu koneksi live.' ?></p><?php endif ?>
   <div class="ai-empty-state" id="aiEmptyState" <?= !empty($activeConversation['messages']) ? 'hidden' : '' ?>>
    <h3>Apa yang ingin Anda ketahui?</h3><p>Tanyakan penjualan, laba, atau persediaan toko Anda.</p>
    <div class="ai-starter-grid">
     <button type="button" class="ai-starter-card" data-ai-prompt="Berapa total penjualan bulan lalu?">Penjualan bulan lalu</button>
     <button type="button" class="ai-starter-card" data-ai-prompt="Barang apa yang stoknya menipis?">Stok yang perlu ditambah</button>
     <button type="button" class="ai-starter-card" data-ai-prompt="Berapa laba kotor bulan lalu setelah memperhitungkan retur?">Laba kotor bulan lalu</button>
    </div>
   </div>
   <div class="ai-message-stream" id="aiMessageStream"></div>
   <div class="ai-thinking-card" id="aiThinkingIndicator" role="status" hidden><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Menganalisis pertanyaan...</span></div>
  </div>
  <footer class="ai-composer-area">
   <div class="ai-followup-chips" id="aiFollowupChips" aria-label="Saran pertanyaan lanjutan" hidden></div>
   <form class="ai-composer-form" id="aiChatForm" action="<?= esc(rtrim($aiEndpointPath, '/') . '/message', 'attr') ?>" method="post" novalidate>
    <?= csrf_field() ?><label class="visually-hidden" for="aiMessageInput">Pertanyaan Anda</label>
    <textarea id="aiMessageInput" name="message" class="form-control ai-composer-input" rows="1" maxlength="1500" placeholder="Tanyakan tentang toko Anda..." required></textarea>
    <button type="submit" class="btn btn-primary ai-send-btn" id="btnSendAiMessage" aria-label="Kirim pertanyaan"><i class="bi bi-send"></i><span class="d-none d-sm-inline">Kirim</span></button>
   </form>
   <small class="ai-composer-footnote">Enter untuk kirim · Shift+Enter untuk baris baru</small>
  </footer>
 </div>
</section>
<div class="modal fade ai-chart-modal" id="aiChartModal" tabindex="-1" aria-labelledby="aiChartModalTitle" aria-hidden="true" data-bs-backdrop="static">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title" id="aiChartModalTitle">Grafik analisis</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup grafik"></button></div>
  <div class="modal-body" id="aiChartModalContent"></div>
  <div class="modal-footer"><button type="button" class="btn btn-soft d-none d-sm-inline-flex" id="aiChartFullscreen" aria-pressed="false">Layar penuh</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
 </div></div>
</div>
<div class="modal fade" id="aiInformationModal" tabindex="-1" aria-labelledby="aiInformationTitle" aria-hidden="true" data-bs-backdrop="static">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title" id="aiInformationTitle">Informasi AI ASISTAN</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
  <div class="modal-body"><dl><dt>Sumber data</dt><dd><?= esc($posStatus['database']) ?></dd><dt>Status</dt><dd><?= esc($posStatus['message']) ?></dd><dt>Model</dt><dd id="aiProviderBadge"><?= esc($providerConfig['model_id']) ?> · <?= !empty($providerConfig['configured']) ? 'Dikonfigurasi' : 'AI online belum diatur' ?> · <?= esc(['unchecked' => 'Koneksi belum diuji', 'verified' => 'Koneksi terverifikasi', 'unavailable' => 'Koneksi bermasalah'][$providerConfig['connection_status'] ?? 'unchecked'] ?? 'Koneksi belum diuji') ?></dd><dt>Akses akun</dt><dd><?= esc(implode(', ', array_map(static fn ($d) => $domainBadges[$d] ?? $d, $allowedDomains))) ?></dd></dl><p class="form-text">Provider AI wajib terhubung pada setiap pertanyaan. Saat AI tidak tersedia, analisis dihentikan tanpa jawaban offline. Data POS tetap hanya dibaca.</p></div>
  <div class="modal-footer"><?php if ($isAdmin): ?><button type="button" class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#aiProviderModal">Pengaturan Provider</button><?php endif ?><button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
 </div></div>
</div>
<!-- Modal Ganti Nama Percakapan -->
<div class="modal fade" id="aiRenameModal" tabindex="-1" aria-labelledby="aiRenameModalTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" id="aiRenameForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="aiRenameModalTitle">Ganti nama percakapan</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="aiRenameUid" name="uid">
                <label class="form-label" for="aiRenameInput">Judul percakapan</label>
                <input type="text" class="form-control" id="aiRenameInput" name="title" maxlength="150" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Modal Pengaturan AI Provider (Khusus Administrator) -->
<div class="modal fade" id="aiProviderModal" tabindex="-1" aria-labelledby="aiProviderModalTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="aiProviderForm" action="<?= esc(rtrim($aiEndpointPath, '/') . '/provider-config', 'attr') ?>" method="post" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="aiProviderModalTitle">Pengaturan AI Provider</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="demo-note mb-2">Pilih preset provider atau isi Base URL &amp; API Key Anda. API key disimpan pada file server dengan akses terbatas dan tidak dikirim kembali ke browser.</p>
                <div class="component-row mb-3">
                    <button type="button" class="btn btn-soft btn-sm" data-ai-preset-provider="openai" data-ai-preset-url="https://api.openai.com/v1" data-ai-preset-model="gpt-4o-mini">OpenAI</button>
                    <button type="button" class="btn btn-soft btn-sm" data-ai-preset-provider="gemini" data-ai-preset-url="https://generativelanguage.googleapis.com/v1beta/openai" data-ai-preset-model="gemini-2.5-flash">Google Gemini</button>
                    <button type="button" class="btn btn-soft btn-sm" data-ai-preset-provider="deepseek" data-ai-preset-url="https://api.deepseek.com/v1" data-ai-preset-model="deepseek-chat">DeepSeek</button>
                    <button type="button" class="btn btn-soft btn-sm" data-ai-preset-provider="groq" data-ai-preset-url="https://api.groq.com/openai/v1" data-ai-preset-model="llama-3.3-70b-versatile">Groq</button>
                    <button type="button" class="btn btn-soft btn-sm" data-ai-preset-provider="ollama" data-ai-preset-url="http://127.0.0.1:11434/v1" data-ai-preset-model="qwen2.5-coder:7b">Ollama Lokal</button>
                </div>
                <div class="form-pair mb-3">
                    <label>
                        <span>Provider</span>
                        <input type="text" class="form-control" name="provider_name" value="<?= esc((string) $providerConfig['provider_name']) ?>" required>
                    </label>
                    <label>
                        <span>Model ID</span>
                        <input type="text" class="form-control" name="model_id" value="<?= esc((string) $providerConfig['model_id']) ?>" required>
                    </label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="aiBaseUrlInput">API Base URL</label>
                    <input type="url" class="form-control" id="aiBaseUrlInput" name="base_url" value="<?= esc((string) $providerConfig['base_url']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="aiApiKeyInput">API Key <small class="text-muted">(Status: <?= esc((string) ($providerConfig['api_key_masked'] ?? 'Belum diatur')) ?>)</small></label>
                    <input type="password" class="form-control" id="aiApiKeyInput" name="api_key" placeholder="Kosongkan jika tidak ingin mengubah API Key" autocomplete="off">
                </div>
                <div class="form-pair mb-3">
                    <label>
                        <span>Temperature (0.0 – 1.0)</span>
                        <input type="number" class="form-control numeric" name="temperature" step="0.05" min="0" max="1" value="<?= esc((string) $providerConfig['temperature']) ?>">
                    </label>
                    <label>
                        <span>Max Tokens</span>
                        <input type="number" class="form-control numeric" name="max_tokens" min="256" max="8192" value="<?= esc((string) $providerConfig['max_tokens']) ?>">
                    </label>
                </div>
                <div class="form-pair mb-2">
                    <label>
                        <span>Timeout (detik)</span>
                        <input type="number" class="form-control numeric" name="timeout_seconds" min="5" max="120" value="<?= esc((string) $providerConfig['timeout_seconds']) ?>">
                    </label>
                    <label for="aiReasoningEffortInput">
                        <span>Reasoning</span>
                        <select class="form-select" id="aiReasoningEffortInput" name="reasoning_effort" aria-describedby="aiReasoningHelp">
                            <?php foreach (['auto'=>'Otomatis · ikuti provider','none'=>'Nonaktif (jika didukung)','low'=>'Rendah','medium'=>'Sedang','high'=>'Tinggi'] as $effort=>$label): ?>
                            <option value="<?= esc($effort,'attr') ?>" <?= ($providerConfig['reasoning_effort']??'auto')===$effort?'selected':'' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <p class="form-text mb-0" id="aiReasoningHelp">Otomatis mengikuti 9Router/provider. Pilihan level diperiksa terhadap kemampuan model saat disimpan. Level tinggi dapat menambah waktu dan penggunaan token.</p>
                <p class="form-text" id="aiReasoningStatus" role="status"><?= !empty($providerConfig['reasoning_capabilities']['effort_supported'])?'Provider menyatakan model mendukung pengaturan level reasoning.':'Dukungan level reasoning belum diperiksa atau belum dinyatakan oleh provider.' ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Konfigurasi</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
