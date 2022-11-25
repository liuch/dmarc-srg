<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Settings\SettingInteger;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Database\DatabaseController;

class SettingIntegerTest extends \PHPUnit\Framework\TestCase
{
    public function testCreatingWithCorrectValue(): void
    {
        $this->assertSame(
            222,
            (new SettingInteger([
                'name'  => 'status.emails-for-last-n-days',
                'value' => 222
            ], false, $this->getDbMapperNever()))->value()
        );
    }

    public function testCreatingWithIncorrectValue(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        (new SettingInteger([
            'name'  => 'status.emails-for-last-n-days',
            'value' => 'someStringValue'
        ], false, $this->getDbMapperNever()))->value();
    }

    public function testCreatingWithIncorrectValueWithIgnoring(): void
    {
        $this->assertSame(
            SettingsList::$schema['status.emails-for-last-n-days']['default'],
            (new SettingInteger([
                'name'  => 'status.emails-for-last-n-days',
                'value' => 'incorrectIntegerValue'
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
           ->willReturn($this->getDbMapperOnce('value', 'status.emails-for-last-n-days', 333));
        $this->assertSame(
            333,
            (new SettingInteger('status.emails-for-last-n-days', false, $db))->value()
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
