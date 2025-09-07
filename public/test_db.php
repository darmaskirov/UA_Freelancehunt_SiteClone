<?php
require_once __DIR__ . '/../config/db.php';

$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    echo $row[0] . "<br>";
}