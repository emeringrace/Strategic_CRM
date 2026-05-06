<?php
// ── Database Configuration ─────────────────────────────────
// XAMPP defaults: host=localhost, user=root, password=''
define('DB_HOST', 'localhost');
define('DB_NAME', 'crm_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:2rem;color:#c1622a;'>
        <h2>Database Connection Failed</h2>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p>Make sure XAMPP is running and you have imported <code>sql/crm_setup.sql</code>.</p>
    </div>");
}
