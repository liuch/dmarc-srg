<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Exception\SoftException;

class CoreTest extends \PHPUnit\Framework\TestCase
{
    /** @var Core */
    private $core = null;

    public function setUp(): void
    {
        $this->core = Core::instance();
    }

    public function testSelfInstance(): void
    {
        $this->assertSame($this->core, Core::instance());
    }

    /**
     * @runInSeparateProcess
     */
    public function testRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'some_method';
        $this->assertSame('some_method', $this->core->method());
    }

    public function testAdminUser(): void
    {
        $this->core->user('admin');
        $this->assertInstanceOf(AdminUser::class, $this->core->user());

        $this->core->user(new AdminUser($this->core));
        $this->assertInstanceOf(AdminUser::class, $this->core->user());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendHtml(): void
    {
        foreach ([
            [ '', false, 'Empty custom CSS' ],
            [ 'custom.css', true, 'Correct custom CSS' ],
            [ 'custom.csss', false, 'Incorrect custom CSS' ]
        ] as $it) {
            $core = new Core([
                'config' => $this->getConfig('custom_css', '', $it[0]),
                'template' => realpath(__DIR__ . '/../..') . '/template.html'
            ]);
            ob_start();
            $core->sendHtml();
            $output = ob_get_contents();
            ob_end_clean();
            $this->assertIsString($output);
            $this->assertStringNotContainsString('<!-- Custom CSS -->', $output, $it[2]);
            if ($it[1]) {
                $this->assertStringContainsString($it[0], $output, $it[2]);
            } elseif (!empty($it[0])) {
                $this->assertStringNotContainsString($it[0], $output, $it[2]);
            }
        }
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

    public function testCheckingDependencies(): void
    {
        $this->core->auth();
        if (extension_loaded('zip')) {
            $this->core->checkDependencies('zip');
        }
        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Required extension is missing: FAKE_EXTENSION.');
        $this->core->checkDependencies('fake_extension');
    }

    public function testConfigExistingParameters(): void
    {
        $core = new Core([ 'config' => $this->getConfig('some/param', 'default', 'some_value') ]);
        $this->assertSame('some_value', $core->config('some/param', 'default'));
    }

    public function testConfigNonexistentParameters(): void
    {
        $core   = new Core([ 'config' => [ 'Liuch\DmarcSrg\Config', [ 'tests/conf_test_file.php' ] ] ]);
        $config = new Config('tests/conf_test_file.php');
        $this->assertNull($core->config('some_unknown_parameter'));
        $this->assertIsString($core->config('some_unknown_parameter', ''));
    }

    private function getConfig(string $param, $defval, $value)
    {
        $config = $this->getMockBuilder(Config::class)
                       ->disableOriginalConstructor()
                       ->setMethods([ 'get' ])
                       ->getMock();
        $config->expects($this->once())
               ->method('get')
               ->with($this->equalTo($param), $this->equalTo($defval))
               ->willReturn($value);
        return $config;
    }
}
