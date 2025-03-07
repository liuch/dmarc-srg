<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Settings\SettingStringSelect;
use Liuch\DmarcSrg\Exception\SoftException;

class SettingStringSelectTest extends \PHPUnit\Framework\TestCase
{
    public function testCreatingWithCorrectValue(): void
    {
        $this->assertSame(
            'auto',
            (new SettingStringSelect([
                'name'  => 'ui.datetime.offset',
                'value' => 'auto'
            ], false, $this->getCoreWithDatabaseNever()))->value()
        );
    }

    public function testCreatingWithIncorrectValueType(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        (new SettingStringSelect([
            'name'  => 'ui.datetime.offset',
            'value' => 333
        ], false, $this->getCoreWithDatabaseNever()))->value();
    }

    public function testCreatingWithIncorrectValueRange(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        (new SettingStringSelect([
            'name'  => 'ui.datetime.offset',
            'value' => 'IncorrectValue'
        ], false, $this->getCoreWithDatabaseNever()))->value();
    }

    public function testCreatingWithIncorrectValueTypeWithIgnoring(): void
    {
        $this->assertSame(
            SettingsList::$schema['ui.datetime.offset']['default'],
            (new SettingStringSelect([
                'name'  => 'ui.datetime.offset',
                'value' => 333
            ], true, $this->getCoreWithDatabaseNever()))->value()
        );
    }

    public function testCreatingWithIncorrectValueRangeWithIgnoring(): void
    {
        $this->assertSame(
            SettingsList::$schema['ui.datetime.offset']['default'],
            (new SettingStringSelect([
                'name'  => 'ui.datetime.offset',
                'value' => 'incorrectValue'
            ], true, $this->getCoreWithDatabaseNever()))->value()
        );
    }

    public function testGettingValueByName(): void
    {
        $user = new AdminUser($this->getCore());
        $this->assertSame('utc', (new SettingStringSelect(
            'ui.datetime.offset',
            false,
            $this->getCoreWithDatabaseOnce('value', 'ui.datetime.offset', 'utc', $user)
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
