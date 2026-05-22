<?php

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Exception\RuntimeException;

class ReportDataTest extends \PHPUnit\Framework\TestCase
{
    private function generateXml(int $record_count)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" ?>';
        $xml .= '<feedback>';
        $xml .= '<version>1.0</version>';
        $xml .= '<report_metadata>';
        $xml .= '<org_name>example.com</org_name>';
        $xml .= '<email>r@example.com</email>';
        $xml .= '<report_id>123</report_id>';
        $xml .= '<date_range><begin>1609459200</begin><end>1609545600</end></date_range>';
        $xml .= '</report_metadata>';
        $xml .= '<policy_published>';
        $xml .= '<domain>example.com</domain>';
        $xml .= '<p>none</p>';
        $xml .= '</policy_published>';
        for ($i = 0; $i < $record_count; ++$i) {
            $xml .= '<record>';
            $xml .= '<row><source_ip>192.0.2.1</source_ip><count>1</count></row>';
            $xml .= '<identifiers><header_from>example.com</header_from></identifiers>';
            $xml .= '<auth_results><dkim><domain>example.com</domain><result>pass</result></dkim></auth_results>';
            $xml .= '</record>';
        }
        $xml .= '</feedback>';

        $fd = fopen('php://memory', 'r+');
        fwrite($fd, $xml);
        rewind($fd);
        return $fd;
    }

    public function testFromXmlFileWithRecordsBelowLimit(): void
    {
        $fd = $this->generateXml(2);
        $data = ReportData::fromXmlFile($fd, false, 5);
        $this->assertCount(2, $data->toArray()['records']);
        fclose($fd);
    }

    public function testFromXmlFileWithRecordsAtLimit(): void
    {
        $fd = $this->generateXml(3);
        $data = ReportData::fromXmlFile($fd, false, 3);
        $this->assertCount(3, $data->toArray()['records']);
        fclose($fd);
    }

    public function testFromXmlFileWithRecordsAboveLimit(): void
    {
        $fd = $this->generateXml(3);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many records');
        ReportData::fromXmlFile($fd, false, 2);
        fclose($fd);
    }

    public function testFromXmlFileWithLimitZeroIsUnlimited(): void
    {
        $fd = $this->generateXml(5);
        $data = ReportData::fromXmlFile($fd, false, 0);
        $this->assertCount(5, $data->toArray()['records']);
        fclose($fd);
    }
}
