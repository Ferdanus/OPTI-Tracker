<?php
namespace petunjuk_operasional;

/**
 * PoPenandatanganController -- daftar pejabat penandatangan untuk dokumen Petunjuk Operasional (semua jenis PO).
 *
 * GET /petunjuk-operasional/penandatangan  -> JSON {ok, data:[{id, nama, nip, jabatan, baris, kategori, k}]}
 * Sumber: master penandatangan (mst_penandatangan_lhu, database sekretariat) yang sama dengan Surat Penawaran.
 * Dipakai komponen pilihan di app/views/petunjuk_operasional/_penandatangan.html.
 *
 * Kompatibel PHP 7.2.
 */
class PoPenandatanganController extends \Controller {

    public function daftar($f3) {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $data = \PoTtd::daftarMaster($this->dbSekretariat ?: $this->db);
            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[PoPenandatangan] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Daftar penandatangan tidak bisa dimuat.'], JSON_UNESCAPED_UNICODE);
        }
    }
}
