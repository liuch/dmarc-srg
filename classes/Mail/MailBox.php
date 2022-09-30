<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020 Aleksey Andreev (liuch)
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

class MailBox
{
    private $conn;
    private $server;
    private $host;
    private $mbox;
    private $name;
    private $uname;
    private $passw;
    private $delim;
    private $expunge;

    public function __construct($params)
    {
        if (!is_array($params)) {
            throw new \Exception('Incorrect mailbox params', -1);
        }

        $this->conn = null;
        $this->uname = $params['username'];
        $this->passw = $params['password'];
        if (isset($params['name']) && is_string($params['name']) && strlen($params['name']) > 0) {
            $this->name = $params['name'];
        } else {
            $name = $this->uname;
            $pos = strpos($name, '@');
            if ($pos !== false && $pos !== 0) {
                $name = substr($name, 0, $pos);
            }
            $this->name = $name;
        }
        $this->mbox = $params['mailbox'];
        $this->host = $params['host'];

        $flags = $params['encryption'] ?? '';
        switch ($flags) {
            case 'ssl':
                $flags = '/ssl';
                break;
            case 'none':
                $flags = '/notls';
                break;
            case 'starttls':
            default:
                $flags = '/tls';
                break;
        }
        if (isset($params['novalidate-cert']) && $params['novalidate-cert'] === true) {
            $flags .= '/novalidate-cert';
        }

        $this->server = sprintf('{%s/imap%s}', $this->host, $flags);
        $this->expunge = false;
    }

    public function __destruct()
    {
        if (!is_null($this->conn)) {
            try {
                if ($this->expunge) {
                    @imap_expunge($this->conn);
                }
                @imap_close($this->conn);
            } catch (\Exception $e) {
                $this->resetErrorStack();
            }
        }
    }

    public function childMailbox(string $mailbox_name)
    {
        $this->ensureConnection();
        $mb_list = imap_list(
            $this->conn,
            \imap_utf8_to_mutf7($this->server),
            \imap_utf8_to_mutf7($this->mbox) . $this->delim . \imap_utf8_to_mutf7($mailbox_name)
        );
        if (!$mb_list) {
            return null;
        }
        $child = clone $this;
        $child->mbox .= $this->delim . $mailbox_name;
        $child->conn = null;
        $child->expunge = false;
        return $child;
    }

    public function name()
    {
        return $this->name;
    }

    public function host()
    {
        return $this->host;
    }

    public function mailbox()
    {
        return $this->mbox;
    }

    public function check()
    {
        $status = [];
        try {
            $this->ensureConnection();
            $res = @imap_status($this->conn, \imap_utf8_to_mutf7($this->server . $this->mbox), SA_MESSAGES | SA_UNSEEN);
            if ($res === false) {
                $err_msg = imap_last_error();
                $this->resetErrorStack();
                throw new \Exception($err_msg, -1);
            }
            return [
                'error_code' => 0,
                'message'    => 'Successfully',
                'status'     => [
                    'messages' => $res->messages,
                    'unseen'   => $res->unseen
                ]
            ];
        } catch (\Exception $e) {
            return [
                'error_code' => $e->getCode(),
                'message'    => $e->getMessage()
            ];
        }
    }

    public function search($criteria)
    {
        $this->ensureConnection();
        $res = @imap_search($this->conn, $criteria);
        if ($res === false) {
            $err_msg = imap_last_error();
            if (!$err_msg) {
                return [];
            }
            $this->resetErrorStack();
            throw new \Exception($err_msg, -1);
        }
        return $res;
    }

    public function sort($criteria, $search_criteria, $reverse)
    {
        $this->ensureConnection();
        $res = @imap_sort($this->conn, $criteria, $reverse ? 1 : 0, SE_NOPREFETCH, $search_criteria);
        if ($res === false) {
            $err_msg = imap_last_error();
            if (!$err_msg) {
                return [];
            }
            $this->resetErrorStack();
            throw new \Exception($err_msg, -1);
        }
        return $res;
    }

    public function message($number)
    {
        return new MailMessage($this->conn, $number);
    }

    public function ensureMailbox($mailbox_name)
    {
        $mbn = \imap_utf8_to_mutf7($mailbox_name);
        $srv = \imap_utf8_to_mutf7($this->server);
        $mbo = \imap_utf8_to_mutf7($this->mbox);
        $this->ensureConnection();
        $mb_list = @imap_list($this->conn, $srv, $mbo . $this->delim . $mbn);
        if (!$mb_list) {
            $new_mailbox = $srv . $mbo . $this->delim . $mbn;
            if (!@imap_createmailbox($this->conn, $new_mailbox)) {
                $this->resetErrorStack();
                throw new \Exception('Failed to create a new mailbox', -1);
            }
            @imap_subscribe($this->conn, $new_mailbox);
            $this->resetErrorStack();
        }
    }

    public function moveMessage($number, $mailbox_name)
    {
        $this->ensureConnection();
        $target = \imap_utf8_to_mutf7($this->mbox) . $this->delim . \imap_utf8_to_mutf7($mailbox_name);
        if (!@imap_mail_move($this->conn, strval($number), $target)) {
            $err_str = imap_last_error();
            $this->resetErrorStack();
            throw new \Exception('Failed to move a message: ' . $err_str, -1);
        }
        $this->expunge = true;
    }

    public function deleteMessage($number)
    {
        $this->ensureConnection();
        @imap_delete($this->conn, strval($number));
        $this->expunge = true;
    }

    private function ensureConnection()
    {
        if (is_null($this->conn)) {
            $err_msg = null;
            $srv = \imap_utf8_to_mutf7($this->server);
            $this->conn = @imap_open($srv, $this->uname, $this->passw, OP_HALFOPEN);
            if ($this->conn !== false) {
                $mbx = \imap_utf8_to_mutf7($this->mbox);
                $mb_list = @imap_getmailboxes($this->conn, $srv, $mbx);
                if ($mb_list && count($mb_list) === 1) {
                    $this->delim = $mb_list[0]->delimiter ?? '/';
                    if (@imap_reopen($this->conn, $srv . $mbx)) {
                        return;
                    }
                } else {
                    $err_msg = "Mailbox `{$this->mbox}` not found";
                }
            }
            if (!$err_msg) {
                $err_msg = imap_last_error();
            }
            $this->resetErrorStack();
            if ($this->conn) {
                @imap_close($this->conn);
                $this->conn = null;
            }
            throw new \Exception($err_msg ? $err_msg : 'Cannot connect to the mail server', -1);
        }
    }

    private function resetErrorStack()
    {
        imap_errors();
        imap_alerts();
    }
}
