<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Settings\SettingStringSelect;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Database\DatabaseController;

class SettingStringSelectTest extends \PHPUnit\Framework\TestCase
{
    public function testCreatingWithCorrectValue(): void
    {
        $this->assertSame(
            'auto',
            (new SettingStringSelect([
                'name'  => 'ui.datetime.offset',
                'value' => 'auto'
            ], false, $this->getDbMapperNever()))->value()
        );
    }

    public function testCreatingWithIncorrectValueType(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        (new SettingStringSelect([
            'name'  => 'ui.datetime.offset',
            'value' => 333
        ], false, $this->getDbMapperNever()))->value();
    }

    public function testCreatingWithIncorrectValueRange(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        (new SettingStringSelect([
            'name'  => 'ui.datetime.offset',
            'value' => 'IncorrectValue'
        ], false, $this->getDbMapperNever()))->value();
    }

    public function testCreatingWithIncorrectValueTypeWithIgnoring(): void
    {
        $this->assertSame(
            SettingsList::$schema['ui.datetime.offset']['default'],
            (new SettingStringSelect([
                'name'  => 'ui.datetime.offset',
                'value' => 333
            ], true, $this->getDbMapperNever()))->value()
        );
    }

    public function testCreatingWithIncorrectValueRangeWithIgnoring(): void
    {
        $this->assertSame(
            SettingsList::$schema['ui.datetime.offset']['default'],
            (new SettingStringSelect([
                'name'  => 'ui.datetime.offset',
                'value' => 'incorrectValue'
            ], true, $this->getDbMapperNever()))->value()
        );
    }

    public function testGettingValueByName(): void
    {
        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->with($this->equalTo('setting'))
           ->willReturn($this->getDbMapperOnce('value', 'ui.datetime.offset', 'utc'));
        $this->assertSame(
            'utc',
            (new SettingStringSelect('ui.datetime.offset', false, $db))->value()
        );
    }

    private function getDbMapperNever(): object
    {
        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->never())->method('getMapper');
        return $db;
    }

    private function getDbMapperOnce(string $method, $parameter, $value): object
    {
        $mapper = $this->getMockBuilder(StdClass::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ $method ])
                       ->getMock();
        $mapper->expects($this->once())
               ->method($method)
               ->with($this->equalTo($parameter))
               ->willReturn($value);
        return $mapper;
    }
}
