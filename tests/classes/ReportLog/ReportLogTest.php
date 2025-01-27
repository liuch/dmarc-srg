<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Sources\Source;
use Liuch\DmarcSrg\ReportLog\ReportLog;

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

    public function testRangeFilter(): void
    {
        $callback = function ($filter, $order, $limit) {
            $this->assertSame([ 'from_time' => self::$from, 'till_time' => self::$till ], $filter);
            $this->assertSame(11, $limit['offset']);
            return [];
        };
        (new ReportLog($this->getDbMapperOnce('list', $callback)))
            ->setFilter([ 'from_time' => self::$from, 'till_time' => self::$till ])
            ->getList(11);
    }

    public function testFilterWithSuccessValue(): void
    {
        $callback1 = function ($filter, $order, $limit) {
            $this->assertSame([ 'success' => true ], $filter);
            return [];
        };
        $callback2 = function ($filter, $order, $limit) {
            $this->assertSame([ 'success' => false ], $filter);
            return [];
        };
        (new ReportLog($this->getDbMapperOnce('list', $callback1)))
            ->setFilter([ 'success' => true ])
            ->getList(0);
        (new ReportLog($this->getDbMapperOnce('list', $callback2)))
            ->setFilter([ 'success' => false ])
            ->getList(0);
    }

    public function testFilterWithSourceValue(): void
    {
        $callback1 = function ($filter, $order, $limit) {
            $this->assertSame([ 'source' => Source::SOURCE_UPLOADED_FILE ], $filter);
            return [];
        };
        $callback2 = function ($filter, $order, $limit) {
            $this->assertSame([ 'source' => Source::SOURCE_MAILBOX ], $filter);
            return [];
        };
        $callback3 = function ($filter, $order, $limit) {
            $this->assertSame([ 'source' => Source::SOURCE_DIRECTORY ], $filter);
            return [];
        };
        (new ReportLog($this->getDbMapperOnce('list', $callback1)))
            ->setFilter([ 'source' => 'uploaded_file' ])
            ->getList(0);
        (new ReportLog($this->getDbMapperOnce('list', $callback2)))
            ->setFilter([ 'source' => 'email' ])
            ->getList(0);
        (new ReportLog($this->getDbMapperOnce('list', $callback3)))
            ->setFilter([ 'source' => 'directory' ])
            ->getList(0);
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
        (new ReportLog($this->getDbMapperOnce('list', $callback_ascent)))
            ->getList(0);

        (new ReportLog($this->getDbMapperOnce('list', $callback_ascent)))
            ->setOrder(ReportLog::ORDER_ASCENT)
            ->getList(0);
        (new ReportLog($this->getDbMapperOnce('list', $callback_descent)))
            ->setOrder(ReportLog::ORDER_DESCENT)
            ->getList(0);
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
        (new ReportLog($this->getDbMapperOnce('list', $callback26)))
            ->getList(0);
        (new ReportLog($this->getDbMapperOnce('list', $callback51)))
            ->setMaxCount(50)
            ->getList(0);
    }

    public function testGettingCount(): void
    {
        $callback = function ($filter, $limit) {
            $this->assertSame([ 'from_time' => self::$from, 'till_time' => self::$till ], $filter);
            $this->assertSame([ 'offset' => 0, 'count' => 44 ], $limit);
            return 55;
        };
        $rl = (new ReportLog($this->getDbMapperOnce('count', $callback)))
            ->setFilter([ 'from_time' => self::$from, 'till_time' => self::$till ])
            ->setMaxCount(44);
        $this->assertSame(55, $rl->count());
    }

    public function testGettingList(): void
    {
        $callback = function () {
            return [
                [
                    'id'          => 1,
                    'domain'      => null,
                    'report_id'   => null,
                    'event_time'  => null,
                    'filename'    => null,
                    'source'      => 0,
                    'success'     => false,
                    'message'     => null
                ],
                [
                    'id'          => 2,
                    'domain'      => null,
                    'report_id'   => null,
                    'event_time'  => null,
                    'filename'    => null,
                    'source'      => 0,
                    'success'     => false,
                    'message'     => null
                ]

            ];
        };

        $res = (new ReportLog($this->getDbMapperOnce('list', $callback)))
            ->setMaxCount(1)
            ->getList(0);
        $this->assertTrue($res['more']);
        $this->assertCount(1, $res['items']);

        $res = (new ReportLog($this->getDbMapperOnce('list', $callback)))
            ->setMaxCount(2)
            ->getList(0);
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
        (new ReportLog($this->getDbMapperOnce('delete', $callback)))
            ->setFilter([ 'from_time' => self::$from, 'till_time' => self::$till ])
            ->setOrder(ReportLog::ORDER_ASCENT)
            ->setMaxCount(33)
            ->delete();
    }

    private function getDbMapperOnce(string $method, $callback): object
    {
        $mapper = $this->getMockBuilder(Database\ReportLogMapperInterface::class)
                       ->getMock();
        $mapper->expects($this->once())
               ->method($method)
               ->willReturnCallback($callback);

        $db = $this->getMockBuilder(Database\DatabaseConnector::class)
                   ->disableOriginalConstructor()
                   ->getMock();
        $db->method('getMapper')
           ->with('report-log')
           ->willReturn($mapper);

        return $db;
    }
}
