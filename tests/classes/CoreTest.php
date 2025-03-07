<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Users\DbUser;
use Liuch\DmarcSrg\Users\AdminUser;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\ForbiddenException;
use Liuch\DmarcSrg\Exception\DatabaseNotFoundException;
use Liuch\DmarcSrg\Database\DatabaseController;
use Liuch\DmarcSrg\Database\UserMapperInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class CoreTest extends \PHPUnit\Framework\TestCase
{
    /** @var Core */
    private $core = null;

    public function setUp(): void
    {
        $this->core = Core::instance();
        $_SERVER['REQUEST_URI'] = '/';
    }

    public function testSelfInstance(): void
    {
        $this->assertSame($this->core, Core::instance());
    }

    public function testRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'some_method';
        $this->assertSame('some_method', $this->core->requestMethod());
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testGetCurrentUserWhenAuthIsDisabled(): void
    {
        $auth = $this->getAuthDisabledMock();

        $sess = $this->getMockBuilder(Session::class)
                     ->onlyMethods([ 'getData', 'destroy' ])
                     ->getMock();
        $sess->expects($this->never())->method('destroy');
        $sess->expects($this->never())->method('getData');

        $core = new Core([ 'auth' => $auth, 'session' => $sess ]);

        $usr = $core->getCurrentUser();
        $this->assertInstanceOf(AdminUser::class, $usr);
    }

    public function testGetCurrentUserWhenNobodyLogin(): void
    {
        $auth = $this->getAuthEnabledMock();

        $sess = $this->getMockBuilder(Session::class)
                     ->onlyMethods([ 'getData' ])
                     ->getMock();
        $sess->expects($this->once())->method('getData')->willReturn([]);

        $core = new Core([ 'auth' => $auth, 'session' => $sess ]);

        $this->assertNull($core->getCurrentUser());
    }

    public function testGetCurrentUserWhenAdminSessionHasWrongId(): void
    {
        $auth = $this->getAuthEnabledMock();

        $sess = $this->getSessionMock([ 'getData' ]);
        $sess->expects($this->once())->method('getData')->willReturn([
            'user' => [ 'id' => 1, 'name' => 'admin', 'level' => User::LEVEL_ADMIN ]
        ]);

        $core = new Core([ 'auth' => $auth, 'session' => $sess ]);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('The user session has been broken!');
        $core->getCurrentUser();
    }

    public function testGetCurrentUserWhenAdminSessionHasWrongLevel(): void
    {
        $auth = $this->getAuthEnabledMock();

        $sess = $this->getSessionMock([ 'getData' ]);
        $sess->expects($this->once())->method('getData')->willReturn([
            'user' => [ 'id' => 0, 'name' => 'admin', 'level' => User::LEVEL_USER ]
        ]);

        $core = new Core([ 'auth' => $auth, 'session' => $sess ]);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('The user session has been broken!');
        $core->getCurrentUser();
    }

    public function testGetCurrentUserWhenAdminSessionIsOk(): void
    {
        $auth = $this->getAuthEnabledMock();

        $sess = $this->getSessionMock([ 'getData' ]);
        $sess->expects($this->once())->method('getData')->willReturn([
            'user' => [ 'id' => 0, 'name' => 'admin', 'level' => User::LEVEL_ADMIN ]
        ]);

        $core = new Core([ 'auth' => $auth, 'session' => $sess ]);

        $this->assertInstanceOf(AdminUser::class, $core->getCurrentUser());
    }

    public function testGetCurrentNotAdminUserWhenUserManagerIsDisabled(): void
    {
        $sess = $this->getSessionMock([ 'destroy', 'getData' ]);
        $sess->expects($this->once())->method('getData')->willReturn([
            'user' => [ 'id' => 1, 'name' => 'user', 'level' => User::LEVEL_USER ]
        ]);
        $sess->expects($this->once())->method('destroy');

        $core = new Core([
            'auth'    => $this->getAuthEnabledMock(),
            'config'  => $this->getConfig('users/user_management', false, false),
            'session' => $sess
        ]);

        $this->assertNull($core->getCurrentUser());
    }

    public function testGetCurrentNotAdminUserWhenUserManagerIsEnabledAndUserDoesNotExist(): void
    {
        $sess = $this->getSessionMock([ 'destroy', 'getData' ]);
        $sess->expects($this->once())->method('getData')->willReturn([
            'user' => [ 'id' => 1, 'name' => 'user', 'level' => User::LEVEL_USER ],
            's_id' => 1, 's_time' => (new DateTime())->getTimestamp() - 999999
        ]);
        $sess->expects($this->once())->method('destroy');

        $core = new Core([
            'auth'     => $this->getAuthEnabledMock(),
            'config'   => $this->getConfig('users/user_management', false, true),
            'database' => $this->getDbMapperUserNotFound(),
            'session'  => $sess
        ]);

        $this->expectException(SoftException::class);
        $core->getCurrentUser();
    }

    public function testGetCurrentNotAdminUserWhenUserManagerIsEnabledAndUserIsDisabled(): void
    {
        $sess = $this->getSessionMock([ 'destroy', 'getData' ]);
        $sess->expects($this->once())->method('getData')->willReturn([
            'user' => [ 'id' => 1, 'name' => 'user', 'level' => User::LEVEL_USER ],
            's_id' => 1, 's_time' => (new DateTime())->getTimestamp() - 999999
        ]);
        $sess->expects($this->once())->method('destroy');

        $core = new Core([
            'auth'     => $this->getAuthEnabledMock(),
            'config'   => $this->getConfig('users/user_management', false, true),
            'database' => $this->getDbMapperUserDisabled(),
            'session'  => $sess
        ]);

        $this->assertNull($core->getCurrentUser());
    }

    public function testGetCurrentNotAdminUserWhenUserManagerIsEnabledAndUserExists(): void
    {
        $sess = $this->getSessionMock([ 'destroy', 'getData' ]);
        $sess->expects($this->once())->method('getData')->willReturn([
            'user' => [ 'id' => 1, 'name' => 'user', 'level' => User::LEVEL_USER ],
            's_id' => 1, 's_time' => (new DateTime())->getTimestamp() - 999999
        ]);
        $sess->expects($this->never())->method('destroy');

        $core = new Core([
            'auth'     => $this->getAuthEnabledMock(),
            'config'   => $this->getConfig('users/user_management', false, true),
            'database' => $this->getDbMapperUserEnabled(),
            'session'  => $sess
        ]);

        $usr = $core->getCurrentUser();
        $this->assertInstanceOf(DbUser::class, $usr);
        $this->assertSame(User::LEVEL_MANAGER, $usr->level());
    }

    public function testGetCurrentNotAdminUserWhenUserManagerIsEnabledAndUserGotNewStatus(): void
    {
        $sess = $this->getSessionMock([ 'destroy', 'getData' ]);
        $sess->expects($this->once())->method('getData')->willReturn([
            'user' => [ 'id' => 1, 'name' => 'user', 'level' => User::LEVEL_USER ],
            's_id' => 1, 's_time' => (new DateTime())->getTimestamp() - 999999
        ]);
        $sess->expects($this->once())->method('destroy');

        $core = new Core([
            'auth'     => $this->getAuthEnabledMock(),
            'config'   => $this->getConfig('users/user_management', false, true),
            'database' => $this->getDbMapperUserChangedStatus(),
            'session'  => $sess
        ]);

        $this->assertNull($core->getCurrentUser());
    }

    public function testSetAdminUserWithName(): void
    {
        $sess = $this->getSessionMock([ 'destroy', 'getData' ]);
        $sess->expects($this->never())->method('destroy');
        $sess->expects($this->never())->method('getData');

        $core = new Core([
            'auth'    => $this->getAuthEnabledMock(),
            'session' => $sess
        ]);

        $core->setCurrentUser('admin');
        $this->assertInstanceOf(AdminUser::class, $core->getCurrentUser());
    }

    public function testSetAdminUserWithInstance(): void
    {
        $sess = $this->getSessionMock([ 'destroy', 'getData' ]);
        $sess->expects($this->never())->method('destroy');
        $sess->expects($this->never())->method('getData');

        $core = new Core([
            'auth'    => $this->getAuthEnabledMock(),
            'session' => $sess
        ]);

        $admin = new AdminUser();
        $core->setCurrentUser($admin);
        $this->assertSame($admin, $core->getCurrentUser());
    }

    public function testUnsetUserWithNullValueWithWeb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET'; // self::isWEB() -> true

        $sess = $this->getSessionMock([ 'destroy', 'getData', 'setData' ]);
        $sess->expects($this->once())->method('destroy');
        $sess->expects($this->never())->method('getData');
        $sess->expects($this->never())->method('setData');

        $core = new Core([
            'session' => $sess
        ]);

        $core->setCurrentUser(null);

        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testSetDbUserWithWeb(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET'; // self::isWEB() -> true

        $sess = $this->getSessionMock([ 'destroy', 'getData', 'setData' ]);
        $sess->expects($this->once())->method('destroy');
        $sess->expects($this->never())->method('getData');
        $sess->expects($this->once())->method('setData');

        $db = $this->getDbMapperUserEnabled();

        $core = new Core([
            'session'  => $sess,
            'database' => $db
        ]);

        $core->setCurrentUser(new DbUser([ 'id' => 1, 'name' => 'User' ], $db));

        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testSetDbUserWithNoWeb(): void
    {
        $sess = $this->getSessionMock([ 'destroy', 'getData', 'setData' ]);
        $sess->expects($this->never())->method('destroy');
        $sess->expects($this->never())->method('getData');
        $sess->expects($this->never())->method('setData');

        $core = new Core([
            'session' => $sess
        ]);

        $core->setCurrentUser(new DbUser([ 'id' => 1, 'name' => 'User' ]));
    }

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
        $this->expectExceptionMessage('Required dependency is missing: ext-fake.');
        $this->core->checkDependencies('fake');
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

    private function getAuthEnabledMock()
    {
        $auth = $this->getMockBuilder(Auth::class)
                     ->disableOriginalConstructor()
                     ->onlyMethods([ 'isEnabled' ])
                     ->getMock();
        $auth->expects($this->once())->method('isEnabled')->willReturn(true);
        return $auth;
    }

    private function getAuthDisabledMock()
    {
        $auth = $this->getMockBuilder(Auth::class)
                     ->disableOriginalConstructor()
                     ->onlyMethods([ 'isEnabled' ])
                     ->getMock();
        $auth->expects($this->once())->method('isEnabled')->willReturn(false);
        return $auth;
    }

    private function getSessionMock(array $methods)
    {
        return $this->getMockBuilder(Session::class)->onlyMethods($methods)->getMock();
    }

    private function getConfig(string $param, $defval, $value)
    {
        $config = $this->getMockBuilder(Config::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods([ 'get' ])
                       ->getMock();
        $config->expects($this->once())
               ->method('get')
               ->with($this->equalTo($param), $this->equalTo($defval))
               ->willReturn($value);
        return $config;
    }

    private function getDbMapperUserNotFound(): object
    {
        $mapper = $this->getMockBuilder(UserMapperInterface::class)->disableOriginalConstructor()->getMock();
        $mapper->expects($this->once())->method('fetch')->willThrowException(new DatabaseNotFoundException());

        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->onlyMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())->method('getMapper')->with('user')->willReturn($mapper);
        return $db;
    }

    private function getDbMapperUserDisabled(): object
    {
        return $this->getDbMapperUserOnce('fetch', [ 'enabled', 'session' ], [ false, 1 ]);
    }

    private function getDbMapperUserEnabled(): object
    {
        return $this->getDbMapperUserOnce('fetch', [ 'enabled', 'session', 'level' ], [ true, 1, User::LEVEL_MANAGER ]);
    }

    private function getDbMapperUserChangedStatus(): object
    {
        return $this->getDbMapperUserOnce('fetch', [ 'enabled', 'session', 'level' ], [ true, 2, User::LEVEL_MANAGER ]);
    }

    private function getDbMapperUserOnce(string $method, array $keys, array $values): object
    {
        $mapper = $this->getMockBuilder(UserMapperInterface::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods([
                           'exists', 'save', 'delete', 'fetch', 'list', 'getPasswordHash',
                           'savePasswordHash', 'setUserKey'
                       ])
                       ->getMock();
        $mapper->expects($this->once())->method($method)->willReturnCallback(function (&$data) use ($keys, $values) {
            for ($i = 0; $i < count($keys); ++$i) {
                $data[$keys[$i]] = $values[$i];
            }
        });

        $db = $this->getMockBuilder(DatabaseController::class)
                   ->disableOriginalConstructor()
                   ->onlyMethods([ 'getMapper' ])
                   ->getMock();
        $db->expects($this->once())->method('getMapper')->with('user')->willReturn($mapper);
        return $db;
    }
}
