<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\LogicException;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $this->conf = new Config('tests/conf_test_file.php');
    }

    public function testEmptyName(): void
    {
        $this->expectException(LogicException::class);
        $this->conf->get('');
    }

    public function testNestedEmptyName(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Incorrect ');
        $this->conf->get('cleaner/');
    }

    public function testNotAllowedName(): void
    {
        $this->assertNull($this->conf->get('some'));
        $this->assertEquals(1, $this->conf->get('some', 1));
    }

    public function testNonexistentParameter(): void
    {
        $this->assertNull($this->conf->get('unknown'));
        $this->assertNull($this->conf->get('unknown', null));
        $this->assertFalse($this->conf->get('unknown', false));
        $this->assertSame(0, $this->conf->get('unknown', 0));
        $this->assertSame('', $this->conf->get('unknown', ''));
        $array = $this->conf->get('unknown', []);
        $this->assertIsArray($array);
        $this->assertEmpty($array);
    }

    public function testBoolParameter(): void
    {
        $this->assertSame(false, $this->conf->get('debug'));
    }

    public function testIntParameter(): void
    {
        $this->assertSame(0, $this->conf->get('database'));
    }

    public function testStringParameter(): void
    {
        $this->assertSame('', $this->conf->get('mailboxes'));
    }

    public function testNullParameter(): void
    {
        $this->assertNull($this->conf->get('directories'));
    }

    public function testArrayParameter(): void
    {
        $array = $this->conf->get('admin');
        $this->assertIsArray($array);
        $this->assertEmpty($array);
    }

    public function testArrayKeyIntParameter(): void
    {
        $this->assertSame(0, $this->conf->get('cleaner/key_int'));
    }

    public function testArrayKeyBoolParameter(): void
    {
        $this->assertFalse($this->conf->get('cleaner/key_bool'));
    }

    public function testArrayKeyStringParameter(): void
    {
        $this->assertSame('', $this->conf->get('cleaner/key_string'));
    }

    public function testArrayKeyNonexistentParameter(): void
    {
        $this->assertSame('default', $this->conf->get('cleaner/key_some', 'default'));
    }
}
