<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Exception\AuthException;
use Liuch\DmarcSrg\Database\DatabaseController;
use Liuch\DmarcSrg\Database\UserMapperInterface;

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

    public function testCorrectLogin(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'config', 'user' ])
                     ->getMock();
        $core->expects($this->exactly(3))
             ->method('config')
             ->with($this->logicalOr(
                 $this->equalTo('admin/password'),
                 $this->equalTo('admin/user_management')
             ))
             ->will($this->returnCallback(function ($param) {
                 return $param === 'admin/password' ? 'some' : false;
             }));
        $core->expects($this->once())
             ->method('user')
             ->with($this->callback(function ($param) {
                 return $param instanceof User;
             }));
        $result = (new Auth($core))->login('', 'some');
        $this->assertIsArray($result);
        $this->assertSame(0, $result['error_code']);
        $this->assertSame('Authentication succeeded', $result['message']);
    }

    public function testLoginWithIncorrectUsername(): void
    {
        $mp = $this->getMockBuilder(UserMapperInterface::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'exists' ])
                   ->getMockForAbstractClass();
        $mp->expects($this->once())
           ->method('exists')
           ->willReturn(false);

        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->with('user')
           ->willReturn($mp);

        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'database', 'config' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('database')
             ->willReturn($db);
        $core->expects($this->exactly(2))
             ->method('config')
             ->with($this->logicalOr(
                 $this->equalTo('admin/password'),
                 $this->equalTo('admin/user_management')
             ))
             ->will($this->returnCallback(function ($param) {
                 return $param === 'admin/password' ? 'some' : true;
             }));

        $this->expectException(AuthException::class);
        (new Auth($core))->login('fake_user', 'password');
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
                     ->setMethods([ 'config', 'user' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn(null);
        $core->expects($this->never())
             ->method('user');
        $this->assertNull((new Auth($core))->isAllowed(User::LEVEL_ADMIN));
    }

    public function testIsAllowedWithActiveSession(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'config', 'user' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn('some');
        $core->expects($this->once())
             ->method('user')
             ->willReturn(new AdminUser($core));
        $this->assertNull((new Auth($core))->isAllowed(User::LEVEL_ADMIN));
    }

    public function testIsAllowedWithoutSession(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->setMethods([ 'config', 'user' ])
                     ->getMock();
        $core->expects($this->exactly(3))
             ->method('config')
             ->with($this->logicalOr(
                 $this->equalTo('admin/password'),
                 $this->equalTo('admin/user_management')
             ))
             ->will($this->returnCallback(function ($param) {
                 return $param === 'admin/password' ? 'some' : false;
             }));
        $core->expects($this->once())
             ->method('user')
             ->willReturn(null);
        $this->expectException(AuthException::class);
        $this->expectExceptionCode(-2);
        $this->expectExceptionMessage('Authentication needed');
        (new Auth($core))->isAllowed(User::LEVEL_ADMIN);
    }

    private function coreWithConfigValue(string $key, $value)
    {
        $core = $this->getMockBuilder(Core::class)->disableOriginalConstructor()->setMethods([ 'config' ])->getMock();
        $core->expects($this->once())->method('config')->with($this->equalTo($key))->willReturn($value);
        return $core;
    }
}
