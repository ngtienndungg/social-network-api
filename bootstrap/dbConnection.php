<?php
$db = "mysql:host=127.0.0.1; port=4306; dbname=social_network";
$username = "root";
$password = "";

try {
    $pdo = new PDO($db, $username, $password);
} catch (Exception $ex) {
    echo json_encode(array('status'=>500, 'message' => $ex->getMessage()));
    die();
}
?>