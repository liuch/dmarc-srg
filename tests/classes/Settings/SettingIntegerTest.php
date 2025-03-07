<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Settings\SettingInteger;
use Liuch\DmarcSrg\Exception\SoftException;

class SettingIntegerTest extends \PHPUnit\Framework\TestCase
{
    public function testCreatingWithCorrectValue(): void
    {
        $this->assertSame(
            222,
            (new SettingInteger([
                'name'  => 'status.emails-for-last-n-days',
                'value' => 222
            ], false, $this->getCoreWithDatabaseNever()))->value()
        );
    }

    public function testCreatingWithIncorrectValue(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        (new SettingInteger([
            'name'  => 'status.emails-for-last-n-days',
            'value' => 'someStringValue'
        ], false, $this->getCoreWithDatabaseNever()))->value();
    }

    public function testCreatingWithIncorrectValueWithIgnoring(): void
    {
        $this->assertSame(
            SettingsList::$schema['status.emails-for-last-n-days']['default'],
            strval(
                (new SettingInteger([
                    'name'  => 'status.emails-for-last-n-days',
                    'value' => 'incorrectIntegerValue'
                ], true, $this->getCoreWithDatabaseNever()))->value()
            )
        );
    }

    public function testGettingValueByName(): void
    {
        $user = new AdminUser($this->getCore());
        $this->assertSame(333, (new SettingInteger(
            'status.emails-for-last-n-days',
            false,
            $this->getCoreWithDatabaseOnce('value', 'status.emails-for-last-n-days', '333', $user)
        ))->value());
    }

    private function getCore(): object
    {
        return $this->getMockBuilder(Core::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods([ 'getCurrentUser', 'database' ])
                    ->getMock();
    }

    private function getCoreWithDatabaseNever(): object
    {
        $core = $this->getCore();
        $core->expects($this->never())->method('database');
        return $core;
    }

    private function getCoreWithDatabaseOnce(string $method, $parameter, $value, $user): object
    {
        $mapper = $this->getMockBuilder(Database\SettingMapperInterface::class)
                       ->onlyMethods([ 'value', 'list', 'save' ])
                       ->getMock();
        $mapper->expects($this->once())
               ->method($method)
               ->with($this->equalTo($parameter))
               ->willReturn($value);
        $db = $this->getMockBuilder(Database\DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->onlyMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->with($this->equalTo('setting'))
           ->willReturn($mapper);

        $core = $this->getCore();
        $core->expects($this->once())->method('getCurrentUser')->willReturn($user);
        $core->expects($this->once())->method('database')->willReturn($db);
        return $core;
    }
}
