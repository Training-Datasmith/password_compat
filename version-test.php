<?php

declare(strict_types=1);

require 'lib/password.php';

echo 'Test for functionality of compat library: ' . (PasswordCompat\binary\check() ? 'Pass' : 'Fail');
echo "\n";
