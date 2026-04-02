<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2026 Aleksey Andreev (liuch)
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
 * This file contains the class FetchReportsHandler
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\Core;
use Liuch\DmarcSrg\Mail\MailBoxes;
use Liuch\DmarcSrg\Report\ReportFetcher;
use Liuch\DmarcSrg\Sources\Source;
use Liuch\DmarcSrg\Sources\MailboxSource;
use Liuch\DmarcSrg\Sources\DirectorySource;
use Liuch\DmarcSrg\Sources\RemoteFilesystemSource;
use Liuch\DmarcSrg\Directories\DirectoryList;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\ValidationException;
use Liuch\DmarcSrg\RemoteFilesystems\RemoteFilesystemList;
use Liuch\DmarcSrg\Collections\ReportSourceCollection;

/**
 * This class is designed to fetch report files from multiple report sources and store them to the database.
 */
class FetchReportsHandler
{
    private $results = [];

    public function __construct(
        private Core $core
    ) {
    }

    /**
     * Receives reports from multiple sources
     *
     * @param string     $type Type of the report source to fetch reports from
     * @param array|null $ids  Source Comma-separated list of the source ids
     *
     * @throws ValidationException
     *
     * @return void
     */
    public function handle(string $type, ?array $ids = null): void
    {
        $sourceCollection = $this->getSourceCollectionByType($type);
        if (count($sourceCollection) == 0) {
            throw new ValidationException('There are no configured report sources of this type');
        }

        $sourceIteratorList = [];
        if (is_null($ids)) {
            foreach ($sourceCollection as $source) {
                $sourceIteratorList[] = $this->sourceToIterator($source, $type);
            }
        } else {
            $handled = [];
            foreach ($ids as $id) {
                switch (gettype($id)) {
                    case 'integer':
                        $sourceId = $id;
                        break;
                    case 'string':
                        $sourceId = ctype_digit($id) ? intval($id) : 0;
                        break;
                    default:
                        $sourceId = 0;
                        break;
                }
                if (!in_array($sourceId, $handled, true)) {
                    if (!$sourceCollection->has($sourceId -1)) {
                        throw new ValidationException('Incorrect report source id: ' . $id);
                    }
                    $source = $sourceCollection->get($sourceId - 1);
                    $sourceIteratorList[] = $this->sourceToIterator($source, $type);
                    $handled[] = $sourceId;
                }
            }

            if (count($sourceIteratorList) === 0) {
                throw new ValidationException('Incorrect source list');
            }
        }

        $results = [];
        foreach ($sourceIteratorList as $sourceIterator) {
            $sourceResult = (new ReportFetcher($sourceIterator))->fetch();
            foreach ($sourceResult as $result) {
                $results[] = $result;
            }
        }
        $this->results = $results;
    }

    public function getSummaryResult(): array
    {
        return ReportFetcher::makeSummaryResult($this->results);
    }

    /**
     * Returns a list of incoming DMARC report sources by source type
     *
     * @param string $type
     *
     * @throws ValidationException
     *
     * @return ReportSourceCollection
     */
    private function getSourceCollectionByType(string $sourceType): ReportSourceCollection
    {
        switch ($sourceType) {
            case 'mailbox':
                $this->core->checkDependencies('imap-engine|imap,xml,zip');
                return new MailBoxes();
            case 'directory':
                $this->core->checkDependencies('xml,zip');
                return new DirectoryList();
            case 'remotefs':
                $this->core->checkDependencies('flyfs,xml,zip');
                return new RemoteFilesystemList(true);
        }
        throw new ValidationException('Incorrect source type parameter: ' . $sourceType);
    }

    /**
     * Returns a source iterator
     *
     * @param Object $source
     * @param string $sourceType
     *
     * @return Source
     */
    private function sourceToIterator(Object $source, string $sourceType): Source
    {
        switch ($sourceType) {
            case 'mailbox':
                return new MailboxSource($source);
            case 'directory':
                return new DirectorySource($source);
            case 'remotefs':
                return new RemoteFilesystemSource($source);
        }
        throw new LogicException("Invalid source type identifier: {$sourceType}");
    }
}
