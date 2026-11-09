<?php

require 'lib/password.php';

echo 'Test for functionality of compat library: ' . (Password_Compat\binary\check() ? 'Pass' : 'Fail');
echo "\n";
