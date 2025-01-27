<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Database\DatabaseController;

class DatabaseControllerTest extends \PHPUnit\Framework\TestCase
{
    public function testGettingType(): void
    {
        $ctl = new DatabaseController($this->getCoreWithSettings([]));
        $this->assertSame('', $ctl->type());
        $ctl = new DatabaseController($this->getCoreWithSettings([ 'type' => 'dbType' ]));
        $this->assertSame('dbType', $ctl->type());
    }

    public function testGettingName(): void
    {
        $ctl = new DatabaseController($this->getCoreWithSettings([]));
        $this->assertSame('', $ctl->name());
        $ctl = new DatabaseController($this->getCoreWithSettings([ 'name' => 'dbName' ]));
        $this->assertSame('dbName', $ctl->name());
    }

    public function testGettingLocation(): void
    {
        $ctl = new DatabaseController($this->getCoreWithSettings([]));
        $this->assertSame('', $ctl->location());
        $ctl = new DatabaseController($this->getCoreWithSettings([ 'host' => 'dbLocation' ]));
        $this->assertSame('dbLocation', $ctl->location());
    }

    public function testGettingState(): void
    {
        $callback = function () {
            return [
                'someParam' => 'someValue',
                'correct'   => true,
                'version'   => DatabaseController::REQUIRED_VERSION
            ];
        };
        $ctl = new DatabaseController(
            $this->getCoreWithSettings([
                'type' => 'dbType',
                'name' => 'dbName',
                'host' => 'dbLocation'
            ]),
            $this->getConnector('state', $callback)
        );
        $res = $ctl->state();
        $this->assertIsArray($res);
        $this->assertTrue($res['correct']);
        $this->assertFalse($res['needs_upgrade'] ?? false);
        $this->assertSame('someValue', $res['someParam']);
        $this->assertSame('dbType', $res['type']);
        $this->assertSame('dbName', $res['name']);
        $this->assertSame('dbLocation', $res['location']);
    }

    public function testGettingStateNeedsUpgrating(): void
    {
        $callback1 = function () {
            return [ 'correct' => true ];
        };
        $callback2 = function () {
            return [ 'correct' => false ];
        };

        $ctl = new DatabaseController($this->getCoreWithSettings([]), $this->getConnector('state', $callback1));
        $res = $ctl->state();
        $this->assertFalse($res['correct']);
        $this->assertTrue($res['needs_upgrade']);
        $this->assertArrayHasKey('message', $res);

        $ctl = new DatabaseController($this->getCoreWithSettings([]), $this->getConnector('state', $callback2));
        $res = $ctl->state();
        $this->assertFalse($res['correct']);
        $this->assertFalse($res['needs_upgrade'] ?? false);
    }

    public function testInitDb(): void
    {
        $callback = function () {
        };
        $ctl = new DatabaseController($this->getCoreWithSettings([]), $this->getConnector('initDb', $callback));
        $res = $ctl->initDb();
        $this->assertIsArray($res);
        $this->assertSame(0, $res['error_code'] ?? 0);
        $this->assertArrayHasKey('message', $res);
    }

    public function testCleanDb(): void
    {
        $callback = function () {
        };
        $ctl = new DatabaseController($this->getCoreWithSettings([]), $this->getConnector('cleanDb', $callback));
        $res = $ctl->cleanDb();
        $this->assertIsArray($res);
        $this->assertSame(0, $res['error_code'] ?? 0);
        $this->assertArrayHasKey('message', $res);
    }

    public function testGettingMapper(): void
    {
        $callback = function ($param) {
            $this->assertSame('mapperId', $param);
            return new \StdClass();
        };
        $ctl = new DatabaseController($this->getCoreWithSettings([]), $this->getConnector('getMapper', $callback));
        $ctl->getMapper('mapperId');
    }

    private function getCoreWithSettings($data): object
    {
        $core = $this->createMock(Core::class);
        $core->expects($this->once())
             ->method('config')
             ->with('database')
             ->willReturn($data);
        return $core;
    }

    private function getConnector(string $method, $callback): object
    {
        $con = $this->getMockBuilder(Database\DatabaseConnector::class)
                    ->disableOriginalConstructor()
                    ->getMock();
        $con->expects($this->once())
            ->method($this->equalTo($method))
            ->willReturnCallback($callback);
        return $con;
    }
}
