<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Settings\SettingString;
use Liuch\DmarcSrg\Settings\SettingInteger;
use Liuch\DmarcSrg\Exception\SoftException;

class SettingTest extends \PHPUnit\Framework\TestCase
{
    public function testCreatingWithCorrectName(): void
    {
        $this->assertInstanceOf(
            SettingString::class,
            new SettingString('version', false, $this->getCoreWithDatabaseNever())
        );
    }

    public function testCreatingWithIncorrectName(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Unknown setting name: some-setting');
        new SettingString('some-setting', false, $this->getCoreWithDatabaseNever());
    }

    public function testCreatingWithIncorrectDataType(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        new SettingString(1, false, $this->getCoreWithDatabaseNever());
    }

    public function testCreatingWithIncorrectNameType(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Wrong setting data');
        new SettingString([ 'name' => 1], false, $this->getCoreWithDatabaseNever());
    }

    public function testSetValue(): void
    {
        $ss = new SettingString('version', false, $this->getCoreWithDatabaseNever());
        $ss->setValue('someString');
        $this->assertSame('someString', $ss->value());
    }

    public function testToArray(): void
    {
        $ss = new SettingString(
            [ 'name' => 'version', 'value' => 'someString' ],
            false,
            $this->getCoreWithDatabaseNever()
        );
        $this->assertEquals([
            'type'    => 'string',
            'name'    => 'version',
            'value'   => 'someString',
            'default' => SettingsList::$schema['version']['default']
        ], $ss->toArray());
    }

    public function testSave(): void
    {
        $user = new AdminUser($this->getCore());
        $ss = new SettingInteger(
            [ 'name' => 'status.emails-for-last-n-days', 'value' => 231, 'user' => $user ],
            false,
            $this->getCoreWithDatabaseMapperOnce('save', 'status.emails-for-last-n-days', '231', 0)
        );
        $ss->save();
    }

    private function getCore(): object
    {
        return $this->getMockBuilder(Core::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods([ 'database' ])
                    ->getMock();
    }

    private function getCoreWithDatabaseNever(): object
    {
        $core = $this->getCore();
        $core->expects($this->never())->method('database');
        return $core;
    }

    private function getCoreWithDatabaseMapperOnce(string $method, string $param1, string $param2, int $param3): object
    {
        $mapper = $this->getMockBuilder(Database\SettingMapperInterface::class)
                       ->onlyMethods([ 'value', 'list', 'save' ])
                       ->getMock();
        $mapper->expects($this->once())
               ->method($method)
               ->with($this->equalTo($param1), $this->equalTo($param2), $this->equalTo($param3));
        $db = $this->getMockBuilder(Database\DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->onlyMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->willReturn($mapper);

        $core = $this->getCore();
        $core->expects($this->once())->method('database')->willReturn($db);
        return $core;
    }
}
