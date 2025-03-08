<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\DateTime;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Database\DatabaseController;
use Liuch\DmarcSrg\Database\DomainMapperInterface;

class DomainTest extends \PHPUnit\Framework\TestCase
{
    public function testConstructorWrongArgumentType(): void
    {
        $this->expectException(LogicException::class);
        new Domain(true, $this->getDbMapperNever());
    }

    public function testConstructorEmptyFqdn(): void
    {
        $this->expectException(SoftException::class);
        new Domain('', $this->getDbMapperNever());
    }

    public function testConstructorFqdnIsDot(): void
    {
        $this->expectException(SoftException::class);
        new Domain('.', $this->getDbMapperNever());
    }

    public function testConstructorFqdnEndWithDot(): void
    {
        $this->assertSame('example.org', (new Domain('example.org.', $this->getDbMapperNever()))->fqdn());
        $this->assertSame('example.org', (new Domain([ 'fqdn' => 'example.org.' ], $this->getDbMapperNever()))->fqdn());
    }

    public function testConstructorIncorrectArrayData(): void
    {
        $data = [
            'active' => true,
            'description' => 'Descr',
            'created_time' => new DateTime(),
            'updated_time' => new DateTime()
        ];
        $this->expectException(LogicException::class);
        new Domain($data, $this->getDbMapperNever());
    }

    public function testExists(): void
    {
        $this->assertSame(false, (new Domain(1, $this->getDbMapperOnce('exists', '', false)))->exists());
        $this->assertSame(true, (new Domain(1, $this->getDbMapperOnce('exists', '', true)))->exists());
    }

    public function testEnsureExist(): void
    {
        (new Domain(1, $this->getDbMapperOnce('exists', '', true)))->ensure('exist');
        $this->expectException(SoftException::class);
        (new Domain(1, $this->getDbMapperOnce('exists', '', false)))->ensure('exist');
    }

    public function testEnsureNonexist(): void
    {
        (new Domain(1, $this->getDbMapperOnce('exists', '', false)))->ensure('nonexist');
        $this->expectException(SoftException::class);
        (new Domain(1, $this->getDbMapperOnce('exists', '', true)))->ensure('nonexist');
    }

    public function testId(): void
    {
        $this->assertSame(1, (new Domain(1, $this->getDbMapperNever()))->id());
        $this->assertSame(1, (new Domain([ 'id' => 1 ], $this->getDbMapperNever()))->id());
        $this->assertSame(1, (new Domain([ 'fqdn' => 'example.org' ], $this->getDbMapperOnce('fetch', 'id', 1)))->id());
    }

    public function testFqdn(): void
    {
        $this->assertSame(
            'example.org',
            (new Domain('example.org', $this->getDbMapperNever()))->fqdn()
        );
        $this->assertSame(
            'example.org',
            (new Domain([ 'fqdn' => 'example.org' ], $this->getDbMapperNever()))->fqdn()
        );
        $this->assertSame(
            'example.org',
            (new Domain(1, $this->getDbMapperOnce('fetch', 'fqdn', 'example.org')))->fqdn()
        );
    }

    public function testActive(): void
    {
        $this->assertSame(true, (new Domain([ 'id' => 1, 'active' => true ], $this->getDbMapperNever()))->active());
        $this->assertSame(true, (new Domain(1, $this->getDbMapperOnce('fetch', 'active', true)))->active());
    }

    public function testDescription(): void
    {
        $this->assertSame(
            'Descr',
            (new Domain(
                [ 'id'=> 1, 'fqdn' => 'example.org', 'description' => 'Descr' ],
                $this->getDbMapperNever()
            ))->description()
        );
        $this->assertSame(
            'Descr',
            (new Domain(1, $this->getDbMapperOnce('fetch', 'description', 'Descr')))->description()
        );
    }

    public function testToArray(): void
    {
        $data_in = [
            'id' => 1,
            'fqdn' => 'example.org',
            'active' => true,
            'description' => 'Descr',
            'created_time' => new DateTime(),
            'updated_time' => new DateTime()
        ];
        $data_out = $data_in;
        unset($data_out['id']);
        $this->assertSame($data_out, (new Domain($data_in, $this->getDbMapperNever()))->toArray());
        (new Domain([ 'id' => 1 ], $this->getDbMapperOnce('fetch', '', null)))->toArray();
        (new Domain([ 'fqdn' => 'example.org' ], $this->getDbMapperOnce('fetch', '', null)))->toArray();
    }

    public function testSave(): void
    {
        (new Domain(1, $this->getDbMapperOnce('save', '', null)))->save();
    }

    public function testDelete(): void
    {
        (new Domain(1, $this->getDbMapperOnce('delete', '', null)))->delete(false);
    }

    private function getDbMapperNever(): object
    {
        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->onlyMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->never())
           ->method('getMapper');
        return $db;
    }

    private function getDbMapperOnce(string $method, string $key, $value): object
    {
        $mapper = $this->getMockBuilder(DomainMapperInterface::class)
                       ->disableOriginalConstructor()
                       ->getMock();
        $mr = $mapper->expects($this->once())->method($method);
        if (empty($key)) {
            if (!is_null($value)) {
                $mr->willReturn($value);
            }
        } else {
            $mr->willReturnCallback(function (&$data) use ($key, $value) {
                $data[$key] = $value;
            });
        }

        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->onlyMethods([ 'getMapper' ])
                   ->getMock();
        $db->method('getMapper')
           ->with('domain')
           ->willReturn($mapper);
        return $db;
    }
}
