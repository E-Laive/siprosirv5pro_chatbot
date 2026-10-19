<!doctype html><html lang="id"><head><meta charset="utf-8"><style>
@page { margin: <?= $format === 'a4' ? '12mm' : '2mm' ?>; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: <?= $format === 'a4' ? '10px' : '8px' ?>; color: #172033; }
h2 { margin: 0 0 4px; font-size: 18px; } h3 { margin: 14px 0 8px; font-size: 12px; }
p { margin: 4px 0; } header, footer { text-align: <?= $format === 'a4' ? 'left' : 'center' ?>; }
.document-sample { font-size: 7px; color: #485565; } .document-meta { margin-bottom: 12px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; } td, th { text-align: left; padding: 6px 2px; word-wrap: break-word; }
.document-items { table-layout: auto; } .document-items th { background: #edf2f5; } .document-items td { border-bottom: 1px solid #d8e1e8; }
.document-items small { display: block; color: #485565; } .amount { text-align: right; } .document-totals { margin-top: 12px; }
.grand-total th,.grand-total td { border-top: 1px solid #64748b; font-weight: bold; }
.receipt-line { margin: 8px 0; page-break-inside: avoid; } .receipt-line table td { padding: 2px 0; }
footer { margin-top: 18px; font-size: 8px; } thead { display: table-header-group; }
</style></head><body><?= view('documents/content', compact('invoice', 'format')) ?></body></html>
