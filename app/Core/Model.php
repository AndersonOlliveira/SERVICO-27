<?php

namespace App\Core;
use App\Core\Database;


class Model
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }
}
