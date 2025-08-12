<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2024 Aleksey Andreev (liuch)
 *
 * Available at:
 * https://github.com/liuch/dmarc-srg
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Liuch\DmarcSrg\Mail;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Exception\LogicException;

class MailBoxes implements \Iterator
{
    private $box_list;
    private $index = 0;

    public function __construct()
    {
        $mailboxes = Core::instance()->config('mailboxes');
        $library = Core::instance()->config('fetcher/mailboxes/library', '');

        $this->box_list = [];
        if (is_array($mailboxes)) {
            $cnt = count($mailboxes);
            if ($cnt > 0) {
                if (isset($mailboxes[0])) {
                    for ($i = 0; $i < $cnt; ++$i) {
                        $this->box_list[] = $this->getMailbox($mailboxes[$i], $library);
                    }
                } else {
                    $this->box_list[] = $this->getMailbox($mailboxes, $library);
                }
            }
        }
    }

    public function count()
    {
        return count($this->box_list);
    }

    public function list()
    {
        $id = 0;
        $res = [];
        foreach ($this->box_list as &$mbox) {
            $id += 1;
            $res[] = [
                'id'      => $id,
                'name'    => $mbox->name(),
                'host'    => $mbox->host(),
                'mailbox' => $mbox->folder()
            ];
        }
        unset($mbox);
        return $res;
    }

    public function mailbox($id)
    {
        if (!is_int($id) || $id <= 0 || $id > count($this->box_list)) {
            throw new LogicException("Incorrect mailbox Id: {$id}");
        }
        return $this->box_list[$id - 1];
    }

    public function check($id)
    {
        if ($id !== 0) {
            return $this->mailbox($id)->check();
        }

        $results = [];
        $err_cnt = 0;
        $box_cnt = count($this->box_list);
        for ($i = 0; $i < $box_cnt; ++$i) {
            $r = $this->box_list[$i]->check();
            if ($r['error_code'] !== 0) {
                ++$err_cnt;
            }
            $results[] = $r;
        }
        $res = [];
        if ($err_cnt == 0) {
            $res['error_code'] = 0;
            $res['message'] = 'Success';
        } else {
            $res['error_code'] = -1;
            $res['message'] = sprintf('%d of the %d mailboxes failed the check', $err_cnt, $box_cnt);
        }
        $res['results'] = $results;
        return $res;
    }

    public function current(): object
    {
        return $this->box_list[$this->index];
    }

    public function key(): int
    {
        return $this->index;
    }

    public function next(): void
    {
        ++$this->index;
    }

    public function rewind(): void
    {
        $this->index = 0;
    }

    public function valid(): bool
    {
        return isset($this->box_list[$this->index]);
    }

    private function getMailbox(array $params, string $library)
    {
        switch ($library) {
            case 'php-extension':
                $dir_name = 'PhpExtension';
                break;
            case 'imap-engine':
                $dir_name = 'ImapEngine';
                break;
            case '':
            case 'auto':
                if (class_exists('\DirectoryTree\ImapEngine\Mailbox')) {
                    $dir_name = 'ImapEngine';
                } else {
                    $dir_name = 'PhpExtension';
                }
                break;
            default:
                throw new LogicException("Mailbox: wrong library name '$library'");
        }
        $reflection = new \ReflectionClass($this);
        $class_name = $reflection->getNamespaceName() . '\\' . $dir_name . '\\MailBox';
        return new $class_name($params);
    }
}
