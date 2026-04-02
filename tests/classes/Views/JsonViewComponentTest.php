<?php

namespace Liuch\DmarcSrg\Requests;

use Liuch\DmarcSrg\Requests\HttpRequest;
use Liuch\DmarcSrg\Views\JsonViewComponent;

class JsonViewComponentTest extends \PHPUnit\Framework\TestCase
{
    public function testNormalRender(): void
    {
        $origJson = [ 'key1' => 'value1', 'key2' => 999 ];

        $req = new HttpRequest();
        $req->setData($origJson);

        $view = new JsonViewComponent();
        ob_start();
        $view->render($req);
        $output = ob_get_contents();
        ob_end_clean();

        $outputJson = json_decode($output, true);
        $this->assertSame($outputJson, $origJson);
    }

    public function testErrorRender(): void
    {
        $origJson = [ 'key1' => 'value1', 'key2' => 999 ];

        $req = new HttpRequest();
        $req->setData($origJson);
        $req->setErrorCode(55);
        $req->setMessage('Error message');

        $view = new JsonViewComponent();
        ob_start();
        $view->render($req);
        $output = ob_get_contents();
        ob_end_clean();

        $outputJson = json_decode($output, true);
        $this->assertSame([ 'error_code' => 55, 'message' => 'Error message' ], $outputJson);
    }
}
