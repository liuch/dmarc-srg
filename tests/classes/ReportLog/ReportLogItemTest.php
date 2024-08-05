<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Report\Report;
use Liuch\DmarcSrg\Sources\Source;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;
use Liuch\DmarcSrg\ReportLog\ReportLogItem;
use Liuch\DmarcSrg\Database\ReportLogMapperInterface;

class ReportLogItemTest extends \PHPUnit\Framework\TestCase
{
    public function testForSuccess(): void
    {
        $db = $this->getDbMapperNever();
        $rli = ReportLogItem::success(
            Source::SOURCE_MAILBOX,
            new Report([ 'domain' => 'example.org', 'report_id' => 'rrggoo' ], $db),
            'filename.gz',
            'Success!',
            $db
        );
        $this->assertTrue($rli->toArray()['success']);
    }

    public function testForFailed(): void
    {
        $rli = ReportLogItem::failed(
            Source::SOURCE_UPLOADED_FILE,
            null,
            null,
            'Failed!',
            $this->getDbMapperNever()
        );
        $this->assertFalse($rli->toArray()['success']);
    }

    public function testGettingById(): void
    {
        $callback = function (&$data) {
            $data['source'] = Source::SOURCE_MAILBOX;
            $this->assertSame(55, $data['id']);
        };
        ReportLogItem::byId(55, $this->getDbMapperOnce('fetch', $callback));
    }

    public function testGettingByIdNotFound(): void
    {
        $callback = function (&$data) {
            throw new DatabaseNotFoundException();
        };
        $this->expectException(SoftException::class);
        ReportLogItem::byId(55, $this->getDbMapperOnce('fetch', $callback));
    }

    public function testSourceToString(): void
    {
        $this->assertSame('uploaded_file', ReportLogItem::sourceToString(Source::SOURCE_UPLOADED_FILE));
        $this->assertSame('email', ReportLogItem::sourceToString(Source::SOURCE_MAILBOX));
        $this->assertSame('directory', ReportLogItem::sourceToString(Source::SOURCE_DIRECTORY));
        $this->assertSame('', ReportLogItem::sourceToString(-111));
    }

    public function testToArray(): void
    {
        $sdata = [
            'id'          => 66,
            'domain'      => 'example.org',
            'report_id'   => 'gg44dd',
            'event_time'  => new \DateTime(),
            'filename'    => 'filename.zip',
            'source'      => Source::SOURCE_DIRECTORY,
            'success'     => true,
            'message'     => 'Message!'
        ];
        $callback = function (&$data) use ($sdata) {
            foreach ($sdata as $key => $value) {
                $data[$key] = $value;
            }
        };
        $sdata['source'] = ReportLogItem::sourceToString($sdata['source']);
        $this->assertSame($sdata, ReportLogItem::byId(66, $this->getDbMapperOnce('fetch', $callback))->toArray());
    }

    public function testSaving(): void
    {
        $callback1 = function ($data) {
            $this->assertSame(
                [
                    'id'          => null,
                    'domain'      => 'example.org',
                    'report_id'   => 'xxvvbb',
                    'event_time'  => null,
                    'filename'    => 'filename.xml',
                    'source'      => Source::SOURCE_MAILBOX,
                    'success'     => true,
                    'message'     => 'Success!'
                ],
                $data
            );
        };
        $callback2 = function ($data) {
            $this->assertSame(
                [
                    'id'          => null,
                    'domain'      => null,
                    'report_id'   => null,
                    'event_time'  => null,
                    'filename'    => null,
                    'source'      => Source::SOURCE_UPLOADED_FILE,
                    'success'     => false,
                    'message'     => 'Failed!'
                ],
                $data
            );
        };

        $rli = ReportLogItem::success(
            Source::SOURCE_MAILBOX,
            new Report([ 'domain' => 'example.org', 'report_id' => 'xxvvbb' ], $this->getDbMapperNever()),
            'filename.xml',
            'Success!',
            $this->getDbMapperOnce('save', $callback1)
        );
        $rli->save();

        $rli = ReportLogItem::failed(
            Source::SOURCE_UPLOADED_FILE,
            null,
            null,
            'Failed!',
            $this->getDbMapperOnce('save', $callback2)
        );
        $rli->save();
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

    private function getDbMapperNever(): object
    {
        $db = $this->getMockBuilder(\StdClass::class)
                   ->setMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->never())
           ->method('getMapper');
        return $db;
    }
}
