<?php

require_once __DIR__ . '/DataLayer.php';

abstract class Model
{
    protected Database $db;

    public function __construct(?Database $db = null)
    {
        global $db;
        if ($db instanceof Database) {
            $this->db = $db;
        } else {
            $this->db = new Database();
        }
    }
}
