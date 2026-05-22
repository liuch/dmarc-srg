<?php

namespace Liuch\DmarcSrg\Mail\ImapEngine;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

class MailAttachmentTest extends TestCase
{
    public function testMimeTypeIgnoresEmailHeader(): void
    {
        // gzip magic bytes followed by dummy payload
        $gzipContent = "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\x03" . str_repeat("\x00", 20);

        $mockAttachment = new class ($gzipContent) {
            private $content;
            public function __construct(string $content)
            {
                $this->content = $content;
            }
            public function contentType(): string
            {
                return 'text/xml';
            }
            public function filename(): string
            {
                return 'report.xml.gz';
            }
            public function contentStream()
            {
                return Utils::streamFor($this->content);
            }
        };

        $mailAttachment = new MailAttachment($mockAttachment);

        // Must not trust the attacker-controlled Content-Type header
        $this->assertNotSame('text/xml', $mailAttachment->mimeType());
    }
}
