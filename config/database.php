<?php

function database()
{
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $name = getenv("DB_NAME") ?: "taller_mecanico_v2";
    $user = getenv("DB_USER") ?: "root";
    $password = getenv("DB_PASSWORD") ?: "";
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}
