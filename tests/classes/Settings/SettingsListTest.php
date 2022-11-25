<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Settings\SettingsList;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Database\DatabaseController;

class SettingsListTest extends \PHPUnit\Framework\TestCase
{
    public function testSettingList(): void
    {
        $real_list = $this->realSettingsList(SettingsList::ORDER_ASCENT);
        $mapp_list = (new SettingsList($this->getDbMapperListOnce()))->setOrder(SettingsList::ORDER_ASCENT)->getList();
        $this->assertIsArray($mapp_list);
        $this->assertFalse($mapp_list['more']);
        $this->assertCount(count($real_list), $mapp_list['list']);
        $this->assertSame($real_list[0], $mapp_list['list'][0]->name());
        $cnt = count($real_list);
        $this->assertSame($real_list[$cnt - 1], $mapp_list['list'][$cnt - 1]->name());

        $real_list = $this->realSettingsList(SettingsList::ORDER_DESCENT);
        $mapp_list = (new SettingsList($this->getDbMapperListOnce()))->setOrder(SettingsList::ORDER_DESCENT)->getList();
        $this->assertSame($real_list[0], $mapp_list['list'][0]->name());
        $cnt = count($real_list);
        $this->assertSame($real_list[$cnt - 1], $mapp_list['list'][$cnt - 1]->name());
    }

    public function testCheckingCorrectSettingName(): void
    {
        $this->assertNull(SettingsList::checkName('version'));
    }

    public function testCheckingWrongSettingName(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Unknown setting name: wrongName');
        $this->assertNull(SettingsList::checkName('wrongName'));
    }

    public function testGettingUnknownSettingByName(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Unknown setting name: someUnknownSetting');
        SettingsList::getSettingByName('someUnknownSetting');
    }

    public function testGettingInternalSetting(): void
    {
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Attempt to access an internal variable');
        SettingsList::getSettingByName('version');
    }

    private function getDbMapperListOnce(): object
    {
        $mapper = $this->getMockBuilder(StdClass::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ 'list' ])
                       ->getMock();
        $mapper->expects($this->once())
               ->method('list')
               ->willReturn([]);
        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())
           ->method('getMapper')
           ->willReturn($mapper);
        return $db;
    }

    private function realSettingsList(int $order): array
    {
        $list = [];
        foreach (SettingsList::$schema as $name => &$props) {
            if (isset($props['public']) && $props['public'] === true) {
                $list[] = $name;
            }
        }
        unset($props);
        if ($order === SettingsList::ORDER_ASCENT) {
            usort($list, static function ($a, $b) {
                return $a <=> $b;
            });
        } elseif ($order === SettingsList::ORDER_DESCENT) {
            usort($list, static function ($a, $b) {
                return $b <=> $a;
            });
        }
        return $list;
    }
}
