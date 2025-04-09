<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Exception\LogicException;

class SessionTest extends \PHPUnit\Framework\TestCase
{
    private $sess  = null;

    public function setUp(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        \session_start();
        \session_unset();
        \session_destroy();
        \session_write_close();
        $this->sess = new Session();
    }

    public function tearDown(): void
    {
        $this->sess->destroy();
    }

    public function testGetDataWhenSessionDoesNotExist(): void
    {
        $this->assertSame([], $this->sess->getData());
    }

    public function testSetAndGetDataWhenSessionDoesNotExist(): void
    {
        $this->sess->setData([ 'user' => 1 ]);
        $this->assertSame([ 'user' => 1 ], $this->sess->getData());
    }

    public function testSetAndGetDataWhenSessionExistsButNotStarted(): void
    {
        $this->sess->setData([]);
        $this->sess->commit();
        $this->sess->setData([ 'user' => 1 ]);
        $this->assertSame([ 'user' => 1 ], $this->sess->getData());
    }

    public function testSetAndGetDataWhenSessionStarted(): void
    {
        $this->sess->getData();
        $this->sess->setData([ 'user' => 1 ]);
        $this->assertSame([ 'user' => 1 ], $this->sess->getData());
    }

    public function testCommitWhenSessionIsNotStarted(): void
    {
        $this->sess->setData([ 'user' => 1 ]);
        $this->sess->commit();
        $this->sess->commit();
        $this->assertSame([ 'user' => 1 ], $this->sess->getData());
    }

    public function testCommitWhenSessionIsStarted(): void
    {
        $this->sess->setData([ 'user' => 1 ]);
        $this->sess->commit();
        $this->assertSame([ 'user' => 1 ], $this->sess->getData());
    }

    public function testCloseWhenSessionIsNotStarted(): void
    {
        $this->sess->setData([ 'user' => 1 ]);
        $this->sess->commit();
        $this->sess->close();
        $this->assertSame([ 'user' => 1 ], $this->sess->getData());
    }

    public function testCloseWhenSessionIsStarted(): void
    {
        $this->sess->setData([ 'user' => 1 ]);
        $this->sess->commit();
        $this->sess->setData([ 'user' => 2 ]);
        $this->sess->close();
        $this->assertSame([ 'user' => 1 ], $this->sess->getData());
    }

    public function testDestroyWhenSessionIsNotStarted(): void
    {
        $this->sess->setData([ 'user' => 1 ]);
        $id = \session_id();
        $this->sess->commit();
        $this->sess->destroy();
        $this->assertSame([], $this->sess->getData());
        $this->assertNotSame($id, \session_id());
    }

    public function testDestroyWhenSessionIsStarted(): void
    {
        $this->sess->setData([ 'user' => 1 ]);
        $id = \session_id();
        $this->sess->commit();
        $this->sess->getData();
        $this->sess->destroy();
        $this->assertSame([], $this->sess->getData());
        $this->assertNotSame($id, \session_id());
    }

    public function testStrictModeActivated(): void
    {
        $fake_id = 'myfakesessionid00000000000000000';
        \session_id($fake_id);
        $this->sess->getData();
        $this->assertNotSame($fake_id, \session_id());
    }
}
