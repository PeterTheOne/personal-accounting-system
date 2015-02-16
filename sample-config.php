<?php

try {
    $pdo = new PDO('mysql:host=localhost;dbname=personal-accounting-system;charset=utf8', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    $pdo->query('
        CREATE TABLE IF NOT EXISTS postingline (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            postingLineId VARCHAR(255),

            account VARCHAR(255),
            text TEXT,

            postingDate TIMESTAMP,
            valueDate TIMESTAMP,

            amount FLOAT,
            currency VARCHAR(255),

            comment VARCHAR(255),

            contraAccount VARCHAR(255),
            contraAccountBic VARCHAR(255),
            contraAccountName VARCHAR(255),

            category VARCHAR(255),

            UNIQUE(postingLineId)
        );
    ');

    $pdo->query('
        CREATE TABLE IF NOT EXISTS rules (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            search VARCHAR(255),
            category VARCHAR(255)
        );
    ');
} catch (Exception $exception) {
    echo $exception->getMessage();
}
