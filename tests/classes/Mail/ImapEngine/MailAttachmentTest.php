<?php

namespace Liuch\DmarcSrg\Mail\ImapEngine;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Liuch\DmarcSrg\Exception\SoftException;

class MailAttachmentTest extends TestCase
{
    public function testMimeTypeRejectsMismatchedHeader(): void
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

        $this->expectException(SoftException::class);
        $mailAttachment->mimeType();
    }

    public function testMimeTypeAcceptsMatchingHeader(): void
    {
        $gzipContent = "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\x03" . str_repeat("\x00", 20);

        $mockAttachment = new class ($gzipContent) {
            private $content;
            public function __construct(string $content)
            {
                $this->content = $content;
            }
            public function contentType(): string
            {
                return 'application/gzip';
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

        $this->assertSame('application/gzip', $mailAttachment->mimeType());
    }

    public function testMimeTypeFallsBackForOctetStream(): void
    {
        $gzipContent = "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\x03" . str_repeat("\x00", 20);

        $mockAttachment = new class ($gzipContent) {
            private $content;
            public function __construct(string $content)
            {
                $this->content = $content;
            }
            public function contentType(): string
            {
                return 'application/octet-stream';
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

        $this->assertSame('application/gzip', $mailAttachment->mimeType());
    }
}
