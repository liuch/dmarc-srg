<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Settings\SettingString;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Database\DatabaseController;

class SettingStringTest extends \PHPUnit\Framework\TestCase
{
    public function testCreatingWithCorrectValue(): void
    {
        $this->assertSame(
            'someValue',
            (new SettingString([
                'name'  => 'version',
                'value' => 'someValue'
            ], false, $this->getDbMapperNever()))->value()
        );
    }

    public function testCreatingWithIncorrectValue(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        (new SettingString([
            'name'  => 'version',
            'value' => 111
        ], false, $this->getDbMapperNever()))->value();
    }

    public function testCreatingWithIncorrectValueWithIgnoring(): void
    {
        $this->assertSame(
            SettingsList::$schema['version']['default'],
            (new SettingString([
                'name'  => 'version',
                'value' => 111
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
           ->willReturn($this->getDbMapperOnce('value', 'version', 'stringValue'));
        $this->assertSame('stringValue', (new SettingString('version', false, $db))->value());
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
