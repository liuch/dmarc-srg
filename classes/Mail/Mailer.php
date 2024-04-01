<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2024 Aleksey Andreev (liuch)
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
 *
 * =========================
 *
 * This file contains an abstract wrapper class to implement sending emails
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Mail;

use Liuch\DmarcSrg\Exception\LogicException;

/**
 * A wrapper class to support different methods of sending emails
 */
abstract class Mailer
{
    protected $params = null;
    protected $from   = null;
    protected $addr   = [];
    protected $subj   = null;
    protected $body   = null;

    private function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     * Factory method
     *
     * @param array $params Mailer parameters from the configuration file
     *
     * @throws LogicException
     *
     * @return object Instance of a class inherited from Mailer
     */
    public static function get(array $params)
    {
        switch ($params['library'] ?? 'internal') {
            case 'internal':
                return new MailerInternal($params);
            case 'phpmailer':
                return new MailerPhpMailer($params);
        }
        throw new LogicException('Unsupported mailing library: ' . $library);
    }

    /**
     * Add a "To" address
     *
     * @param string $address The email address to send to
     *
     * @return $this
     */
    public function addAddress(string $address)
    {
        if (!in_array($address, $this->addr)) {
            $this->addr[] = $address;
        }
        return $this;
    }

    /**
     * Sets the "From" email address for the message
     *
     * @param string $from The "From" email address
     *
     * @throws LogicException
     *
     * @return $this
     */
    public function setFrom(string $from)
    {
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new LogicException('Invalid email address (From): ' . $from);
        }
        $this->from = $from;
        return $this;
    }

    /**
     * Sets the subject of the message
     *
     * @param string the subject of the message
     *
     * @return $this
     */
    public function setSubject(string $subject)
    {
        $this->subj = $subject;
        return $this;
    }

    /**
     * Sets the body of the message
     *
     * @param MailBody $body The message body
     *
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Creates a message and sends it
     *
     * @return void
     */
    abstract public function send(): void;

    /**
     * Checks if the minimum required data is available to send the email
     *
     * @throws LogicException
     *
     * @return void
     */
    protected function checkData(): void
    {
        if (!$this->from) {
            throw new LogicException('The "From" address is not specified');
        }
        if (!count($this->addr)) {
            throw new LogicException('The "To" address is not specified');
        }
        if (empty($this->subj)) {
            throw new LogicException('The subject of the message is not specified');
        }
        if (!$this->body) {
            throw new LogicException('The body of the message is not specified');
        }
    }
}
