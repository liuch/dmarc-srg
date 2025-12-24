<?php

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Report\SummaryReport;

/**
 * Test markdown character escaping functionality
 */
class MarkdownEscapingTest extends \PHPUnit\Framework\TestCase
{
    private $mockDomain;

    public function setUp(): void
    {
        $this->mockDomain = $this->createMock(Domain::class);
        $this->mockDomain->method('fqdn')->willReturn('test.example.com');
        $this->mockDomain->method('active')->willReturn(true);
        $this->mockDomain->method('isAssigned')->willReturn(true);
    }

    public function testEscapeMarkdownMethodExists()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $this->assertTrue($reflection->hasMethod('escapeMarkdown'), 'escapeMarkdown method must exist');

        $method = $reflection->getMethod('escapeMarkdown');
        $this->assertTrue($method->isPrivate(), 'escapeMarkdown method must be private');
    }

    public function testMarkdownSpecialCharactersEscaping()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $method = $reflection->getMethod('escapeMarkdown');

        $report = new SummaryReport('lastweek');

        // Test characters that actually need escaping in table context
        $testCases = [
            // [input, expected_output]
            ['test|pipe', 'test\\|pipe'],        // Pipe breaks tables
            ['test\\backslash', 'test\\\\backslash'], // Backslash for escaping
            ['normal_text', 'normal_text'],      // Normal text unchanged
            ['test_underscore', 'test_underscore'], // Underscores OK in table cells
            ['test*asterisk', 'test*asterisk'],  // Asterisks OK in table cells
            ['test#hash', 'test#hash'],          // Hash OK in table cells
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $method->invokeArgs($report, [$input]);
            $this->assertEquals($expected, $result, "Failed to escape: '$input'");
        }
    }

    public function testComplexStringEscaping()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $method = $reflection->getMethod('escapeMarkdown');

        $report = new SummaryReport('lastweek');

        // Test complex strings focusing on critical table-breaking characters
        $complexString = 'Org|Name\\With|Pipes';
        $expected = 'Org\\|Name\\\\With\\|Pipes';

        $result = $method->invokeArgs($report, [$complexString]);
        $this->assertEquals($expected, $result, 'Complex string escaping failed');
    }

    public function testEmptyAndNullStrings()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $method = $reflection->getMethod('escapeMarkdown');

        $report = new SummaryReport('lastweek');

        // Test empty string
        $result = $method->invokeArgs($report, ['']);
        $this->assertEquals('', $result, 'Empty string should remain empty');

        // Test spaces
        $result = $method->invokeArgs($report, ['   ']);
        $this->assertEquals('   ', $result, 'Spaces should be preserved');
    }

    public function testCommonOrganizationNames()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $method = $reflection->getMethod('escapeMarkdown');

        $report = new SummaryReport('lastweek');

        // Test realistic organization names focusing on table-breaking characters
        $orgNames = [
            'Org|With|Pipes' => 'Org\\|With\\|Pipes',
            'Company\\Path' => 'Company\\\\Path',
            'Normal Company' => 'Normal Company',
            'Company_Name' => 'Company_Name', // Underscores OK in tables
            'Google*Analytics' => 'Google*Analytics', // Asterisks OK in tables
        ];

        foreach ($orgNames as $input => $expected) {
            $result = $method->invokeArgs($report, [$input]);
            $this->assertEquals($expected, $result, "Failed to escape org name: '$input'");
        }
    }

    public function testPercentageStringsEscaping()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $method = $reflection->getMethod('escapeMarkdown');

        $report = new SummaryReport('lastweek');

        // Test percentage strings focusing on table-breaking characters
        $percentages = [
            '50%' => '50%',
            '100%|' => '100%\\|', // Pipe would break table
            '25%(5+2)' => '25%(5+2)', // Parentheses are OK
            '0' => '0',
        ];

        foreach ($percentages as $input => $expected) {
            $result = $method->invokeArgs($report, [$input]);
            $this->assertEquals($expected, $result, "Failed to escape percentage: '$input'");
        }
    }

    public function testIPAddressEscaping()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $method = $reflection->getMethod('escapeMarkdown');

        $report = new SummaryReport('lastweek');

        // Test IP addresses (should normally not contain table-breaking chars)
        $ipAddresses = [
            '192.168.1.1' => '192.168.1.1',
            '2001:db8::1' => '2001:db8::1',
            'special|ip' => 'special\\|ip', // Edge case with pipe
        ];

        foreach ($ipAddresses as $input => $expected) {
            $result = $method->invokeArgs($report, [$input]);
            $this->assertEquals($expected, $result, "Failed to escape IP: '$input'");
        }
    }

    public function testBackslashEscapingOrder()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $method = $reflection->getMethod('escapeMarkdown');

        $report = new SummaryReport('lastweek');

        // Test that backslashes are escaped first (important for order)
        $input = 'test\\|pipe|';
        $expected = 'test\\\\\\|pipe\\|'; // \\ then \|

        $result = $method->invokeArgs($report, [$input]);
        $this->assertEquals($expected, $result, 'Backslash escaping order is critical');
    }

    public function testUnicodeCharacters()
    {
        $reflection = new \ReflectionClass(SummaryReport::class);
        $method = $reflection->getMethod('escapeMarkdown');

        $report = new SummaryReport('lastweek');

        // Test unicode characters mixed with table-breaking chars
        $input = 'Café|München|Zürich';
        $expected = 'Café\\|München\\|Zürich';

        $result = $method->invokeArgs($report, [$input]);
        $this->assertEquals($expected, $result, 'Unicode characters should be preserved while special chars are escaped');
    }
}
