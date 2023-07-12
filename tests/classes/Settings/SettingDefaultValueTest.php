<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\AdminUser;
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

            $user = new AdminUser($this->getCore());
            $cc = new $ss([
                'name'  => $name,
                'value' => $val
            ], true, $this->getCoreWithDatabaseNever($user));
            $this->assertSame($props['default'], strval($cc->value()), "Name: {$name}; Constructor Value");

            if ($t2) {
                $cc = new $ss($name, true, $this->getCoreWithDatabaseMapperOnce($name, $user, $val));
                $this->assertSame($props['default'], strval($cc->value()), "Name: {$name}; Database Value");
            }

            unset($ss);
        }
        unset($props);
    }

    public function testSettingNotFoundDefaultValue(): void
    {
        foreach (SettingsList::$schema as $name => &$props) {
            $user = new AdminUser($this->getCore());
            switch ($props['type']) {
                case 'string':
                    $cc = new SettingString($name, true, $this->getCoreWithDatabaseMapperNotFound($name, $user));
                    break;
                case 'integer':
                    $cc = new SettingInteger($name, true, $this->getCoreWithDatabaseMapperNotFound($name, $user));
                    break;
                case 'select':
                    $cc = new SettingStringSelect($name, true, $this->getCoreWithDatabaseMapperNotFound($name, $user));
                    break;
            }
            $cc->value();
            unset($cc);
        }
        unset($props);
    }

    private function getCore(): object
    {
        return $this->getMockBuilder(Core::class)
                    ->disableOriginalConstructor()
                    ->setMethods([ 'user', 'database' ])
                    ->getMock();
    }

    private function getCoreWithDatabaseNever($user): object
    {
        $core = $this->getCore();
        $core->expects($this->never())->method('user')->willReturn($user);
        $core->expects($this->never())->method('database');
        return $core;
    }

    private function getCoreWithDatabaseMapperOnce($key, $user, $value): object
    {
        $mapper = $this->getMockBuilder(StdClass::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ 'value' ])
                       ->getMock();
        $mapper->expects($this->once())
               ->method('value')
               ->with($key, $user->id())
               ->willReturn($value);

        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->willReturn($mapper);

        $core = $this->getCore();
        $core->expects($this->once())->method('user')->willReturn($user);
        $core->expects($this->once())->method('database')->willReturn($db);
        return $core;
    }

    private function getCoreWithDatabaseMapperNotFound($parameter, $user): object
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

        $core = $this->getCore();
        $core->expects($this->once())->method('user')->willReturn($user);
        $core->expects($this->once())->method('database')->willReturn($db);
        return $core;
    }
}
