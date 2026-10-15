<?php

require_once __DIR__ . '/../System/System.php';
require_once __DIR__ . '/../System/Language.php';

class Controller
{
    protected ?Database $db = null;
    protected ?System $system = null;

    public function __construct(?System $system = null)
    {
        global $system, $db;
        Language::init();
        $this->system = $system ?? ($GLOBALS['system'] ?? new System());
        $this->db = ($this->system && isset($this->system->db)) ? $this->system->db : ($db ?? new Database());
    }
}
