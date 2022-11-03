<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\AuthException;

class AuthTest extends \PHPUnit\Framework\TestCase
{
    public function testIsEnabledWithNoPassword(): void
    {
        $core = $this->coreWithConfigValue('admin/password', null);
        $this->assertFalse((new Auth($core))->isEnabled());
    }

    public function testIsEnabledWithPassword(): void
    {
        $core = $this->coreWithConfigValue('admin/password', 'some');
        $this->assertTrue((new Auth($core))->isEnabled());
    }

    public function testCorrectAdminPassword(): void
    {
        $core = $this->coreWithConfigValue('admin/password', 'some');
        $this->assertNull((new Auth($core))->checkAdminPassword('some'));
    }

    public function testWrongAdminPassword(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Incorrect password');
        $core = $this->coreWithConfigValue('admin/password', 'some');
        (new Auth($core))->checkAdminPassword('fake');
    }

    public function testEmptyAdminPassword(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'config', 'userId' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn('some');
        $core->expects($this->never())
             ->method('userId');
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Incorrect password');
        (new Auth($core))->checkAdminPassword('');
    }

    public function testCorrectLogin(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'config', 'userId' ])
                     ->getMock();
        $core->expects($this->exactly(2))
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn('some');
        $core->expects($this->once())
             ->method('userId')
             ->with($this->equalTo(0));
        $result = (new Auth($core))->login('', 'some');
        $this->assertIsArray($result);
        $this->assertSame(0, $result['error_code']);
        $this->assertSame('Authentication succeeded', $result['message']);
    }

    public function testLogout(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'destroySession' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('destroySession');
        $result = (new Auth($core))->logout();
        $this->assertArrayHasKey('error_code', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals(0, $result['error_code']);
        $this->assertEquals('Logged out successfully', $result['message']);
    }

    public function testIsAllowedWhenAuthDisabled(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'config', 'userId' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn(null);
        $core->expects($this->never())
             ->method('userId');
        $this->assertNull((new Auth($core))->isAllowed());
    }

    public function testIsAllowedWithActiveSession(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'config', 'userId' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn('some');
        $core->expects($this->once())
             ->method('userId')
             ->willReturn(0);
        $this->assertNull((new Auth($core))->isAllowed());
    }

    public function testIsAllowedWithoutSession(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'config', 'userId' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn('some');
        $core->expects($this->once())
             ->method('userId')
             ->willReturn(false);
        $this->expectException(AuthException::class);
        $this->expectExceptionCode(-2);
        $this->expectExceptionMessage('Authentication needed');
        (new Auth($core))->isAllowed();
    }

    private function coreWithConfigValue(string $key, $value)
    {
        $core = $this->getMockBuilder(Core::class)->disableOriginalConstructor()->setMethods([ 'config' ])->getMock();
        $core->expects($this->once())->method('config')->with($this->equalTo($key))->willReturn($value);
        return $core;
    }
}
