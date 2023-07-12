<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Settings\SettingInteger;
use Liuch\DmarcSrg\Settings\SettingString;
use Liuch\DmarcSrg\Settings\SettingStringSelect;
use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Database\DatabaseController;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;

class SettingDefaultValueTest extends \PHPUnit\Framework\TestCase
{
    public function testSettingDefaultValue(): void
    {
        foreach (SettingsList::$schema as $name => &$props) {
            switch ($props['type']) {
                case 'string':
                    $t2 = false;
                    $val = 0;
                    $ss = 'Liuch\DmarcSrg\Settings\SettingString';
                    break;
                case 'integer':
                    $t2 = true;
                    $val = '';
                    $ss = 'Liuch\DmarcSrg\Settings\SettingInteger';
                    break;
                case 'select':
                    $t2 = true;
                    $val = '0';
                    $ss = 'Liuch\DmarcSrg\Settings\SettingStringSelect';
                    break;
            }

            $cc = new $ss([ 'name' => $name, 'value' => $val ], true, $this->getDbMapperNever());
            $this->assertSame($props['default'], strval($cc->value()), "Name: {$name}; Constructor Value");

            if ($t2) {
                $cc = new $ss($name, true, $this->getDbMapperOnce($name, $val));
                $this->assertSame($props['default'], strval($cc->value()), "Name: {$name}; Database Value");
            }

            unset($ss);
        }
        unset($props);
    }

    public function testSettingNotFoundDefaultValue(): void
    {
        foreach (SettingsList::$schema as $name => &$props) {
            switch ($props['type']) {
                case 'string':
                    $cc = new SettingString($name, true, $this->getDbMapperNotFound($name));
                    break;
                case 'integer':
                    $cc = new SettingInteger($name, true, $this->getDbMapperNotFound($name));
                    break;
                case 'select':
                    $cc = new SettingStringSelect($name, true, $this->getDbMapperNotFound($name));
                    break;
            }
            $cc->value();
            unset($cc);
        }
        unset($props);
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

    private function getDbMapperOnce($parameter, $value): object
    {
        $mapper = $this->getMockBuilder(StdClass::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ 'value' ])
                       ->getMock();
        $mapper->expects($this->once())
               ->method('value')
               ->with($this->equalTo($parameter))
               ->willReturn($value);

        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->willReturn($mapper);

        return $db;
    }

    private function getDbMapperNotFound($parameter): object
    {
        $mapper = $this->getMockBuilder(StdClass::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ 'value' ])
                       ->getMock();
        $mapper->expects($this->once())
               ->method('value')
               ->with($this->equalTo($parameter))
               ->willThrowException(new DatabaseNotFoundException());

        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->willReturn($mapper);

        return $db;
    }
}
