<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/password.php';

// --- Example 1: Hash a password ---
$password = 'my_secret_password';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo "Hash: {$hash}\n\n";

// --- Example 2: Verify a password ---
if (password_verify($password, $hash)) {
    echo "Password is valid!\n";
} else {
    echo "Invalid password.\n";
}
echo "\n";

// --- Example 3: Check if rehash is needed (e.g., after increasing cost) ---
$old_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 8]);

// Later, your policy requires cost >= 12
if (password_needs_rehash($old_hash, PASSWORD_BCRYPT, ['cost' => 12])) {
    // The user just logged in successfully, so we have the plain-text password
    $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    echo "Rehashed with higher cost: {$new_hash}\n\n";
} else {
    echo "No rehash needed.\n\n";
}

// --- Example 4: Inspect a hash ---
$info = password_get_info($hash);
echo "Algorithm: {$info['algoName']}\n";      // bcrypt
echo "Cost factor: {$info['options']['cost']}\n"; // 10 (default)
