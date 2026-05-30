<?php

namespace Liuch\DmarcSrg\ReportFile;

use Liuch\DmarcSrg\Exception\SoftException;

class ReportFileTest extends \PHPUnit\Framework\TestCase
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

    private static function createTempZip(string $content, string $entryName = 'report.xml'): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dmarc_test_');
        $zip = new \ZipArchive();
        if ($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create temporary ZIP file');
        }
        $zip->addFromString($entryName, $content);
        $zip->close();
        return $tmpPath;
    }

    private static function createTempGz(string $content): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dmarc_test_');
        $gz = gzopen($tmpPath, 'w');
        gzwrite($gz, $content);
        gzclose($gz);
        return $tmpPath;
    }

    public function testDatastreamZipUnderLimit(): void
    {
        $xml = self::minimalValidXml();
        $zipPath = self::createTempZip($xml);
        $reportFile = ReportFile::fromFile($zipPath, 'report.zip', true);

        $fd = $reportFile->datastream(strlen($xml));
        $this->assertIsResource($fd);

        unset($reportFile);
    }

    public function testDatastreamZipExceedsLimit(): void
    {
        $xml = self::minimalValidXml();
        $zipPath = self::createTempZip($xml);
        $reportFile = ReportFile::fromFile($zipPath, 'report.zip', true);

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Uncompressed ZIP entry exceeds size limit');

        $reportFile->datastream(strlen($xml) - 1);
    }

    public function testDatastreamZipNoLimit(): void
    {
        $xml = self::minimalValidXml();
        $zipPath = self::createTempZip($xml);
        $reportFile = ReportFile::fromFile($zipPath, 'report.zip', true);

        $fd = $reportFile->datastream(null);
        $this->assertIsResource($fd);

        unset($reportFile);
    }

    public function testDatastreamGzipUnderLimit(): void
    {
        $xml = self::minimalValidXml();
        $gzPath = self::createTempGz($xml);
        $reportFile = ReportFile::fromFile($gzPath, 'report.xml.gz', true);

        $fd = $reportFile->datastream(strlen($xml));
        $this->assertIsResource($fd);

        unset($reportFile);
    }
}
