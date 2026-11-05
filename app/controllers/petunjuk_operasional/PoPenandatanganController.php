<?php
namespace petunjuk_operasional;

/**
 * PoPenandatanganController -- daftar pejabat penandatangan untuk dokumen Petunjuk Operasional (semua jenis PO).
 *
 * GET /petunjuk-operasional/penandatangan  -> JSON {ok, data:[{id, nama, nip, jabatan, baris, kategori, k}], ketua:[nama, ...]}
 *   ?layanan=selulosa|lingkungan  -> 'ketua' = nama Ketua Tim layanan itu (tanpa parameter: layanan user yang login)
 *   ?ketua_saja=1                 -> hanya 'ketua' (tanpa 'data')
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
            $layanan = strtolower(trim((string) $f3->get('GET.layanan')));
            if (!in_array($layanan, array('selulosa', 'lingkungan'), true)) {
                $layanan = strtolower((string) $this->getUserLayanan());
            }
            $out = array('ok' => true, 'layanan' => $layanan, 'ketua' => $this->namaKetuaTim($layanan));
            if (!$f3->get('GET.ketua_saja')) {
                $out['data'] = \PoTtd::daftarMaster($this->dbSekretariat ?: $this->db);
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[PoPenandatangan] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Daftar penandatangan tidak bisa dimuat.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Nama Ketua Tim aktif untuk satu layanan (opti_user_map). Layanan khusus didahulukan; 'semua' hanya bila tidak ada. */
    protected function namaKetuaTim($layanan) {
        if (!in_array($layanan, array('selulosa', 'lingkungan'), true)) {
            return array();
        }
        $nama = array();
        foreach (array($layanan, 'semua') as $jl) {
            try {
                $rows = $this->db->exec(
                    "SELECT DISTINCT u.nama_user FROM opti_user_map m
                       JOIN tb_arsipuser u ON u.id_user = m.id_user
                      WHERE m.role = 'ketua_tim' AND m.is_active = 1 AND m.status_tampil = 'show'
                        AND m.jenis_layanan = ? AND (u.status = 1 OR u.status = '1' OR u.status = 'aktif')",
                    array(1 => $jl)
                );
                foreach ($rows as $r) {
                    $n = trim((string) $r['nama_user']);
                    if ($n !== '') { $nama[] = $n; }
                }
            } catch (\Throwable $e) {
                error_log('[PoPenandatangan] ketua tim: ' . $e->getMessage());
            }
            if ($nama) { break; }
        }
        return $nama;
    }
}
