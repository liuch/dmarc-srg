<?php

namespace Liuch\DmarcSrg;

class CommonTest extends \PHPUnit\Framework\TestCase
{
    public function testAlignResultArray(): void
    {
        $this->assertCount(3, Common::$align_res);
        $this->assertEquals('fail', Common::$align_res[0]);
        $this->assertEquals('unknown', Common::$align_res[1]);
        $this->assertEquals('pass', Common::$align_res[2]);
    }

    public function testDispositionArray(): void
    {
        $this->assertCount(3, Common::$disposition);
        $this->assertEquals('reject', Common::$disposition[0]);
        $this->assertEquals('quarantine', Common::$disposition[1]);
        $this->assertEquals('none', Common::$disposition[2]);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testCsvConverting(): void
    {
        $this->assertEquals(
            'qwe' . "\n\r" . '' . "\n\r" . '1,qwe,"q w e","""q""w"""' . "\n\r",
            Common::arrayToCSV([
                'qwe',
                '',
                [1, 'qwe', 'q w e', '"q"w"'],
            ])
        );
    }

    public function testRandomString(): void
    {
        $this->assertEquals(10, strlen(Common::randomString(10)));
        $this->assertNotEquals(Common::randomString(4), Common::randomString(4));
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', Common::randomString(64));
    }
}
