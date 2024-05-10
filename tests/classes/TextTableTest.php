<?php

namespace Liuch\DmarcSrg;

class TextTableTest extends \PHPUnit\Framework\TestCase
{
    private $table  = null;
    private $rlines = [
        '+--------------------------+------+--------+',
        '| t1                       | ttl2 | title3 |',
        '| 2. Long long long string | ss1  |   1111 |',
        '| 1. Some string           | ss2  |      9 |',
        '+--------------------------+------------+--------+',
        '| t1                       | ttl2       | title3 |',
        '| 2. Long long long string | ss1        |   1111 |',
        '| 1. Some string           | ss2        |      9 |',
    ];

    public function setUp(): void
    {
        $this->table = new TextTable([ 't1', 'ttl2', 'title3' ]);
        $this->table->appendRow([ '2. Long long long string', 'ss1', 1111 ]);
        $this->table->appendRow([ '1. Some string', 'ss2', 9 ]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSimple(): void
    {
        ob_start();
        $this->table->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResult(0, 1, 0, 2, 3, 0), $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testMinColumnWidth(): void
    {
        ob_start();
        $this->table->setMinColumnWidth(1, 10)->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResult(4, 5, 4, 6, 7, 4), $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSortByString(): void
    {
        ob_start();
        $this->table->sortBy(0)->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResult(0, 1, 0, 3, 2, 0), $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSortByInt(): void
    {
        ob_start();
        $this->table->sortBy(2)->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResult(0, 1, 0, 3, 2, 0), $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testMinColumnWidthAndSortBy(): void
    {
        ob_start();
        $this->table->setMinColumnWidth(1, 10)->sortBy(2)->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResult(4, 5, 4, 7, 6, 4), $output);
    }

    private function genResult(...$lines): string
    {
        $res = '';
        foreach ($lines as $ln) {
            $res .= $this->rlines[$ln] . PHP_EOL;
        }
        return $res;
    }
}
