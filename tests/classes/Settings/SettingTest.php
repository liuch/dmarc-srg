<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Settings\SettingString;
use Liuch\DmarcSrg\Settings\SettingInteger;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Database\DatabaseController;

class SettingTest extends \PHPUnit\Framework\TestCase
{
    public function testCreatingWithCorrectName(): void
    {
        $this->assertInstanceOf(SettingString::class, new SettingString('version', false, $this->getDbMapperNever()));
    }

    public function testCreatingWithIncorrectName(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Unknown setting name: some-setting');
        new SettingString('some-setting', false, $this->getDbMapperNever());
    }

    public function testCreatingWithIncorrectDataType(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        new SettingString(1, false, $this->getDbMapperNever());
    }

    public function testCreatingWithIncorrectNameType(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        new SettingString([ 'name' => 1], false, $this->getDbMapperNever());
    }

    public function testSetValue(): void
    {
        $ss = new SettingString('version', false, $this->getDbMapperNever());
        $ss->setValue('someString');
        $this->assertSame('someString', $ss->value());
    }

    public function testToArray(): void
    {
        $ss = new SettingString([ 'name' => 'version', 'value' => 'someString' ], false, $this->getDbMapperNever());
        $this->assertEquals([
            'type'    => 'string',
            'name'    => 'version',
            'value'   => 'someString',
            'default' => SettingsList::$schema['version']['default']
        ], $ss->toArray());
    }

    public function testSave(): void
    {
        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->with($this->equalTo('setting'))
           ->willReturn($this->getDbMapperOnce('save', 'status.emails-for-last-n-days', '231'));
        $ss = new SettingInteger(
            [ 'name' => 'status.emails-for-last-n-days', 'value' => 231 ],
            false,
            $db
        );
        $ss->save();
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

    private function getDbMapperOnce(string $method, string $param1, string $param2): object
    {
        $mapper = $this->getMockBuilder(StdClass::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ $method ])
                       ->getMock();
        $mapper->expects($this->once())
               ->method($method)
               ->with($this->equalTo($param1), $this->equalTo($param2));
        return $mapper;
    }
}
