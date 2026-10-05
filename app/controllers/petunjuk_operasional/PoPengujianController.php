<?php
namespace petunjuk_operasional;

/**
 * PoPengujianController -- Petunjuk Operasional (PO) jenis PENGUJIAN.
 *
 * Sengaja DIPISAH dari modul PO OPTI (PoKegiatanController dkk): beda folder
 * (app/controllers/petunjuk_operasional/), beda namespace, beda folder view
 * (app/views/petunjuk_operasional/po_pengujian/), beda URL (/petunjuk-operasional/...).
 *
 * TAHAP 1 (sekarang): hanya halaman "create" (frontend) -- form + editor teks +
 * live preview dokumen. Belum ada simpan ke database; itu tahap berikutnya.
 *
 * Kompatibel PHP 7.2.
 */
class PoPengujianController extends \Controller {

    /** GET /petunjuk-operasional/pengujian/create */
    public function create($f3) {
        $this->requireAuth();

        // Tahap frontend: semua data diisi manual di halaman. Nanti (tahap backend)
        // di sini ditambah: data order/mitra, master tim pelaksana, dsb.
        $f3->set('tanggal_default', date('Y-m-d'));

        $this->render(
            'petunjuk_operasional/po_pengujian/create.html',
            'Buat PO Pengujian',
            'po_pengujian'
        );
    }
}
