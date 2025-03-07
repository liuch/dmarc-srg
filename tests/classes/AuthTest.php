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
                     ->onlyMethods([ 'config', 'setCurrentUser' ])
                     ->getMock();
        $core->expects($this->exactly(3))
             ->method('config')
             ->with($this->logicalOr(
                 $this->equalTo('admin/password'),
                 $this->equalTo('users/user_management')
             ))
             ->willReturnCallback(function ($param) {
                 return $param === 'admin/password' ? 'some' : false;
             });
        $core->expects($this->once())
             ->method('setCurrentUser')
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
                   ->getMock();
        $mp->expects($this->once())
           ->method('exists')
           ->willReturn(false);

        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->onlyMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->with('user')
           ->willReturn($mp);

        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->onlyMethods([ 'database', 'config' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('database')
             ->willReturn($db);
        $core->expects($this->exactly(2))
             ->method('config')
             ->with($this->logicalOr(
                 $this->equalTo('admin/password'),
                 $this->equalTo('users/user_management')
             ))
             ->willReturnCallback(function ($param) {
                 return $param === 'admin/password' ? 'some' : true;
             });

        $this->expectException(AuthException::class);
        (new Auth($core))->login('fake_user', 'password');
    }

    public function testLogout(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->onlyMethods([ 'setCurrentUser' ])
                     ->disableOriginalConstructor()
                     ->getMock();
        $core->expects($this->once())->method('setCurrentUser')->with(null);

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
                     ->onlyMethods([ 'config', 'getCurrentUser' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn(null);
        $core->expects($this->never())
             ->method('getCurrentUser');
        $this->assertNull((new Auth($core))->isAllowed(User::LEVEL_ADMIN));
    }

    public function testIsAllowedWithActiveSession(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->onlyMethods([ 'config', 'getCurrentUser' ])
                     ->getMock();
        $core->expects($this->once())
             ->method('config')
             ->with($this->equalTo('admin/password'))
             ->willReturn('some');
        $core->expects($this->once())
             ->method('getCurrentUser')
             ->willReturn(new AdminUser($core));
        $this->assertNull((new Auth($core))->isAllowed(User::LEVEL_ADMIN));
    }

    public function testIsAllowedWithoutSession(): void
    {
        $core = $this->getMockBuilder(Core::class)
                     ->disableOriginalConstructor()
                     ->onlyMethods([ 'config', 'getCurrentUser' ])
                     ->getMock();
        $core->expects($this->exactly(3))
             ->method('config')
             ->with($this->logicalOr(
                 $this->equalTo('admin/password'),
                 $this->equalTo('users/user_management')
             ))
             ->willReturnCallback(function ($param) {
                 return $param === 'admin/password' ? 'some' : false;
             });
        $core->expects($this->once())
             ->method('getCurrentUser')
             ->willReturn(null);
        $this->expectException(AuthException::class);
        $this->expectExceptionCode(-2);
        $this->expectExceptionMessage('Authentication needed');
        (new Auth($core))->isAllowed(User::LEVEL_ADMIN);
    }

    private function coreWithConfigValue(string $key, $value)
    {
        $core = $this->getMockBuilder(Core::class)->disableOriginalConstructor()->onlyMethods([ 'config' ])->getMock();
        $core->expects($this->once())->method('config')->with($this->equalTo($key))->willReturn($value);
        return $core;
    }
}
