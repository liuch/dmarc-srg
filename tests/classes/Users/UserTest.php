<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Users\User;
use Liuch\DmarcSrg\Exception\SoftException;
use PHPUnit\Framework\Attributes\DataProvider;

class UserTest extends \PHPUnit\Framework\TestCase
{
    public function testConstantsExistence(): void
    {
        $this->assertIsInt(User::LEVEL_ADMIN);
        $this->assertIsInt(User::LEVEL_MANAGER);
        $this->assertIsInt(User::LEVEL_USER);
    }

    public function testLevelValue(): void
    {
        $this->assertGreaterThan(User::LEVEL_MANAGER, User::LEVEL_ADMIN);
        $this->assertGreaterThan(User::LEVEL_USER, User::LEVEL_MANAGER);
    }

    /**
     * @dataProvider dataProvider1
     */
    #[DataProvider('dataProvider1')]
    public function testLevelToStringCorrectValue(int $ilevel, string $slevel): void
    {
        $this->assertSame(User::levelToString($ilevel), $slevel);
    }

    /**
     * @dataProvider dataProvider1
     */
    #[DataProvider('dataProvider1')]
    public function testLevelToStringIncorrectValue(int $ilevel, string $slevel): void
    {
        if ($ilevel === User::LEVEL_USER) {
            $this->assertSame(User::levelToString($ilevel - 1), $slevel);
        } else {
            $this->assertNotSame(User::levelToString($ilevel - 1), $slevel);
        }
    }

    /**
     * @dataProvider dataProvider1
     */
    #[DataProvider('dataProvider1')]
    public function testStringToLevelCorrectValue(int $ilevel, string $slevel): void
    {
        $this->assertSame(User::stringToLevel($slevel), $ilevel);
    }

    public function testStringToLevelIncorrectValue(): void
    {
        $this->expectException(SoftException::class);
        User::stringToLevel('wrong_level');
    }

    public static function dataProvider1(): array
    {
        return [
            [ User::LEVEL_ADMIN,   'admin'   ],
            [ User::LEVEL_MANAGER, 'manager' ],
            [ User::LEVEL_USER,    'user'    ]
        ];
    }
}
