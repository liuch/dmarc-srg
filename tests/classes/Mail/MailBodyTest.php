<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Mail\MailBody;

class MailBodyTest extends \PHPUnit\Framework\TestCase
{
    private $text = null;
    private $html = null;

    public function setUp(): void
    {
        $this->text = [ 'text string' ];
        $this->html = [ 'html string' ];
    }

    public function testTextContentType(): void
    {
        $body = new MailBody();
        $body->setText($this->text);
        $this->assertSame('text/plain; charset=utf-8', $body->contentType());
    }

    public function testHtmlContentType(): void
    {
        $body = new MailBody();
        $body->setHtml($this->html);
        $this->assertSame('text/html; charset=utf-8', $body->contentType());
    }

    public function testMultipartContentType(): void
    {
        $body = new MailBody();
        $body->setText($this->text);
        $body->setHtml($this->html);
        $this->assertStringStartsWith('multipart/alternative; boundary=', $body->contentType());
    }

    public function testTextContent(): void
    {
        $body = new MailBody();
        $body->setText($this->text);
        $content = $body->content();
        $this->assertCount(1, $content);
        $this->assertSame('text string', $content[0]);
    }

    public function testHtmlContent(): void
    {
        $body = new MailBody();
        $body->setText($this->html);
        $content = $body->content();
        $this->assertCount(1, $content);
        $this->assertSame('html string', $content[0]);
    }

    public function testMultipartContent(): void
    {
        $body = new MailBody();
        $body->setText($this->text);
        $body->setHtml($this->html);
        $boundary = substr($body->contentType(), 33, -1);
        $content = $body->content();
        $this->assertCount(10, $content);
        $this->assertSame('--' . $boundary, $content[0]);
        $this->assertSame('Content-Type: text/plain; charset=utf-8', $content[1]);
        $this->assertSame('Content-Transfer-Encoding: 7bit', $content[2]);
        $this->assertSame('', $content[3]);
        $this->assertSame('text string', $content[4]);
        $this->assertSame('--' . $boundary, $content[5]);
        $this->assertSame('Content-Type: text/html; charset=utf-8', $content[6]);
        $this->assertSame('Content-Transfer-Encoding: 7bit', $content[7]);
        $this->assertSame('', $content[8]);
        $this->assertSame('html string', $content[9]);
    }
}
