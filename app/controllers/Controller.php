<?php

class Controller {
    protected $f3;
    protected $db;

    public function __construct() {
        $this->f3 = \Base::instance();
        $this->db = $this->f3->get('DB');
    }

    /**
     * Helper render view dengan layout utama
     */
    public function render($viewFile, $pageTitle = 'Mini OPTI Tracker', $activeMenu = '') {
        $this->f3->set('content', $viewFile);
        $this->f3->set('page_title', $pageTitle);
        $this->f3->set('active_menu', $activeMenu);
        echo \Template::instance()->render('layout.htm');
    }

    /**
     * Set notifikasi flash sukses
     */
    public function setFlashSuccess($message) {
        $_SESSION['flash_success'] = $message;
    }

    /**
     * Set notifikasi flash error
     */
    public function setFlashError($message) {
        $_SESSION['flash_error'] = $message;
    }
}
