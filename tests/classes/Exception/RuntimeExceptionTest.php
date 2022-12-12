<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\RuntimeException;

class RuntimeExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testExceptionCode(): void
    {
        $this->assertSame(-1, (new RuntimeException())->getCode());
        $this->assertSame(0, (new RuntimeException('', 0))->getCode());
        $this->assertSame(-1, (new RuntimeException('', -1))->getCode());
        $this->assertSame(77, (new RuntimeException('', 77))->getCode());
    }
}
