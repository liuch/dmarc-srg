<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\DbUser;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;
use Liuch\DmarcSrg\Database\DatabaseController;
use Liuch\DmarcSrg\Database\UserMapperInterface;

class DbUserTest extends \PHPUnit\Framework\TestCase
{
    public function testIfExist(): void
    {
        $user = new DbUser('username', $this->getDatabaseMapperOnce('exists', '', true));
        $this->assertTrue($user->exists());
        $this->assertTrue($user->exists());
        $user = new DbUser('username', $this->getDatabaseMapperOnce('exists', '', false));
        $this->assertFalse($user->exists());
        $this->assertFalse($user->exists());
    }

    public function testZeroId(): void
    {
        $this->expectException(LogicException::class);
        new DbUser([ 'id' => 0 ], $this->getDatabaseNever());
    }

    public function testNegativeId(): void
    {
        $this->expectException(LogicException::class);
        new DbUser([ 'id' => -1 ], $this->getDatabaseNever());
    }

    public function testIdFromData(): void
    {
        $this->assertSame(1, (new DbUser([ 'id' => 1 ], $this->getDatabaseNever()))->id());

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('User not found');
        $user = new DbUser('user1', $this->getDatabaseMapperNotFound('fetch'));
        $user->id();
    }

    public function testIdFromDatabase(): void
    {
        $this->assertSame(2, (new DbUser('some', $this->getDatabaseMapperOnce('fetch', 'id', 2)))->id());

        $user = new DbUser([ 'name' => 'some' ], $this->getDatabaseMapperOnce('fetch', 'id', 3));
        $this->assertSame(3, $user->id());
        $this->assertSame(3, $user->id());
    }

    public function testEmptyName1(): void
    {
        $this->expectException(SoftException::class);
        new DbUser('', $this->getDatabaseNever());
    }

    public function testEmptyName2(): void
    {
        $this->expectException(SoftException::class);
        new DbUser([ 'name' => '', 'id' => 33 ], $this->getDatabaseNever());
    }

    public function testReservedName(): void
    {
        $this->expectException(SoftException::class);
        new DbUser('admin', $this->getDatabaseNever());
    }

    public function testName(): void
    {
        $this->assertSame('user1', (new DbUser('user1', $this->getDatabaseNever()))->name());
        $this->assertSame('user2', (new DbUser([ 'name' => 'user2' ], $this->getDatabaseNever()))->name());
        $user = new DbUser([ 'id' => 88 ], $this->getDatabaseMapperOnce('fetch', 'name', 'user3'));
        $this->assertSame('user3', $user->name());
        $this->assertSame('user3', $user->name());

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('User not found');
        $user = new DbUser([ 'id' => 7 ], $this->getDatabaseMapperNotFound('fetch'));
        $user->name();
    }

    public function testLevel(): void
    {
        $this->assertSame(
            User::LEVEL_USER,
            (new DbUser([ 'name' => 'user1', 'level' => User::LEVEL_USER ], $this->getDatabaseNever()))->level()
        );
        $user = new DbUser('user2', $this->getDatabaseMapperOnce('fetch', 'level', User::LEVEL_MANAGER));
        $this->assertSame(User::LEVEL_MANAGER, $user->level());
        $this->assertSame(User::LEVEL_MANAGER, $user->level());

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('User not found');
        $user = new DbUser('user3', $this->getDatabaseMapperNotFound('fetch'));
        $user->level();
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue(
            (new DbUser([ 'name' => 'user1', 'enabled' => true ], $this->getDatabaseNever()))->isEnabled()
        );
        $this->assertFalse(
            (new DbUser([ 'name' => 'user2', 'enabled' => false ], $this->getDatabaseNever()))->isEnabled()
        );
        $user = new DbUser('user3', $this->getDatabaseMapperOnce('fetch', 'enabled', false));
        $this->assertFalse($user->isEnabled());
        $this->assertFalse($user->isEnabled());

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('User not found');
        $user = new DbUser('user4', $this->getDatabaseMapperNotFound('fetch'));
        $user->isEnabled();
    }

    public function testToArray(): void
    {
        $data = [
            'id'           => 33,
            'name'         => 'user1',
            'enabled'      => true,
            'password'     => true,
            'level'        => User::LEVEL_USER,
            'email'        => 'w@b.c',
            'key'          => 'zxd',
            'domains'      => null,
            'created_time' => new \DateTime(),
            'updated_time' => (new \DateTime())->modify('+1 day')
        ];
        $user = new DbUser($data, $this->getDatabaseNever());
        unset($data['id']);
        $data['password'] = null;
        $data['level'] = User::levelToString($data['level']);
        $this->assertSame($data, $user->toArray());

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('User not found');
        $user = new DbUser('user2', $this->getDatabaseMapperNotFound('fetch'));
        $user->toArray();
    }

