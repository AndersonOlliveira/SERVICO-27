<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;

    public static function connect()
    {
        if (!self::$instance) {

            try {

                self::$instance = new PDO(
                    sprintf(
                        "%s:host=%s;port=%s;dbname=%s",
                        $_ENV['DB_CONNECTION'],
                        $_ENV['DB_HOST'],
                        $_ENV['DB_PORT'],
                        $_ENV['DB_DATABASE']
                    ),
                    $_ENV['DB_USERNAME'],
                    $_ENV['DB_PASSWORD']
                );

                self::$instance->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

                self::$instance->setAttribute(
                    PDO::ATTR_DEFAULT_FETCH_MODE,
                    PDO::FETCH_ASSOC
                );
            } catch (PDOException $e) {

                die('Erro ao conectar com banco: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
