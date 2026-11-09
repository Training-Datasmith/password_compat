<?php

require_once __DIR__ . '/../lib/password.php';

$password = 'my_secret_password';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo "Hash: {$hash}\n\n";

if (password_verify($password, $hash)) {
    echo "Password is valid!\n";
} else {
    echo "Invalid password.\n";
}
echo "\n";

$old_hash = password_hash($password, PASSWORD_BCRYPT, array('cost' => 8));

if (password_needs_rehash($old_hash, PASSWORD_BCRYPT, array('cost' => 12))) {
    $new_hash = password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));
    echo "Rehashed with higher cost: {$new_hash}\n\n";
} else {
    echo "No rehash needed.\n\n";
}

$info = password_get_info($hash);
echo "Algorithm: {$info['algoName']}\n";
echo "Cost factor: {$info['options']['cost']}\n";
