<?php

class Password_Hash_Test extends PHPUnit\Framework\TestCase
{
  private function invoke_with_salt_deprecation_check($callback)
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            return $callback();
        }
        $deprecated = array();
        set_error_handler(function ($errno, $errstr) use (&$deprecated) {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
                $deprecated[] = $errstr;
                return true;
            }
            return false;
        });
        try {
            $result = $callback();
        } catch (Exception $e) {
            restore_error_handler();
            throw $e;
        }
        restore_error_handler();
        $this->assertNotEmpty($deprecated);
        $this->assertRegExp('/salt/i', implode("\n", $deprecated));

        return $result;
    }

    public function test_func_exists()
    {
        $this->assertTrue(function_exists('password_hash'));
    }

    public function test_string_length()
    {
        $this->assertEquals(60, strlen(password_hash('foo', PASSWORD_BCRYPT)));
    }

    public function test_hash()
    {
        $hash = password_hash('foo', PASSWORD_BCRYPT);
        $this->assertEquals($hash, crypt('foo', $hash));
    }

    public function test_known_salt()
    {
        $hash = $this->invoke_with_salt_deprecation_check(function () {
            return password_hash('rasmuslerdorf', PASSWORD_BCRYPT, array('cost' => 7, 'salt' => 'usesomesillystringforsalt'));
        });
        $this->assertEquals('$2y$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', $hash);
    }

    public function test_raw_salt()
    {
        $hash = $this->invoke_with_salt_deprecation_check(function () {
            return password_hash('test', PASSWORD_BCRYPT, array('salt' => '123456789012345678901' . chr(0)));
        });
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            $this->assertEquals('$2y$10$KRGxLBS0Lxe3KBCwKxOzLexLDeu0ZfqJAKTubOfy7O/yL2hjimw3u', $hash);
        } else {
            $this->assertEquals('$2y$10$MTIzNDU2Nzg5MDEyMzQ1Nej0NmcAWSLR.oP7XOR9HD/vjUuOj100y', $hash);
        }
    }

    public function test_null_behavior()
    {
        $hash = $this->invoke_with_salt_deprecation_check(function () {
            return password_hash(null, PASSWORD_BCRYPT, array('salt' => '1234567890123456789012345678901234567890'));
        });
        $this->assertEquals('$2y$10$123456789012345678901uhihPb9QpE2n03zMu9TDdvO34jDn6mO.', $hash);
    }

    public function test_integer_behavior()
    {
        $hash = $this->invoke_with_salt_deprecation_check(function () {
            return password_hash(12345, PASSWORD_BCRYPT, array('salt' => '1234567890123456789012345678901234567890'));
        });
        $this->assertEquals('$2y$10$123456789012345678901ujczD5TiARVFtc68bZCAlbEg1fCIexfO', $hash);
    }

    public function test_invalid_algo()
    {
        $this->expectException(PHPUnit\Framework\Error\Warning::class);
        password_hash('foo', array());
    }

    public function test_invalid_algo2()
    {
        $this->expectException(PHPUnit\Framework\Error\Warning::class);
        password_hash('foo', 99);
    }

    public function test_invalid_password()
    {
        $this->expectException(PHPUnit\Framework\Error\Warning::class);
        password_hash(array(), 1);
    }

    public function test_invalid_salt()
    {
        $this->expectException(PHPUnit\Framework\Error\Warning::class);
        password_hash('foo', PASSWORD_BCRYPT, array('salt' => array()));
    }

    public function test_invalid_bcrypt_cost_low()
    {
        $this->expectException(PHPUnit\Framework\Error\Warning::class);
        password_hash('foo', PASSWORD_BCRYPT, array('cost' => 3));
    }

    public function test_invalid_bcrypt_cost_high()
    {
        $this->expectException(PHPUnit\Framework\Error\Warning::class);
        password_hash('foo', PASSWORD_BCRYPT, array('cost' => 32));
    }

    public function test_invalid_bcrypt_cost_invalid()
    {
        $this->expectException(PHPUnit\Framework\Error\Warning::class);
        password_hash('foo', PASSWORD_BCRYPT, array('cost' => 'foo'));
    }

    public function test_invalid_bcrypt_salt_short()
    {
        $this->expectException(PHPUnit\Framework\Error\Warning::class);
        password_hash('foo', PASSWORD_BCRYPT, array('salt' => 'abc'));
    }
}
