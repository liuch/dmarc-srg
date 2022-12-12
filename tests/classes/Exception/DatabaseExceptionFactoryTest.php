<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\DatabaseExceptionFactory;

class DatabaseExceptionFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testWhenDatabaseAccessDenied(): void
    {
        $o = new \PDOException('', 1044);
        $e = DatabaseExceptionFactory::fromException($o);
        $this->checkException($e, 'Database access denied', $o);

        $o = new \PDOException('', 1045);
        $e = DatabaseExceptionFactory::fromException($o);
        $this->checkException($e, 'Database access denied', $o);
    }

    public function testWhenDatabaseConnectionError(): void
    {
        $o = new \PDOException('', 2002);
        $e = DatabaseExceptionFactory::fromException($o);
        $this->checkException($e, 'Database connection error', $o);

        $o = new \PDOException('', 2006);
        $e = DatabaseExceptionFactory::fromException($o);
        $this->checkException($e, 'Database connection error', $o);
    }

    public function testUnknownException(): void
    {
        $o = new \Exception('', 1044);
        $e = DatabaseExceptionFactory::fromException($o);
        $this->checkException($e, 'Database error', $o);

        $o = new \Exception('Some error');
        $e = DatabaseExceptionFactory::fromException($o);
        $this->checkException($e, 'Database error', $o);
    }

    private function checkException($e, $m, $o): void
    {
        $this->assertSame('Liuch\DmarcSrg\Exception\DatabaseException', get_class($e));
        $this->assertSame(-1, $e->getCode());
        $this->assertSame($m, $e->getMessage());
        $this->assertSame($o, $e->getPrevious());
    }
}
