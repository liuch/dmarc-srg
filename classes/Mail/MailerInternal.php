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
 * This file contains an class to implement sending emails using the standard PHP mail() function.
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
 * This class is for sending emails using the standard PHP mail() function.
 */
class MailerInternal extends Mailer
{
    /**
     * Creates a message and sends it
     *
     * @throws RuntimeException
     *
     * @return void
     */
    public function send(): void
    {
        if (isset($this->params['method']) && $this->params['method'] !== 'mail') {
            throw new LogicException('Unsupported sending method');
        }
        $this->checkData();
        $headers = [
            'From'         => $this->from,
            'MIME-Version' => '1.0',
            'Content-Type' => $this->body->contentType()
        ];
        $ex = null;
        $res = null;
        try {
            $res = mail(
                implode(',', $this->addr),
                mb_encode_mimeheader($this->subj, 'UTF-8'),
                implode("\r\n", $this->body->content()),
                $headers
            );
        } catch (\Exception $e) {
            $ex = $e;
        }
        if (!$res) {
            throw new RuntimeException('Failed to send an email', -1, $ex);
        }
    }
}
