<style>
    .po-doc-scope { font-family: 'Times New Roman', Times, serif; color: #000; }
    .po-doc-sheet { background: #fff; padding: 20mm 15mm; max-width: 210mm; margin: 0 auto; }
    .po-doc-title { text-align: center; font-weight: bold; font-size: 16px; margin-bottom: 4px; }
    .po-doc-nomor { text-align: center; font-size: 13px; margin-bottom: 16px; }
    .po-doc-judul { font-weight: bold; margin-bottom: 14px; font-size: 13px; }
    .po-section-title { font-weight: bold; font-size: 13px; margin: 16px 0 6px; }
    .po-doc-sheet table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 6px; }
    .po-doc-sheet table th, .po-doc-sheet table td { border: 1px solid #000; padding: 5px 8px; }
    .po-doc-sheet table th { background: #f1f5f9; font-weight: bold; text-align: center; }
    .po-badge-status { font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 12px; }
    .po-status-draft { background: #f1f5f9; color: #475569; }
    .po-status-terkirim { background: #ecfdf5; color: #065f46; }
    .po-jadwal-cell { width: 26px; height: 20px; }
    .po-jadwal-cell.on { background: var(--color-primary, #881337); }
    .po-signature-row { display: flex; justify-content: space-between; margin-top: 30px; font-size: 12px; text-align: center; }
    .po-signature-row div { width: 45%; }
</style>

<div class="po-doc-scope">
    <div class="text-end mb-2">
        <?php if ($po['status'] == 'draft'): ?><span class="po-badge-status po-status-draft">DRAFT</span><?php endif; ?>
        <?php if ($po['status'] == 'terkirim'): ?><span class="po-badge-status po-status-terkirim">TERKIRIM</span><?php endif; ?>
    </div>

    <div class="po-doc-sheet" id="poDocSheet">
        <div class="po-doc-title">PETUNJUK OPERASIONAL (PO)</div>
        <div class="po-doc-nomor">Nomor: <?= ($po['nomor_po']) ?></div>
        <div class="po-doc-judul">Judul: <?= ($po['judul_kegiatan']) ?></div>

        <div class="po-section-title">1. DASAR</div>
        <div style="font-size:13px; white-space:pre-line;"><?= ($po['dasar'] ?: '-') ?></div>

        <div class="po-section-title">2. TUJUAN</div>
        <div style="font-size:13px; white-space:pre-line;"><?= ($po['tujuan'] ?: '-') ?></div>

        <div class="po-section-title">3. RUANG LINGKUP</div>

<?php if ($po['ruang_lingkup_deskripsi']): ?>
    <div style="font-size:13px; text-align:justify; margin-bottom:8px;"><?= ($po['ruang_lingkup_deskripsi']) ?></div>
<?php endif; ?>

<?php if ($po['ruang_lingkup_items'] && count($po['ruang_lingkup_items']) > 0): ?>
    <table>
        <thead><tr><th>Nama Alat/Kegiatan</th><th>Jumlah</th><th>Waktu (menit)</th><th>Total (jam)</th><th>Keterangan</th></tr></thead>
        <tbody>
            <?php foreach (($po['ruang_lingkup_items']?:[]) as $rl): ?>
                <tr>
                    <td><?= ($rl['nama_alat']) ?></td>
                    <td class="text-center"><?= ($rl['jumlah']) ?></td>
                    <td class="text-center"><?= ($rl['waktu_menit']) ?></td>
                    <td class="text-center"><?= ($rl['total_jam']) ?></td>
                    <td><?= ($rl['keterangan']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($po['alamat']): ?>
    <div class="po-section-title"><?= ($po['nomor_alamat']) ?>. ALAMAT</div>
    <div style="font-size:13px;"><?= ($po['alamat']) ?></div>
<?php endif; ?>

<?php if ($po['nomor_alat_bahan']): ?>
    <div class="po-section-title"><?= ($po['nomor_alat_bahan']) ?>. ALAT DAN BAHAN</div>

    <?php if ($po['alat_items'] && count($po['alat_items']) > 0): ?>
        <div style="font-weight:700; font-size:12.5px; margin-bottom:4px;"><?= ($po['nomor_alat_bahan']) ?>.1. Alat</div>
        <ul style="margin:0 0 10px; padding-left:20px; font-size:13px;">
            <?php foreach (($po['alat_items']?:[]) as $a): ?>
                <li><?= ($a) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($po['bahan_items'] && count($po['bahan_items']) > 0): ?>
        <div style="font-weight:700; font-size:12.5px; margin-bottom:4px;"><?= ($po['nomor_alat_bahan']) ?>.2. Bahan dan Bahan Kimia</div>
        <table>
            <thead><tr><th style="width:35px;">No</th><th>Bahan Kimia/Penolong</th><th>Rencana Penggunaan</th></tr></thead>
            <tbody>
                <?php foreach (($po['bahan_items']?:[]) as $bi=>$b): ?>
                    <tr>
                        <td class="text-center"><?= ($bi + 1) ?></td>
                        <td><?= ($b['nama']) ?></td>
                        <td><?= ($b['jumlah']) ?> <?= ($b['satuan']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>    

        <div class="po-section-title"><?= ($po['nomor_tim']) ?>. TIM PELAKSANA</div>
        <table>
            <thead><tr><th style="width:30px;">No</th><th>Nama</th><th>Tugas/Tanggung Jawab</th><th>Uraian Tugas</th></tr></thead>
            <tbody>
                <?php if ($po['tim_pelaksana'] && count($po['tim_pelaksana']) > 0): ?>
                    <?php foreach (($po['tim_pelaksana']?:[]) as $i=>$tp): ?>
                        <tr>
                            <td class="text-center"><?= ($i + 1) ?></td>
                            <td><?= ($tp['nama']) ?></td>
                            <td><?= ($tp['tugas']) ?></td>
                            <td><?= ($tp['uraian_tugas']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!$po['tim_pelaksana'] || count($po['tim_pelaksana']) == 0): ?>
                    <tr><td colspan="4" class="text-center text-muted">-</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="po-section-title"><?= ($po['nomor_rab'] ?: 5) ?>. RENCANA ANGGARAN BIAYA (RAB)</div>
        <table>
            <tbody>
                <tr><td colspan="2" style="background:#f8fafc;"><strong>A. Penerimaan</strong></td></tr>
                <?php foreach (($po['rab_hitung']['penerimaan_items']?:[]) as $p): ?>
                    <tr><td><?= ($p['nama']) ?></td><td class="text-end">Rp <?= (number_format($p['nominal'], 0, ',', '.')) ?></td></tr>
                <?php endforeach; ?>
                <tr><td><strong>Total Penerimaan</strong></td><td class="text-end"><strong>Rp <?= (number_format($po['rab_hitung']['total_penerimaan'], 0, ',', '.')) ?></strong></td></tr>
        
                <tr><td colspan="2" style="background:#f8fafc;"><strong>B. Rencana Pengeluaran</strong></td></tr>
                <?php foreach (($po['rab_hitung']['kategori']?:[]) as $ki=>$kat): ?>
                    <tr><td><?= ($ki + 1) ?>. <?= ($kat['nama']) ?> (<?= ($kat['persen_tampil']) ?>)</td><td class="text-end">Rp <?= (number_format($kat['subtotal'], 0, ',', '.')) ?></td></tr>
                    <?php foreach (($kat['items']?:[]) as $it): ?>
                        <tr>
                            <td style="padding-left:22px;">
                                <?= ($it['nama'])."
" ?>
                                <?php if ($it['label_a']): ?> (<?= ($it['qty_a']) ?> <?= ($it['label_a']) ?><?php if ($it['label_b']): ?>, <?= ($it['qty_b']) ?> <?= ($it['label_b']) ?><?php endif; ?> @ Rp<?= (number_format($it['tarif'], 0, ',', '.')) ?>)<?php endif; ?>
                            </td>
                            <td class="text-end">Rp <?= (number_format($it['nominal'], 0, ',', '.')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
        
                <tr><td><strong>Total Pengeluaran</strong></td><td class="text-end"><strong>Rp <?= (number_format($po['rab_hitung']['total_pengeluaran'], 0, ',', '.')) ?></strong></td></tr>
            </tbody>
        </table>
        <div class="po-section-title"><?= ($po['nomor_jadwal'] ?: 6) ?>. JADWAL</div>
        <?php if ($po['jadwal_kolom'] && count($po['jadwal_kolom']) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <?php foreach (($po['jadwal_bulan_header']?:[]) as $bh): ?>
                            <th colspan="<?= ($bh['span']) ?>" class="text-center"><?= ($bh['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th>Kegiatan</th>
                        <?php foreach (($po['jadwal_kolom']?:[]) as $k): ?>
                            <th class="text-center" style="font-size:10px;"><?= ($k['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($po['jadwal_items']?:[]) as $j): ?>
                        <tr>
                            <td><?= ($j['nama']) ?></td>
                            <?php foreach (($po['jadwal_kolom']?:[]) as $k): ?>
                                <td class="po-jadwal-cell <?= (in_array($k['key'], $j['tanda'] ?: []) ? 'on' : '') ?>"></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if (!$po['jadwal_kolom'] || count($po['jadwal_kolom']) == 0): ?>
            <div class="text-muted" style="font-size:12px;">Tanggal mulai & selesai jadwal belum diisi.</div>
        <?php endif; ?>

        <div class="po-signature-row">
            <div>
                <div>Menyetujui<br>Pejabat Pembuat Komitmen</div>
                <div style="height:50px;"></div>
                <div>( .......................................... )</div>
            </div>
            <div>
                <div>Pelaksana Kegiatan<br>Ketua Tim Kerja</div>
                <div style="height:50px;"></div>
                <div>( .......................................... )</div>
            </div>
        </div>
    </div>
</div>
