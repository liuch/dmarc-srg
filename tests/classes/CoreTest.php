<?php

namespace Liuch\DmarcSrg;

class CoreTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $this->core = Core::instance();
    }

    public function testSelfInstance(): void
    {
        $this->assertSame($this->core, Core::instance());
    }

    public function testRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'some_method';
        $this->assertSame('some_method', $this->core->method());
    }

    public function testSendHtml(): void
    {
        ob_start();
        $this->core->sendHtml();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertIsString($output);
        $this->assertStringEqualsFile('index.html', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendJson(): void
    {
        $data = [ 'key1' => 'value1', [ 'key2' => 'value2' ] ];
        ob_start();
        $this->core->sendJson($data);
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertJsonStringEqualsJsonString(json_encode($data), $output);
    }

    public function testAuthInstance(): void
    {
        $this->assertSame($this->core->auth(), $this->core->auth());
    }

    public function testStatusInstance(): void
    {
        $this->assertSame($this->core->status(), $this->core->status());
    }

    public function testAdminIntance(): void
    {
        $this->assertSame($this->core->admin(), $this->core->admin());
    }

    public function testErrorHandlerInstance(): void
    {
        $this->assertSame($this->core->errorHandler(), $this->core->errorHandler());
    }

    public function testLoggerInstance(): void
    {
        $this->assertSame($this->core->logger(), $this->core->logger());
    }

    public function testConfigExistingParameters(): void
    {
        $config = $this->getMockBuilder(Config::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ 'get' ])
                       ->getMock();
        $config->expects($this->once())
               ->method('get')
               ->with($this->equalTo('some/param'), $this->equalTo('default'))
               ->willReturn('some_value');
        $core = new Core([ 'config' => $config ]);
        $this->assertSame('some_value', $core->config('some/param', 'default'));
    }

    public function testConfigNonexistentParameters(): void
    {
        $core   = new Core([ 'config' => [ 'Liuch\DmarcSrg\Config', [ 'tests/conf_test_file.php' ] ] ]);
        $config = new Config('tests/conf_test_file.php');
        $this->assertNull($core->config('some_unknown_parameter'));
        $this->assertIsString($core->config('some_unknown_parameter', ''));
    }
}
