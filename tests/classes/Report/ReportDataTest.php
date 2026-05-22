<?php

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Exception\SoftException;

class ReportDataTest extends \PHPUnit\Framework\TestCase
{
    private static function minimalValidXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" ?>'
            . '<feedback>'
            . '<report_metadata>'
            . '<org_name>Example Org</org_name>'
            . '<email>postmaster@example.com</email>'
            . '<report_id>12345</report_id>'
            . '<date_range><begin>1609459200</begin><end>1609545600</end></date_range>'
            . '</report_metadata>'
            . '<policy_published>'
            . '<domain>example.com</domain>'
            . '<adkim>r</adkim>'
            . '<aspf>r</aspf>'
            . '<p>none</p>'
            . '<sp>none</sp>'
            . '<pct>100</pct>'
            . '</policy_published>'
            . '<record>'
            . '<row>'
            . '<source_ip>192.0.2.1</source_ip>'
            . '<count>1</count>'
            . '<policy_evaluated>'
            . '<disposition>none</disposition>'
            . '<dkim>pass</dkim>'
            . '<spf>pass</spf>'
            . '</policy_evaluated>'
            . '</row>'
            . '<identifiers>'
            . '<header_from>example.com</header_from>'
            . '</identifiers>'
            . '<auth_results>'
            . '<dkim><domain>example.com</domain><result>pass</result></dkim>'
            . '<spf><domain>example.com</domain><result>pass</result></spf>'
            . '</auth_results>'
            . '</record>'
            . '</feedback>';
    }

    public function testFromXmlFileValid(): void
    {
        $xml = self::minimalValidXml();
        $fd = fopen('php://memory', 'r+');
        fwrite($fd, $xml);
        rewind($fd);

        $data = ReportData::fromXmlFile($fd);
        fclose($fd);

        $this->assertTrue($data->isValid());
    }

    public function testFromXmlFileUnderLimit(): void
    {
        $xml = self::minimalValidXml();
        $fd = fopen('php://memory', 'r+');
        fwrite($fd, $xml);
        rewind($fd);

        $data = ReportData::fromXmlFile($fd, false, 1024 * 1024);
        fclose($fd);

        $this->assertTrue($data->isValid());
    }

    public function testFromXmlFileExceedsLimit(): void
    {
        $xml = self::minimalValidXml();
        $fd = fopen('php://memory', 'r+');
        fwrite($fd, $xml);
        rewind($fd);

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Report file is too large after decompression');

        ReportData::fromXmlFile($fd, false, strlen($xml) - 1);
    }

    public function testFromXmlFileNoLimit(): void
    {
        $xml = self::minimalValidXml();
        $fd = fopen('php://memory', 'r+');
        fwrite($fd, $xml);
        rewind($fd);

        $data = ReportData::fromXmlFile($fd, false, null);
        fclose($fd);

        $this->assertTrue($data->isValid());
    }
}
