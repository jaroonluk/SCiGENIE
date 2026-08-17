<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
try {
    Db::app()->query('SELECT 1');
    echo "scigeniedb=ok\n";
} catch (Throwable $e) {
    echo 'scigeniedb=fail: ' . $e->getMessage() . "\n";
}
try {
    Db::eoffice()->query('SELECT 1');
    echo "eoffice=ok\n";
} catch (Throwable $e) {
    echo 'eoffice=fail: ' . $e->getMessage() . "\n";
}
