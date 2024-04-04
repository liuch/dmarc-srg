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
        $range = Statistics::lastWeek($this->domain, $this->db)->range();
        $date1 = new DateTime('midnight monday last week');
        $date2 = (new DateTime('midnight monday this week'))->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }

    public function testLastMonth(): void
    {
        $range = Statistics::lastMonth($this->domain, $this->db)->range();
        $date1 = new DateTime('midnight first day of last month');
        $date2 = (new DateTime('midnight first day of this month'))->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }

    public function testLastNDays(): void
    {
        $range = Statistics::lastNDays($this->domain, 10, $this->db)->range();
        $date2 = new DateTime('midnight');
        $date1 = (clone $date2)->sub(new \DateInterval('P10D'));
        $date2->sub(new \DateInterval('PT1S'));
        $this->assertSame($date1->format('c'), $range[0]->format('c'));
        $this->assertSame($date2->format('c'), $range[1]->format('c'));
    }
}
