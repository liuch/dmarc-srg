<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\DbUser;
use Liuch\DmarcSrg\Users\UserList;
use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Database\DatabaseController;
use Liuch\DmarcSrg\Database\UserMapperInterface;

class UserListTest extends \PHPUnit\Framework\TestCase
{
    public function testGetList(): void
    {
        $res = (new UserList($this->getDatabaseMapperOnce(
            'list',
            [
                [ 'id' => 1, 'name' => 'user1' ],
                [ 'id' => 2, 'name' => 'user2' ]
            ]
        )))->getList();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('more', $res);
        $this->assertArrayHasKey('users', $res);
        $this->assertIsBool($res['more']);
        $this->assertIsArray($res['users']);
        $this->assertCount(2, $res['users']);
        $idx = 1;
        foreach ($res['users'] as $user) {
            $this->assertInstanceOf(DbUser::class, $user);
            $this->assertSame($idx, $user->id());
            $this->assertSame("user{$idx}", $user->name());
            ++$idx;
        }
    }

    public function testGetUserByName(): void
    {
        $this->assertInstanceOf(
            AdminUser::class,
            UserList::getUserByName('admin', $this->getCore())
        );
        $this->assertInstanceOf(
            DbUser::class,
            UserList::getUserByName('user1', $this->getCoreWithDatabaseMapperOnce('exists', true))
        );
        $this->expectException(SoftException::class);
        UserList::getUserByName('unknown', $this->getCoreWithDatabaseMapperOnce('exists', false));
    }

    private function getCore(): object
    {
        return $this->getMockBuilder(Core::class)->disableOriginalConstructor()->getMock();
    }

    private function getDatabaseMapperOnce(string $method, $value): object
    {
        $mapper = $this->getMockBuilder(UserMapperInterface::class)
                       ->disableOriginalConstructor()
                       ->getMock();
        $mapper->expects($this->once())
               ->method($method)
               ->willReturn($value);

        $db = $this->getMockBuilder(DatabaseController::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods([ 'getMapper' ])
                    ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->with('user')
           ->willReturn($mapper);
        return $db;
    }

    private function getCoreWithDatabaseMapperOnce(string $method, $value): object
    {
        $db = $this->getDatabaseMapperOnce($method, $value);
        $core = $this->getCore();
        $core->expects($this->once())
             ->method('database')
             ->willReturn($db);
        return $core;
    }
}
