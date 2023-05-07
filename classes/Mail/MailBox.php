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

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Exception\SoftException;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\MailboxException;

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
    private $attrs;
    private $expunge;

    public function __construct($params)
    {
        if (!is_array($params)) {
            throw new LogicException('Incorrect mailbox params');
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
            default:
                $flags = '/ssl';
                break;
            case 'none':
                $flags = '/notls';
                break;
            case 'starttls':
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
        if (extension_loaded('imap')) {
            $this->cleanup();
        }
    }

    public function childMailbox(string $mailbox_name)
    {
        $this->ensureConnection();
        try {
            $mb_list = imap_list(
                $this->conn,
                self::utf8ToMutf7($this->server),
                self::utf8ToMutf7($this->mbox) . $this->delim . self::utf8ToMutf7($mailbox_name)
            );
        } catch (\ErrorException $e) {
            $mb_list = false;
        }
        $this->ensureErrorLog('imap_list');
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
        try {
            $this->ensureConnection();
            try {
                $res = imap_status(
                    $this->conn,
                    self::utf8ToMutf7($this->server . $this->mbox),
                    SA_MESSAGES | SA_UNSEEN
                );
            } catch (\ErrorException $e) {
                $res = false;
            }
            $error_message = $this->ensureErrorLog();
            if (!$res) {
                throw new MailboxException($error_message ?? 'Failed to get the mail box status');
            }

            if ($this->attrs & \LATT_NOSELECT) {
                throw new MailboxException('The resource is not a mailbox');
            }

            $this->checkRights();
        } catch (MailboxException $e) {
            return ErrorHandler::exceptionResult($e);
        }
        return [
            'error_code' => 0,
            'message'    => 'Successfully',
            'status'     => [
                'messages' => $res->messages,
                'unseen'   => $res->unseen
            ]
        ];
    }

    public function search($criteria)
    {
        $this->ensureConnection();
        try {
            $res = imap_search($this->conn, $criteria);
        } catch (\ErrorException $e) {
            $res = false;
        }
        $error_message = $this->ensureErrorLog('imap_search');
        if ($res === false) {
            if (!$error_message) {
                return [];
            }
            throw new MailboxException(
                'Failed to search email messages',
                -1,
                new \ErrorException($error_message)
            );
        }
        return $res;
    }

    public function sort($criteria, $search_criteria, $reverse)
    {
        $this->ensureConnection();
        try {
            $res = imap_sort($this->conn, $criteria, $reverse ? 1 : 0, SE_NOPREFETCH, $search_criteria);
        } catch (\ErrorException $e) {
            $res = false;
        }
        $error_message = $this->ensureErrorLog('imap_sort');
        if ($res === false) {
            if (!$error_message) {
                return [];
            }
            throw new MailboxException(
                'Failed to sort email messages',
                -1,
                new \ErrorException($error_message)
            );
        }
        return $res;
    }

    public function message($number)
    {
        return new MailMessage($this->conn, $number);
    }

    public function ensureMailbox($mailbox_name)
    {
        $mbn = self::utf8ToMutf7($mailbox_name);
        $srv = self::utf8ToMutf7($this->server);
        $mbo = self::utf8ToMutf7($this->mbox);
        $this->ensureConnection();
        try {
            $mb_list = imap_list($this->conn, $srv, $mbo . $this->delim . $mbn);
        } catch (\ErrorException $e) {
            $mb_list = false;
        }
        $error_message = $this->ensureErrorLog('imap_list');
        if (empty($mb_list)) {
            if ($error_message) {
                throw new MailboxException(
                    'Failed to get the list of mailboxes',
                    -1,
                    new \ErrorException($error_message)
                );
            }

            $new_mailbox = "{$srv}{$mbo}{$this->delim}{$mbn}";
            try {
                $res = imap_createmailbox($this->conn, $new_mailbox);
            } catch (\ErrorException $e) {
                $res = false;
            }
            $error_message = $this->ensureErrorLog('imap_createmailbox');
            if (!$res) {
                throw new MailboxException(
                    'Failed to create a new mailbox',
                    -1,
                    new \ErrorException($error_message ?? 'Unknown')
                );
            }

            try {
                imap_subscribe($this->conn, $new_mailbox);
            } catch (\ErrorException $e) {
            }
            $this->ensureErrorLog('imap_subscribe');
        }
    }

    public function moveMessage($number, $mailbox_name)
    {
        $this->ensureConnection();
        $target = self::utf8ToMutf7($this->mbox) . $this->delim . self::utf8ToMutf7($mailbox_name);
        try {
            $res = imap_mail_move($this->conn, strval($number), $target);
        } catch (\ErrorException $e) {
            $res = false;
        }
        $error_message = $this->ensureErrorLog('imap_mail_move');
        if (!$res) {
            throw new MailboxException(
                'Failed to move a message',
                -1,
                new \ErrorException($error_message ?? 'Unknown')
            );
        }
        $this->expunge = true;
    }

    public function deleteMessage($number)
    {
        $this->ensureConnection();
        try {
            imap_delete($this->conn, strval($number));
        } catch (\ErrorException $e) {
        }
        $this->ensureErrorLog('imap_delete');
        $this->expunge = true;
    }

    public static function resetErrorStack()
    {
        imap_errors();
        imap_alerts();
    }

    private function ensureConnection()
    {
        if (is_null($this->conn)) {
            $error_message = null;
            $srv = self::utf8ToMutf7($this->server);
            try {
                $this->conn = imap_open($srv, $this->uname, $this->passw, OP_HALFOPEN);
            } catch (\ErrorException $e) {
                $this->conn = null;
            }
            if ($this->conn) {
                $mbx = self::utf8ToMutf7($this->mbox);
                try {
                    $mb_list = imap_getmailboxes($this->conn, $srv, $mbx);
                } catch (\ErrorException $e) {
                    $mb_list = null;
                }
                if ($mb_list && count($mb_list) === 1) {
                    $this->delim = $mb_list[0]->delimiter ?? '/';
                    $this->attrs = $mb_list[0]->attributes ?? 0;
                    try {
                        if (imap_reopen($this->conn, $srv . $mbx)) {
                            return;
                        }
                    } catch (\ErrorException $e) {
                    }
                } else {
                    $error_message = "Mailbox `{$this->mbox}` not found";
                }
            }
            if (!$error_message) {
                $error_message = imap_last_error();
                if (!$error_message) {
                    $error_message = 'Cannot connect to the mail server';
                }
            }
            Core::instance()->logger()->error("IMAP error: {$error_message}");
            self::resetErrorStack();
            if ($this->conn) {
                try {
                    imap_close($this->conn);
                } catch (\ErrorException $e) {
                }
                $this->ensureErrorLog('imap_close');
            }
            $this->conn = null;
            throw new MailboxException($error_message);
        }
    }

    private function ensureErrorLog(string $prefix = 'IMAP error')
    {
        if ($error_message = imap_last_error()) {
            self::resetErrorStack();
            $error_message = "{$prefix}: {$error_message}";
            Core::instance()->logger()->error($error_message);
            return $error_message;
        }
        return null;
    }

    private function checkRights(): void
    {
        if ($this->attrs & \LATT_NOINFERIORS) {
            throw new SoftException('The mailbox may not have any children mailboxes');
        }

        if (!function_exists('imap_getacl')) {
            return;
        }

        $mbox = self::utf8ToMutf7($this->mbox);
        try {
            $acls = imap_getacl($this->conn, $mbox);
        } catch (\ErrorException $e) {
            // It's not possible to get the ACLs information
            $acls = false;
        }
        $this->ensureErrorLog('imap_getacl');
        if ($acls !== false) {
            $needed_rights_map = [
                'l' => 'LOOKUP',
                'r' => 'READ',
                's' => 'WRITE-SEEN',
                't' => 'WRITE-DELETE',
                'k' => 'CREATE'
            ];
            $result = [];
            $needed_rights = array_keys($needed_rights_map);
            foreach ([ "#{$this->uname}", '#authenticated', '#anyone' ] as $identifier) {
                if (isset($acls[$identifier])) {
                    $rights = $acls[$identifier];
                    foreach ($needed_rights as $r) {
                        if (!str_contains($rights, $r)) {
                            $result[] = $needed_rights_map[$r];
                        }
                    }
                    break;
                }
            }
            if (count($result) > 0) {
                throw new SoftException(
                    'Not enough rights. Additionally, these rights are required: ' . implode(', ', $result)
                );
            }
        }
    }

    /**
     * Deletes messages marked for deletion, if any, and closes the connection
     *
     * @return void
     */
    private function cleanup(): void
    {
        self::resetErrorStack();
        if (!is_null($this->conn)) {
            try {
                if ($this->expunge) {
                    imap_expunge($this->conn);
                }
            } catch (\ErrorException $e) {
            }
            $this->ensureErrorLog('imap_expunge');

            try {
                imap_close($this->conn);
            } catch (\ErrorException $e) {
            }
            $this->ensureErrorLog('imap_close');
        }
    }

    /**
     * It's a replacement for the standard function imap_utf8_to_mutf7
     *
     * @param string $s A UTF-8 encoded string
     *
     * @return string|false
     */
    private static function utf8ToMutf7(string $s)
    {
        return mb_convert_encoding($s, 'UTF7-IMAP', 'UTF-8');
    }
}
