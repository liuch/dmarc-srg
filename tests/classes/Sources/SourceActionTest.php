<?php

namespace Liuch\DmarcSrg;

use Liuch\DmarcSrg\Sources\SourceAction;

class SourceActionTest extends \PHPUnit\Framework\TestCase
{
    public function testUnknowAction(): void
    {
        $this->assertCount(0, SourceAction::fromSetting('some_action', 0, ''));
    }

    public function testMarkSeen(): void
    {
        $this->assertCount(0, SourceAction::fromSetting('mark_seen:??', 0, ''));
        $sa = SourceAction::fromSetting('mark_seen', 0, '')[0];
        $this->assertSame(SourceAction::ACTION_SEEN, $sa->type);
    }

    public function testMoveTo(): void
    {
        $this->assertCount(0, SourceAction::fromSetting('move_to', 0, ''));
        $sa = SourceAction::fromSetting('move_to:target', 0, '')[0];
        $this->assertSame(SourceAction::ACTION_MOVE, $sa->type);
        $this->assertSame('target', $sa->param);
    }

    public function testMoveToAndBasename(): void
    {
        $this->assertCount(1, SourceAction::fromSetting('move_to:/target', 0, ''));
        $this->assertCount(0, SourceAction::fromSetting('move_to:/target', SourceAction::FLAG_BASENAME, ''));
    }

    public function testDelete(): void
    {
        $this->assertCount(0, SourceAction::fromSetting('delete:??', 0, ''));
        $sa = SourceAction::fromSetting('delete', 0, '')[0];
        $this->assertSame(SourceAction::ACTION_DELETE, $sa->type);
    }

    public function testMultipleActions(): void
    {
        $list = SourceAction::fromSetting([ 'mark_seen', 'move_to:target', 'delete' ], 0, '');
        $this->assertCount(3, $list);
        $this->assertSame(SourceAction::ACTION_SEEN, $list[0]->type);
        $this->assertSame(SourceAction::ACTION_MOVE, $list[1]->type);
        $this->assertSame(SourceAction::ACTION_DELETE, $list[2]->type);
    }

    public function testMultipleActionsWithWrongItem(): void
    {
        $list = SourceAction::fromSetting([ 'mark_seen', 'move_to:', 'delete' ], 0, '');
        $this->assertCount(2, $list);
        $this->assertSame(SourceAction::ACTION_SEEN, $list[0]->type);
        $this->assertSame(SourceAction::ACTION_DELETE, $list[1]->type);
    }

    public function testDefaultAction(): void
    {
        $list = SourceAction::fromSetting([], 0, 'mark_seen');
        $this->assertCount(1, $list);
        $this->assertSame(SourceAction::ACTION_SEEN, $list[0]->type);

        $list = SourceAction::fromSetting('', 0, 'mark_seen');
        $this->assertCount(1, $list);
        $this->assertSame(SourceAction::ACTION_SEEN, $list[0]->type);
    }

    public function testWrongDefaultAction(): void
    {
        $list = SourceAction::fromSetting([], 0, 'wrong_default');
        $this->assertCount(0, $list);
    }

    public function testDuplicates(): void
    {
        $s_list1 = [ 'mark_seen', 'move_to:target', 'delete' ];
        $s_list2 = $s_list1;
        $this->assertCount(3, SourceAction::fromSetting(array_merge($s_list1, $s_list2), 0, ''));
    }
}
