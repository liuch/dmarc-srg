<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\UserList;
use Liuch\DmarcSrg\Domains\Domain;
use Liuch\DmarcSrg\Domains\DomainList;

class DomainListTest extends \PHPUnit\Framework\TestCase
{
    public function testGettingList(): void
    {
        $data = [
            [ 'id' => 1, 'fqdn' => 'example.org' ],
            [ 'id' => 2, 'fqdn' => 'example.net' ],
            [ 'id' => 3, 'fqdn' => 'example.com' ]
        ];
        $user   = UserList::getUserByName('admin');
        $result = (new DomainList($user, $this->getDbMapperOnce('list', $data)))->getList();
        $this->assertSame(false, $result['more']);
        $list = $result['domains'];
        $this->assertSame(count($data), count($list));
        $this->assertContainsOnlyInstancesOf(Domain::class, $list);
        foreach ($list as $key => $dom) {
            $di = $data[$key];
            $this->assertSame($di['id'], $dom->id());
            $this->assertSame($di['fqdn'], $dom->fqdn());
        }
    }

    public function testGettingNames(): void
    {
        $user  = UserList::getUserByName('admin');
        $names = [ 'example.org', 'example.net', 'example.com' ];
        $this->assertSame($names, (new DomainList($user, $this->getDbMapperOnce('names', $names)))->names());
    }

    private function getDbMapperOnce(string $method, $value): object
    {
        $mapper = $this->getMockBuilder(Database\DomainMapperInterface::class)
                       ->disableOriginalConstructor()
                       ->getMock();
        $mapper->expects($this->once())
               ->method($method)
               ->willReturnCallback(function () use ($value) {
                return $value;
               });

        $db = $this->getMockBuilder(Database\DatabaseConnector::class)
                   ->disableOriginalConstructor()
                   ->getMock();
        $db->method('getMapper')
           ->with('domain')
           ->willReturn($mapper);
        return $db;
    }
}
