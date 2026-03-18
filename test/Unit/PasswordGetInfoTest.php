<?php

declare(strict_types=1);

class PasswordGetInfoTest extends PHPUnit_Framework_TestCase
{
    public static function provideInfo()
    {
        return [
            ['foo', ['algo' => 0, 'algoName' => 'unknown', 'options' => []]],
            ['$2y$', ['algo' => 0, 'algoName' => 'unknown', 'options' => []]],
            ['$2y$07$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', ['algo' => PASSWORD_BCRYPT, 'algoName' => 'bcrypt', 'options' => ['cost' => 7]]],
            ['$2y$10$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi', ['algo' => PASSWORD_BCRYPT, 'algoName' => 'bcrypt', 'options' => ['cost' => 10]]],

        ];
    }

    public function testFuncExists()
    {
        $this->assertTrue(function_exists('password_get_info'));
    }

    /**
     * @dataProvider provideInfo
     */
    public function testInfo($hash, $info)
    {
        $this->assertEquals($info, password_get_info($hash));
    }

}
