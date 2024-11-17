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
        '*==========================*======*========*',
        '! t1                       ! ttl2 ! title3 !',
        '! 2. Long long long string ! ss1  !   1111 !',
        '! 1. Some string           ! ss2  !      9 !',
        't1                        ttl2  title3',
        '2. Long long long string  ss1     1111',
        '1. Some string            ss2        9',
        '|                       t1 | ttl2 | title3 |',
        '| 2. Long long long string |  ss1 | 1111   |',
        '|           1. Some string |  ss2 | 9      |',
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
        $this->assertEquals($this->genResultText(0, 1, 0, 2, 3, 0), $output);
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
        $this->assertEquals($this->genResultText(4, 5, 4, 6, 7, 4), $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testMimColumnsWidth(): void
    {
        ob_start();
        $this->table->setMinColumnsWidth([ 1, 10, 2 ])->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResultText(4, 5, 4, 6, 7, 4), $output);
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
        $this->assertEquals($this->genResultText(0, 1, 0, 3, 2, 0), $output);
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
        $this->assertEquals($this->genResultText(0, 1, 0, 3, 2, 0), $output);
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
        $this->assertEquals($this->genResultText(4, 5, 4, 7, 6, 4), $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetColumnAlignment(): void
    {
        ob_start();
        $this->table->setColumnAlignment(0, 'right')->setColumnAlignment(1, 'right')
            ->setColumnAlignment(2, 'left')->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResultText(0, 15, 0, 16, 17, 0), $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testToArray(): void
    {
        $this->assertEquals($this->genResultArray(0, 1, 0, 2, 3, 0), $this->table->toArray());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetBorders(): void
    {
        ob_start();
        $this->table->setBorders('=', '!', '*')->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResultText(8, 9, 8, 10, 11, 8), $output);
    }

    public function testSetEmptyBorders(): void
    {
        ob_start();
        $this->table->setBorders('', '', '')->output();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($this->genResultText(12, 13, 14), $output);
    }

    private function genResultArray(...$lines): array
    {
        $res = [];
        foreach ($lines as $ln) {
            $res[] = $this->rlines[$ln];
        }
        return $res;
    }

    private function genResultText(...$lines): string
    {
        return implode(PHP_EOL, $this->genResultArray(...$lines)) . PHP_EOL;
    }
}
