<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\AdminUser;

class AdminUserTest extends \PHPUnit\Framework\TestCase
{
    private $user = null;

    public function setUp(): void
    {
        $this->user = new AdminUser($this->getCore());
    }

    public function testIfExist(): void
    {
        $this->assertTrue($this->user->exists());
    }

    public function testId(): void
    {
        $this->assertSame($this->user->id(), 0);
    }

    public function testName(): void
    {
        $this->assertSame($this->user->name(), 'admin');
    }

    public function testLevel(): void
    {
        $this->assertSame($this->user->level(), User::LEVEL_ADMIN);
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->user->isEnabled());
    }

    public function testToArray(): void
    {
        $this->assertSame(
            [ 'id' => 0, 'name' => 'admin', 'level' => User::LEVEL_ADMIN, 'enabled' => true ],
            $this->user->toArray()
        );
    }

    public function testWrongPassword(): void
    {
        $this->assertFalse(
            (new AdminUser($this->getCoreWithConfigValue('admin/password', 'some')))->verifyPassword('fake')
        );
    }

    public function testEmptyPassword(): void
    {
        $this->assertFalse(
            (new AdminUser($this->getCoreWithConfigValue('admin/password', 'some')))->verifyPassword('')
        );
        $this->assertFalse(
            (new AdminUser($this->getCoreWithConfigValue('admin/password', '')))->verifyPassword('')
        );
    }

    public function testCorrectPassword(): void
    {
        $this->assertTrue(
            (new AdminUser($this->getCoreWithConfigValue('admin/password', 'some')))->verifyPassword('some')
        );
    }

    private function getCore(): object
    {
        return $this->getMockBuilder(Core::class)->disableOriginalConstructor()->setMethods([ 'config' ])->getMock();
    }

    private function getCoreWithConfigValue(string $key, $value)
    {
        $core = $this->getCore();
        $core->expects($this->once())->method('config')->with($this->equalTo($key))->willReturn($value);
        return $core;
    }
}
