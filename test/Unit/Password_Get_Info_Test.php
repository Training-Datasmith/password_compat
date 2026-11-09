<?php

class Password_Get_Info_Test extends PHPUnit\Framework\TestCase
{
    public static function provide_info()
    {
        return array(
            array('foo', array('algo' => 0, 'algoName' => 'unknown', 'options' => array())),
            array('$2y$', array('algo' => 0, 'algoName' => 'unknown', 'options' => array())),
            array('$2y$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', array('algo' => PASSWORD_BCRYPT, 'algoName' => 'bcrypt', 'options' => array('cost' => 7))),
            array('$2y$10$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', array('algo' => PASSWORD_BCRYPT, 'algoName' => 'bcrypt', 'options' => array('cost' => 10))),
        );
    }

    public function test_func_exists()
    {
        $this->assertTrue(function_exists('password_get_info'));
    }

    /**
     * @dataProvider provide_info
     */
    public function test_info($hash, $info)
    {
        $this->assertEquals($info, password_get_info($hash));
    }
}
