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

    public function testCsrfTokenGeneratesToken(): void
    {
        $token = $this->sess->csrfToken();
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testCsrfTokenReturnsSameToken(): void
    {
        $token1 = $this->sess->csrfToken();
        $token2 = $this->sess->csrfToken();
        $this->assertSame($token1, $token2);
    }

    public function testValidateCsrfTokenWithInvalidToken(): void
    {
        $this->sess->csrfToken();
        $this->assertFalse($this->sess->validateCsrfToken('invalid-token'));
    }

    public function testValidateCsrfTokenWithValidToken(): void
    {
        $token = $this->sess->csrfToken();
        $this->assertTrue($this->sess->validateCsrfToken($token));
    }

    public function testValidateCsrfTokenRotatesPool(): void
    {
        $token = $this->sess->csrfToken();
        $this->assertTrue($this->sess->validateCsrfToken($token));
        $new_token = $this->sess->csrfToken();
        $this->assertNotSame($token, $new_token);
    }

    public function testCsrfTokenPoolSizeTwo(): void
    {
        $token1 = $this->sess->csrfToken();
        // First validation rotates: pool now has 2 tokens
        $this->assertTrue($this->sess->validateCsrfToken($token1));
        // Old token is still valid because pool size is 2
        $this->assertTrue($this->sess->validateCsrfToken($token1));
    }

    public function testCsrfTokenPoolRotationEvictsOldToken(): void
    {
        $token1 = $this->sess->csrfToken();
        $this->assertTrue($this->sess->validateCsrfToken($token1));
        $token2 = $this->sess->csrfToken();
        $this->assertTrue($this->sess->validateCsrfToken($token2));
        $token3 = $this->sess->csrfToken();
        // token1 should now be evicted from pool (size 2)
        $this->assertFalse($this->sess->validateCsrfToken($token1));
        // token2 and token3 should still be valid
        $this->assertTrue($this->sess->validateCsrfToken($token2));
        $this->assertTrue($this->sess->validateCsrfToken($token3));
    }
}
