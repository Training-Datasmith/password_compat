<?php

class Password_Verify_Test extends PHPUnit\Framework\TestCase
{
    public function test_func_exists()
    {
        $this->assertTrue(function_exists('password_verify'));
    }

    public function test_failed_type()
    {
        $this->assertFalse(password_verify(123, 123));
    }

    public function test_salt_only()
    {
        $this->assertFalse(password_verify('foo', '$2a$07$usesomesillystringforsalt$'));
    }

    public function test_invalid_password()
    {
        $this->assertFalse(password_verify('rasmusler', '$2a$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi'));
    }

    public function test_valid_password()
    {
        $this->assertTrue(password_verify('rasmuslerdorf', '$2a$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi'));
    }

    public function test_in_valid_hash()
    {
        $this->assertFalse(password_verify('rasmuslerdorf', '$2a$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hj'));
    }
}
