<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Database\DatabaseController;

class StatisticsTest extends \PHPUnit\Framework\TestCase
{
    private $db     = null;
    private $domain = null;

    public function setUp(): void
    {
        $this->db     = $this->createStub(DatabaseController::class);
        $this->domain = new Domain('example.org', $this->db);
    }

    public function testFromTo(): void
    {
        $range = Statistics::fromTo(
            $this->domain,
            new DateTime('2020-01-11'),
            new DateTime('2021-01-01'),
            $this->db
        )->range();
        $this->assertSame('2020-01-11T00:00:00+00:00', $range[0]->format('c'));
        $this->assertSame('2020-12-31T23:59:59+00:00', $range[1]->format('c'));
    }

    public function testLastWeek(): void
    {
        $range = Statistics::lastWeek($this->domain, 0, $this->db)->range();
        $date1 = new DateTime('midnight monday last week');
        $date2 = (clone $date1)->add(new \DateInterval('P1W'))->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }

    public function testLastWeekOffset(): void
    {
        $range = Statistics::lastWeek($this->domain, 5, $this->db)->range();
        $date1 = (new DateTime('midnight monday last week'))->sub(new \DateInterval('P5W'));
        $date2 = (clone $date1)->add(new \DateInterval('P1W'))->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }

    public function testLastMonth(): void
    {
        $range = Statistics::lastMonth($this->domain, 0, $this->db)->range();
        $date1 = new DateTime('midnight first day of last month');
        $date2 = (clone $date1)->add(new \DateInterval('P1M'))->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }

    public function testLastMonthOffset(): void
    {
        $range = Statistics::lastMonth($this->domain, 7, $this->db)->range();
        $date1 = (new DateTime('midnight first day of last month'))->sub(new \DateInterval('P7M'));
        $date2 = (clone $date1)->add(new \DateInterval('P1M'))->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }

    public function testLastNDays(): void
    {
        $range = Statistics::lastNDays($this->domain, 10, 0, $this->db)->range();
        $date2 = new DateTime('midnight');
        $date1 = (clone $date2)->sub(new \DateInterval('P10D'));
        $date2->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }

    public function testLastNDaysOffset(): void
    {
        $range = Statistics::lastNDays($this->domain, 10, 9, $this->db)->range();
        $date2 = (new DateTime('midnight'))->sub(new \DateInterval('P9D'));
        $date1 = (clone $date2)->sub(new \DateInterval('P10D'));
        $date2->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }
}
