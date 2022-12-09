<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\ReportLog\ReportLog;
use Liuch\DmarcSrg\Database\ReportLogMapperInterface;

class ReportLogTest extends \PHPUnit\Framework\TestCase
{
    protected static $from = null;
    protected static $till = null;

    public static function setUpBeforeClass(): void
    {
        self::$from = (new \DateTime)->sub(new \DateInterval('P10D'));
        self::$till = new \DateTime();
    }

    public static function tearDownAfterClass(): void
    {
        self::$from = null;
        self::$till = null;
    }

    public function testRange(): void
    {
        $callback = function ($filter, $order, $limit) {
            $this->assertSame([ 'from_time' => self::$from, 'till_time' => self::$till ], $filter);
            $this->assertSame(11, $limit['offset']);
            return [];
        };
        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('list', $callback));
        $rl->getList(11);
    }

    public function testSettingOrder(): void
    {
        $callback_ascent = function ($filter, $order, $limit) {
            $this->assertSame([ 'direction' => 'ascent' ], $order);
            return [];
        };
        $callback_descent = function ($filter, $order, $limit) {
            $this->assertSame([ 'direction' => 'descent' ], $order);
            return [];
        };

        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('list', $callback_ascent));
        $rl->getList(0);

        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('list', $callback_ascent));
        $rl->setOrder(ReportLog::ORDER_ASCENT);
        $rl->getList(0);

        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('list', $callback_descent));
        $rl->setOrder(ReportLog::ORDER_DESCENT);
        $rl->getList(0);
    }

    public function testSettingMaxCount(): void
    {
        $callback26 = function ($filter, $order, $limit) {
            $this->assertSame(26, $limit['count']);
            return [];
        };
        $callback51 = function ($filter, $order, $limit) {
            $this->assertSame(51, $limit['count']);
            return [];
        };

        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('list', $callback26));
        $rl->getList(0);

        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('list', $callback51));
        $rl->setMaxCount(50);
        $rl->getList(0);
    }

    public function testGettingCount(): void
    {
        $callback = function ($filter, $limit) {
            $this->assertSame([ 'from_time' => self::$from, 'till_time' => self::$till ], $filter);
            $this->assertSame([ 'offset' => 0, 'count' => 44 ], $limit);
            return 55;
        };
        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('count', $callback));
        $rl->setMaxCount(44);
        $this->assertSame(55, $rl->count());
    }

    public function testGettingList(): void
    {
        $callback = function () {
            return [
                [
                    'id'          => 1,
                    'domain'      => null,
                    'external_id' => null,
                    'event_time'  => null,
                    'filename'    => null,
                    'source'      => 0,
                    'success'     => false,
                    'message'     => null
                ],
                [
                    'id'          => 2,
                    'domain'      => null,
                    'external_id' => null,
                    'event_time'  => null,
                    'filename'    => null,
                    'source'      => 0,
                    'success'     => false,
                    'message'     => null
                ]

            ];
        };

        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('list', $callback));
        $rl->setMaxCount(1);
        $res = $rl->getList(0);
        $this->assertTrue($res['more']);
        $this->assertCount(1, $res['items']);

        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('list', $callback));
        $rl->setMaxCount(2);
        $res = $rl->getList(0);
        $this->assertFalse($res['more']);
        $this->assertCount(2, $res['items']);
    }

    public function testDeleting(): void
    {
        $callback = function ($filter, $order, $limit) {
            $this->assertSame([ 'from_time' => self::$from, 'till_time' => self::$till ], $filter);
            $this->assertSame([ 'direction' => 'ascent' ], $order);
            $this->assertSame([ 'offset' => 0, 'count' => 33 ], $limit);
        };
        $rl = new ReportLog(self::$from, self::$till, $this->getDbMapperOnce('delete', $callback));
        $rl->setOrder(ReportLog::ORDER_ASCENT);
        $rl->setMaxCount(33);
        $rl->delete();
    }

    private function getDbMapperOnce(string $method, $callback): object
    {
        $mapper = $this->getMockBuilder(ReportLogMapperInterface::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ $method ])
                       ->getMockForAbstractClass();
        $mapper->expects($this->once())
               ->method($method)
               ->willReturnCallback($callback);

        $db = $this->getMockBuilder(\StdClass::class)
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->method('getMapper')
           ->with('report-log')
           ->willReturn($mapper);

        return $db;
    }
}
