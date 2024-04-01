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
 * This file contains an class to implement sending emails using the PHPMailer library
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Mail;

use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is for sending emails using the PHPMailer library.
 */
class MailerPhpMailer extends Mailer
{
    /**
     * Creates a message and sends it
     *
     * @throws LogicException
     * @throws RuntimeException
     *
     * @return void
     */
    public function send(): void
    {
        $this->checkData();
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mailer->setFrom($this->from);
            foreach ($this->addr as $to) {
                $mailer->addAddress($to);
            }
            $mailer->Subject = $this->subj;
            $mailer->XMailer = null;
            if (is_null($this->body->html)) {
                $mailer->Body = implode("\r\n", $this->body->text);
            } else {
                $mailer->isHTML();
                $mailer->Body = implode("\r\n", $this->body->html);
                if (!is_null($this->body->text)) {
                    $mailer->AltBody = implode("\r\n", $this->body->text);
                }
            }
            $method = $this->params['method'] ?? 'mail';
            if ($method === 'smtp') {
                $mailer->isSMTP();
                $mailer->Host = $this->params['host'];
                $mailer->Port = $this->params['port'];
                switch ($this->params['encryption'] ?? 'ssl') {
                    case 'ssl':
                        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        break;
                    case 'starttls':
                        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        break;
                    case 'none':
                        $mailer->SMTPAutoTLS = false;
                        break;
                    default:
                        $mailer->SMTPAutoTLS = true;
                        break;
                }
                $username = $this->params['username'] ?? null;
                if (!empty($username)) {
                    $mailer->SMTPAuth = true;
                    $mailer->Username = $username;
                    $mailer->Password = $this->params['password'] ?? '';
                }
                //$mailer->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER; // for testing purposes
            } elseif ($method === 'mail') {
                $mailer->isMail();
            } else {
                throw new LogicException('Unsupported sending method');
            }
            if ($this->params['novalidate-cert'] ?? false) {
                $mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ]
                ];
            }
            $mailer->send();
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Checks if the minimum required data is available to send the email
     *
     * @throws LogicException
     *
     * @return void
     */
    protected function checkData(): void
    {
        parent::checkData();
        if (isset($this->params['method']) && $this->params['method'] === 'smtp') {
            if (empty($this->params['host'])) {
                throw new LogicException('The SMTP server host is not specified');
            }
            if (!is_int($this->params['port']) || $this->params['port'] <= 0) {
                throw new LogicException('The SMTP server port is not specified');
            }
        }
    }
}
