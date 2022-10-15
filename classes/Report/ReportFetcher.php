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
 *
 * =========================
 *
 * This file contains the class ReportFetcher
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\Report;

use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Report\Report;
use Liuch\DmarcSrg\Sources\Source;
use Liuch\DmarcSrg\ReportLog\ReportLogItem;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is designed to fetch report files from report sources and store them to the database.
 */
class ReportFetcher
{
    private $source = null;

    /**
     * It's the constructor of the class.
     *
     * @param Source $sou Source for fetching report files.
     *
     * @return void
     */
    public function __construct($sou)
    {
        $this->source = $sou;
    }

    /**
     * Retrieves report files from the source and stores them in the database
     * taking into account the limits from the configuration file.
     *
     * @return array Array of results.
     */
    public function fetch(): array
    {
        global $fetcher;

        try {
            $this->source->rewind();
        } catch (RuntimeException $e) {
            return [[ 'source_error' => $e->getMessage() ]];
        }

        $limit = 0;
        $stype = $this->source->type();
        switch ($stype) {
            case Source::SOURCE_MAILBOX:
                $limit = $fetcher['mailboxes']['messages_maximum'] ?? 0;
                break;
            case Source::SOURCE_DIRECTORY:
                $limit = $fetcher['directories']['files_maximum'] ?? 0;
                break;
        }
        $limit = intval($limit);

        $results = [];
        while ($this->source->valid()) {
            $result  = null;
            $fname   = null;
            $report  = null;
            $success = false;
            $err_msg = null;

            // Extracting and saving reports
            try {
                $rfile   = $this->source->current();
                $fname   = $rfile->filename();
                $report  = Report::fromXmlFile($rfile->datastream());
                $result  = $report->save($fname);
                $success = true;
            } catch (RuntimeException $e) {
                $err_msg = $e->getMessage();
                $result  = ErrorHandler::exceptionResult($e);
            }
            unset($rfile);

            // Post processing
            try {
                if ($success) {
                    $this->source->accepted();
                } else {
                    $this->source->rejected();
                }
            } catch (RuntimeException $e) {
                $err_msg = $e->getMessage();
                $result['post_processing_message'] = $err_msg;
            }

            // Adding a record to the log.
            if (!$err_msg) {
                $log = ReportLogItem::success($stype, $report, $fname, null)->save();
            } else {
                $log = ReportLogItem::failed($stype, $report, $fname, $err_msg)->save();
                if ($this->source->type() === Source::SOURCE_MAILBOX) {
                    $msg = $this->source->mailMessage();
                    $ov = $msg->overview();
                    if ($ov) {
                        if (property_exists($ov, 'from')) {
                            $result['emailed_from'] = $ov->from;
                        }
                        if (property_exists($ov, 'date')) {
                            $result['emailed_date'] = $ov->date;
                        }
                    }
                }
                if ($report) {
                    $rd = $report->get();
                    if (isset($rd['external_id'])) {
                        $result['report_id'] = $rd['external_id'];
                    }
                }
            }
            unset($report);

            // Adding result to the results array.
            $results[] = $result;

            // Checking the fetcher limits
            if ($limit > 0) {
                if (--$limit === 0) {
                    break;
                }
            }

            $this->source->next();
        }
        return $results;
    }

    /**
     * Generates the final result based on the results of loading individual report files.
     *
     * @param array $results Array with results of loading report files.
     *
     * @return array Array of the final result to be sent to the client.
     */
    public static function makeSummaryResult(array $results): array
    {
        $reps    = [];
        $others  = [];
        $r_count = 0;
        $loaded  = 0;
        foreach ($results as &$r) {
            if (isset($r['source_error'])) {
                $others[] = $r['source_error'];
            } else {
                $reps[] = $r;
                ++$r_count;
                if (!isset($r['error_code']) || $r['error_code'] === 0) {
                    ++$loaded;
                }
                if (isset($r['post_processing_message'])) {
                    $others[] = $r['post_processing_message'];
                }
            }
        }
        unset($r);

        $result  = null;
        $o_count = count($others);
        if ($r_count + $o_count === 1) {
            if ($r_count === 1) {
                $result = $reps[0];
            } else {
                $result = [
                    'error_code' => -1,
                    'message'    => $others[0]
                ];
            }
        } else {
            $err_code = null;
            $message  = null;
            if ($loaded === $r_count) {
                $err_code = 0;
                if ($r_count > 0) {
                    $message = strval($r_count) . ' report files have been loaded successfully';
                } elseif ($o_count === 0) {
                    $message = 'There are no report files to load';
                } else {
                    $err_code = -1;
                }
            } else {
                $err_code = -1;
                if ($loaded > 0) {
                    $message = "Only {$loaded} of the {$r_count} report files have been loaded";
                } else {
                    $message = "None of the {$r_count} report files has been loaded";
                }
            }
            $result['error_code'] = $err_code;
            $result['message'] = $message;
            if ($r_count > 0) {
                $result['results'] = $reps;
            }
            if ($o_count > 0) {
                $result['other_errors'] = $others;
            }
        }
        return $result;
    }
}
