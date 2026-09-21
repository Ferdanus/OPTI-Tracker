<?php

/**
 * OptiDatabase
 * Extends Fat-Free DB\SQL to support pure dual-database architecture:
 * - Operational OPTI tables are located in `silopti2026`.
 * - Shared master tables (`tb_customer`, `tb_arsipuser`, `tb_arsipsurat`, `tb_hari_libur`, `tb_surat_keluar`)
 *   remain physically in `sil2020` without requiring SQL Views in `silopti2026`.
 */
class OptiDatabase extends \DB\SQL {
    protected static $sharedTables = [
        'tb_customer',
        'tb_arsipuser',
        'tb_arsipsurat',
        'tb_hari_libur',
        'tb_surat_keluar'
    ];

    public function exec($cmds, $args = null, $ttl = 0, $log = true, $stamp = false) {
        if (is_string($cmds)) {
            $cmds = $this->rewriteSharedTables($cmds);
        } elseif (is_array($cmds)) {
            foreach ($cmds as &$c) {
                if (is_string($c)) $c = $this->rewriteSharedTables($c);
            }
            unset($c);
        }
        return parent::exec($cmds, $args, $ttl, $log, $stamp);
    }

    public function schema($table, $fields = null, $ttl = 60) {
        $cleanTable = trim($table, '`');
        if (in_array($cleanTable, self::$sharedTables)) {
            $oldDb = $this->dbname;
            $this->dbname = 'sil2020';
            $res = parent::schema($cleanTable, $fields, $ttl);
            $this->dbname = $oldDb;
            return $res;
        }
        return parent::schema($table, $fields, $ttl);
    }

    public function quotekey($key, $split = true) {
        $cleanKey = trim($key, '`');
        if (in_array($cleanKey, self::$sharedTables)) {
            $key = 'sil2020.' . $cleanKey;
        }
        return parent::quotekey($key, $split);
    }

    protected function rewriteSharedTables(string $sql): string {
        $tables = implode('|', self::$sharedTables);
        // Pastikan tabel master sekretariat selalu diarahkan ke sil2020 baik ditulis dengan/tanpa prefix ataupun backtick
        return preg_replace('/(?<![a-zA-Z0-9_])(?:`?(?:silopti2026|silopti_2026|sil2020)`?\.)?`?(' . $tables . ')`?(?!\w)/i', '`sil2020`.`$1`', $sql);
    }
}
