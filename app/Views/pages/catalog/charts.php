<section class="showcase-stack"><p class="demo-note">Seluruh angka dan status pada halaman ini merupakan data simulasi.</p>
    <div class="content-grid equal-grid">
        <article class="content-card chart-card">
            <div class="card-heading"><span><small>LINE & AREA</small><h2>Tren penjualan</h2></span><span class="demo-note">Data contoh</span></div>
            <?= view('components/sales_chart') ?>
        </article>
        <article class="content-card chart-card">
            <div class="card-heading"><span><small>BAR CHART</small><h2>Penjualan kategori (unit)</h2></span></div>
            <div class="bar-chart" role="img" aria-label="Penjualan kategori contoh dalam unit: Minuman 82, Makanan 66, Sembako 48, Perawatan 72, Lainnya 38"><div style="--bar:82%"><span>82</span><i></i><small>Minuman</small></div><div style="--bar:66%"><span>66</span><i></i><small>Makanan</small></div><div style="--bar:48%"><span>48</span><i></i><small>Sembako</small></div><div style="--bar:72%"><span>72</span><i></i><small>Perawatan</small></div><div style="--bar:38%"><span>38</span><i></i><small>Lainnya</small></div></div>
        </article>
    </div>
    <div class="content-grid equal-grid">
        <article class="content-card"><div class="card-heading"><span><small>DOUGHNUT</small><h2>Metode pembayaran</h2></span></div><div class="donut-layout"><div class="donut-chart"><span><strong>248</strong><small>Transaksi</small></span></div><ul class="chart-legend"><li><i class="legend-primary"></i><span>QRIS</span><strong>42%</strong></li><li><i class="legend-success"></i><span>Tunai</span><strong>33%</strong></li><li><i class="legend-warning"></i><span>Transfer</span><strong>17%</strong></li><li><i class="legend-neutral"></i><span>Lainnya</span><strong>8%</strong></li></ul></div></article>
        <article class="content-card"><div class="card-heading"><span><small>MINI CHART</small><h2>Ringkasan KPI</h2></span></div><div class="spark-grid"><div><span><small>Omzet</small><strong>Rp12,8 jt</strong></span><svg viewBox="0 0 160 48"><polyline points="0,40 25,30 50,34 75,20 100,26 125,10 160,5"/></svg></div><div><span><small>Transaksi</small><strong>248</strong></span><svg viewBox="0 0 160 48"><polyline points="0,34 25,38 50,26 75,30 100,14 125,18 160,8"/></svg></div></div></article>
    </div>
</section>
