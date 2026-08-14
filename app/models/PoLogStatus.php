<?php

/**
 * Model PoLogStatus
 * Mencatat histori dan audit trail setiap perubahan status PO
 */
class PoLogStatus extends \DB\SQL\Mapper {

    public function __construct(\DB\SQL $db) {
        parent::__construct($db, 'po_log_status');
    }

    /**
     * Catat log perpindahan status
     */
    public function catat($poId, $statusLama, $statusBaru, $catatan = '') {
        $this->reset();
        $this->po_id       = $poId;
        $this->status_lama = $statusLama;
        $this->status_baru = $statusBaru;
        $this->catatan     = $catatan;
        $this->tanggal     = date('Y-m-d H:i:s');
        $this->save();
        return $this->id;
    }

    /**
     * Ambil seluruh histori log untuk suatu PO (diurutkan kronologis)
     */
    public function getByPoId($poId) {
        return $this->find(
            array('po_id = ?', $poId),
            array('order' => 'tanggal ASC, id ASC')
        );
    }
}
