<?php

class Password_Needs_Rehash_Test extends PHPUnit\Framework\TestCase
{
    public static function provide_cases()
    {
        return array(
            array('foo', 0, array(), false),
            array('foo', 1, array(), true),
            array('$2y$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', PASSWORD_BCRYPT, array(), true),
            array('$2y$07$usesomesillystringfore2udlvp1ii2e./u9c8sbjqp8i90dh6hi', PASSWORD_BCRYPT, array('cost' => 7), false),
            array('$2y$07$usesomesillystringfore2udlvp1ii2e./u9c8sbjqp8i90dh6hi', PASSWORD_BCRYPT, array('cost' => 5), true),
        );
    }

    public function test_func_exists()
    {
        $this->assertTrue(function_exists('password_needs_rehash'));
    }

    /**
     * @dataProvider provide_cases
     */
    public function test_cases($hash, $algo, $options, $valid)
    {
        $this->assertEquals($valid, password_needs_rehash($hash, $algo, $options));
    }
}
