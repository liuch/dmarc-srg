<?php

namespace Liuch\DmarcSrg;

class DateTimeTest extends \PHPUnit\Framework\TestCase
{
    public function testSimpleJsonSerialize(): void
    {
        $this->assertJsonStringEqualsJsonString(
            '[ "2022-10-15T18:35:20+00:00" ]',
            \json_encode([ new DateTime('2022-10-15 18:35:20') ])
        );
    }

    public function testUnixTimestampJsonSerialize(): void
    {
        $this->assertJsonStringEqualsJsonString(
            '[ "1970-01-01T00:00:01+00:00" ]',
            \json_encode([ new DateTime('@1') ])
        );
    }

    public function testCurrentTimeJsonSerialize(): void
    {
        $now = new DateTime();
        $this->assertJsonStringEqualsJsonString(
            " [ \"{$now->format(\DateTime::ATOM)}\" ]",
            \json_encode([ $now ])
        );
    }
}
