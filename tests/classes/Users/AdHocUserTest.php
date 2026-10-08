<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\AdHocUser;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\ForbiddenException;

class AdHocUserTest extends \PHPUnit\Framework\TestCase
{
    private $user = null;

    public function testIfExist(): void
    {
        $this->assertTrue((new AdHocUser())->exists());
    }

    public function testId(): void
    {
        $this->assertSame((new AdHocUser())->id(), -1);
    }

    public function testName(): void
    {
        $user = new AdHocUser();
        $this->expectException(LogicException::class);
        $user->name();
    }

    public function testLevel(): void
    {
        $this->assertSame((new AdHocUser())->level(), User::LEVEL_SERVICE);
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue((new AdHocUser())->isEnabled());
    }

    public function testPermissions(): void
    {
        $user = new AdHocUser();
        $this->assertFalse($user->hasPermission(User::PERM_DOMAIN_REGISTER_FIRST));
        $this->assertFalse($user->hasPermission(User::PERM_REPORTS_IMPORT_ANY_OWNER));

        $user = new AdHocUser(User::PERM_DOMAIN_REGISTER_FIRST);
        $this->assertTrue($user->hasPermission(User::PERM_DOMAIN_REGISTER_FIRST));
        $this->assertFalse($user->hasPermission(User::PERM_REPORTS_IMPORT_ANY_OWNER));
        $this->assertFalse(
            $user->hasPermission(User::PERM_DOMAIN_REGISTER_FIRST + User::PERM_REPORTS_IMPORT_ANY_OWNER)
        );

        $user = new AdHocUser(User::PERM_DOMAIN_REGISTER_FIRST + User::PERM_REPORTS_IMPORT_ANY_OWNER);
        $this->assertTrue($user->hasPermission(User::PERM_DOMAIN_REGISTER_FIRST));
        $this->assertTrue($user->hasPermission(User::PERM_REPORTS_IMPORT_ANY_OWNER));
        $this->assertTrue(
            $user->hasPermission(User::PERM_DOMAIN_REGISTER_FIRST + User::PERM_REPORTS_IMPORT_ANY_OWNER)
        );
    }

    public function testPermissionsStrict(): void
    {
        $user = new AdHocUser();
        $this->expectException(ForbiddenException::class);
        $user->hasPermission(User::PERM_DOMAIN_REGISTER_FIRST, true);
    }

    public function testToArray(): void
    {
        $user = new AdHocUser();
        $this->expectException(LogicException::class);
        $user->toArray();
    }

    public function testVerifyPassword(): void
    {
        $user = new AdHocUser();
        $this->expectException(LogicException::class);
        $user->verifyPassword('secret');
    }
}
