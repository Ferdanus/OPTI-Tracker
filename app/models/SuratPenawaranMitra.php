<?php


class SuratPenawaranMitra extends \DB\SQL\Mapper
{
    public function __construct(\DB\SQL $db)
    {
        parent::__construct($db, 'surat_penawaran_mitra');
    }
}