<?php

namespace Liuch\DmarcSrg\Mail\PhpExtension;

function imap_fetchbody($stream, $msg_num, $part_num, $options = 0)
{
    return \Liuch\DmarcSrg\MailAttachmentTest::$bodyContent;
}

function imap_qprint($string)
{
    return \Liuch\DmarcSrg\MailAttachmentTest::$qprintResult;
}

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Mail\PhpExtension\MailAttachment;
use Liuch\DmarcSrg\Exception\SoftException;

class MailAttachmentTest extends \PHPUnit\Framework\TestCase
{
    public static $bodyContent = '';
    public static $qprintResult = '';

    public function testBase64DecodeFailure(): void
    {
        MailAttachmentTest::$bodyContent = 'not-valid-base64!!!';

        $mailbox = new class {
            public function connection()
            {
                return null;
            }
        };

        $attachment = new MailAttachment([
            'mailbox'  => $mailbox,
            'filename' => 'test.xml',
            'bytes'    => 100,
            'number'   => 1,
            'mnumber'  => 1,
            'encoding' => \ENCBASE64,
        ]);

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Failed to decode base64 attachment');
        $attachment->datastream();
    }

    public function testQuotedPrintableDecodeFailure(): void
    {
        MailAttachmentTest::$bodyContent = 'some content';
        MailAttachmentTest::$qprintResult = false;

        $mailbox = new class {
            public function connection()
            {
                return null;
            }
        };

        $attachment = new MailAttachment([
            'mailbox'  => $mailbox,
            'filename' => 'test.xml',
            'bytes'    => 100,
            'number'   => 1,
            'mnumber'  => 1,
            'encoding' => \ENCQUOTEDPRINTABLE,
        ]);

        $this->expectException(SoftException::class);
        $this->expectExceptionMessage('Failed to decode quoted-printable attachment');
        $attachment->datastream();
    }
}
