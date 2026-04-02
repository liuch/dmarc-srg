<?php

namespace Liuch\DmarcSrg\Requests;

class HttpRequestTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        unset($_GET);
        unset($_POST);
        unset($_SERVER);
    }

    public function testGetPath(): void
    {
        $_SERVER = [ 'REQUEST_URI' => '/somepath' ];
        $req = new HttpRequest();
        $this->assertSame('/somepath', $req->getPath());
    }

    public function testGetMethod(): void
    {
        $_SERVER = [ 'REQUEST_METHOD' => 'SOME_METHOD' ];
        $req = new HttpRequest();
        $this->assertSame('SOME_METHOD', $req->getMethod());
    }

    public function testHasProperty(): void
    {
        $_SERVER = [ 'REQUEST_METHOD' => 'GET' ];
        $_GET    = [ 'key1' => 'value1', 'key2' => 'value2' ];
        $req     = new HttpRequest();
        $this->assertTrue($req->hasProperty('key1'));
        $this->assertTrue($req->hasProperty('key2'));

        $_SERVER = [ 'REQUEST_METHOD' => 'POST' ];
        $_POST   = [ 'key1' => 'value1', 'key2' => 'value2' ];
        $req     = new HttpRequest();
        $this->assertTrue($req->hasProperty('key1'));
        $this->assertTrue($req->hasProperty('key2'));
    }

    public function testEmptyProperty(): void
    {
        $_SERVER = [ 'REQUEST_METHOD' => 'GET' ];
        $_GET    = [ 'key1' => '' ];
        $req     = new HttpRequest();
        $this->assertTrue($req->emptyProperty('key1'));
        $this->assertTrue($req->emptyProperty('key9'));
    }

    public function testGetProperty(): void
    {
        $_SERVER = [ 'REQUEST_METHOD' => 'GET' ];
        $_GET    = [ 'key1' => 'value1', 'key2' => 'value2' ];
        $req     = new HttpRequest();
        $this->assertSame('value1', $req->getProperty('key1'));
        $this->assertSame('value2', $req->getProperty('key2'));

        $_SERVER = [ 'REQUEST_METHOD' => 'POST' ];
        $_POST   = [ 'key1' => 'value1', 'key2' => 'value2' ];
        $req     = new HttpRequest();
        $this->assertSame('value1', $req->getProperty('key1'));
        $this->assertSame('value2', $req->getProperty('key2'));
    }

    public function testSetAndGetProperty(): void
    {
        $req = new HttpRequest();
        $req->setProperty('key1', 'value1');
        $this->assertSame('value1', $req->getProperty('key1'));
    }

    public function testSetAndGetData(): void
    {
        $data = [ 'key1' => 'value1', 'key2' => 'value2' ];
        $req = new HttpRequest();
        $req->setData($data);
        $this->assertSame($data, $req->getData());
    }

    public function testHasJsonData(): void
    {
        $_SERVER = [ 'CONTENT_TYPE' => 'non-json-type' ];
        $req = new HttpRequest();
        $this->assertFalse($req->hasJsonData());

        $_SERVER = [ 'CONTENT_TYPE' => 'application/json' ];
        $req = new HttpRequest();
        $this->assertTrue($req->hasJsonData());

        $_SERVER = [ 'HTTP_CONTENT_TYPE' => 'application/json' ];
        $req = new HttpRequest();
        $this->assertTrue($req->hasJsonData());
    }

    public function testSetAndGetErrorCode(): void
    {
        $req = new HttpRequest();
        $req->setErrorCode(0);
        $this->assertSame(0, $req->getErrorCode());

        $req = new HttpRequest();
        $req->setErrorCode(99);
        $this->assertSame(99, $req->getErrorCode());

        $req = new HttpRequest();
        $req->setErrorCode(-99);
        $this->assertSame(-99, $req->getErrorCode());
    }

    public function testSetAndGetMessage(): void
    {
        $req = new HttpRequest();
        $req->setMessage('');
        $this->assertSame('', $req->getMessage());

        $req = new HttpRequest();
        $req->setMessage('some message');
        $this->assertSame('some message', $req->getMessage());
    }
}