    public function testSave(): void
    {
        $user = new DbUser([ 'name' => 'user1', 'id' => 1 ], $this->getDatabaseMapperOnce('save', '', true));
        $user->save();
        $this->assertTrue($user->exists());
    }

    public function testDelete(): void
    {
        $mp = $this->getMockBuilder(UserMapperInterface::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'fetch', 'delete' ])
                   ->getMockForAbstractClass();
        $mp->expects($this->once())->method('fetch');
        $mp->expects($this->once())->method('delete');
        $db = $this->getDatabase();
        $db->expects($this->any())
           ->method('getMapper')
           ->with('user')
           ->willReturn($mp);
        $user = new DbUser('user1', $db);
        $user->delete();
        $this->assertFalse($user->exists());

        $mp = $this->getMockBuilder(UserMapperInterface::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'fetch', 'delete' ])
                   ->getMockForAbstractClass();
        $mp->expects($this->never())->method('fetch');
        $mp->expects($this->once())->method('delete');
        $db = $this->getDatabase();
        $db->expects($this->once())
           ->method('getMapper')
           ->with('user')
           ->willReturn($mp);
        $user = new DbUser([ 'name' => 'user2', 'id' => 2 ], $db);
        $user->delete();
        $this->assertFalse($user->exists());

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('User not found');
        $user = new DbUser('user2', $this->getDatabaseMapperNotFound('fetch'));
        $user->delete();
    }

    public function testEmptyPassword(): void
    {
        $this->assertFalse((new DbUser('user1', $this->getDatabaseNever()))->verifyPassword(''));
    }

    public function testIncorrectPassword(): void
    {
        $user = new DbUser(
            'user1',
            $this->getDatabaseMapperOnce('getPasswordHash', '', password_hash('password1', PASSWORD_DEFAULT))
        );
        $this->assertFalse($user->verifyPassword('fake'));

        $user = new DbUser(
            'user1',
            $this->getDatabaseMapperOnce('getPasswordHash', '', password_hash('', PASSWORD_DEFAULT))
        );
        $this->assertFalse($user->verifyPassword('fake'));
    }

    public function testCorrectPassword(): void
    {
        $user = new DbUser(
            'user1',
            $this->getDatabaseMapperOnce('getPasswordHash', '', password_hash('password1', PASSWORD_DEFAULT))
        );
        $this->assertTrue($user->verifyPassword('password1'));
    }

    public function testPasswordForNonexistentUser(): void
    {
        $this->assertFalse(
            (new DbUser('user1', $this->getDatabaseMapperNotFound('getPasswordHash')))->verifyPassword('some')
        );
    }

    public function testSetPassword(): void
    {
        $mp = $this->getMockBuilder(UserMapperInterface::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'savePasswordHash' ])
                   ->getMockForAbstractClass();
        $mp->expects($this->once())
           ->method('savePasswordHash')
           ->willReturnCallback(function (&$data, $hash) {
               $this->assertTrue(password_verify('password1', $hash));
           });
        $db = $this->getDatabase();
        $db->expects($this->once())
           ->method('getMapper')
           ->with('user')
           ->willReturn($mp);
        (new DbUser('user1', $db))->setPassword('password1');
    }

    private function getDatabase(): object
    {
        return $this->getMockBuilder(DatabaseController::class)
                    ->disableOriginalConstructor()
                    ->setMethods([ 'getMapper' ])
                    ->getMock();
    }

    private function getDatabaseNever(): object
    {
        $db = $this->getDatabase();
        $db->expects($this->never())->method('getMapper');
        return $db;
    }

    private function getDatabaseMapperOnce(string $method, string $key, $value): object
    {
        $mapper = $this->getMockBuilder(UserMapperInterface::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ $method ])
                       ->getMockForAbstractClass();
        $mapper->expects($this->once())
               ->method($method)
               ->willReturnCallback(function (&$data) use ($key, $value) {
                if (!empty($key)) {
                    $data[$key] = $value;
                }
                return $value;
               });

        $db = $this->getDatabase();
        $db->expects($this->once())
           ->method('getMapper')
           ->with('user')
           ->willReturn($mapper);
        return $db;
    }

    private function getDatabaseMapperNotFound($method): object
    {
        $mapper = $this->getMockBuilder(UserMapperInterface::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ $method ])
                       ->getMockForAbstractClass();
        $mapper->expects($this->once())
               ->method($method)
               ->willThrowException(new DatabaseNotFoundException('Not found'));

        $db = $this->getDatabase();
        $db->expects($this->any())
           ->method('getMapper')
           ->with('user')
           ->willReturn($mapper);
        return $db;
    }
}
