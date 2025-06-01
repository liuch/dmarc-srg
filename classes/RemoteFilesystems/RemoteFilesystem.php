<?php

/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2023 Aleksey Andreev (liuch)
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
 * This file contains the class RemoteFilesystem
 *
 * @category API
 * @package  DmarcSrg
 * @author   Aleksey Andreev (liuch)
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */

namespace Liuch\DmarcSrg\RemoteFilesystems;

use Liuch\DmarcSrg\ErrorHandler;
use Liuch\DmarcSrg\Exception\LogicException;
use Liuch\DmarcSrg\Exception\RuntimeException;

/**
 * This class is designed to work with remote filesystems which are listed in the configuration file.
 */
class RemoteFilesystem
{
    private $id      = null;
    private $fs      = null;
    private $name    = null;
    private $type    = null;
    private $params  = null;

    /**
     * It's the constructor of the class
     *
     * @param int   $id   Id of the remote filesystem. In fact, it is a serial number in the configuration file.
     * @param array $data An array with the following fields:
     *                    `name` (string)     - Name of the remote filesystem. It is optional.
     *                    `type` (string)     - Type of the remote filesystem. The valid types is `s3`.
     *                    Other fields for the type `s3` (see conf/conf.sample.php for the details):
     *                    `bucket` (string), `path` (string), `key` (string), `secret` (string)
     *                    `token` (string), `profile` (string), `endpoit`, `region` (string)
     *
     * @return void
     */
    public function __construct(int $id, array $data)
    {
        if (isset($data['name']) && gettype($data['name']) !== 'string') {
            throw new LogicException('Remote filesystem name must be either null or a string value');
        }

        $this->id = $id;
        $this->name = $data['name'] ?? ('RFS-' . $id);

        $type = $data['type'] ?? null;
        switch ($type) {
            case 's3':
                $this->params = [];
                foreach ([ 'bucket', 'path' ] as $p) {
                    if (!isset($data[$p])) {
                        throw new LogicException('Missed paramenter ' . $p);
                    }
                    $this->params[$p] = $data[$p];
                }
                // AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_SESSION_TOKEN
                foreach ([ 'key', 'secret', 'token' ] as $p) {
                    if (isset($data[$p])) {
                        if (!isset($this->params['credentials'])) {
                            $this->params['credentials'] = [];
                        }
                        $this->params['credentials'][$p] = $data[$p];
                    }
                }
                // AWS_PROFILE
                foreach ([ 'profile', 'endpoint', 'region' ] as $p) {
                    if (isset($data[$p])) {
                        $this->params[$p] = $data[$p];
                    }
                }
                $this->params['use_path_style_endpoint'] = isset($this->params['endpoint']);
                break;
            default:
                throw new LogicException("Unknown remote filesystem type [{$type}]");
        }
        $this->type = $type;
    }

    /**
     * Returns an array with remote filesystem configuration data.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'location' => "{$this->params['bucket']}::{$this->params['path']}"
        ];
    }

    /**
     * Checks the existence and accessibility of the remote filesystem. Returns the result as an array.
     *
     * @return array
     */
    public function check(): array
    {
        try {
            $cnt = $this->count();
        } catch (\Aws\S3\Exception\S3Exception $e) {
            return ErrorHandler::exceptionResult($e);
        }

        return [
            'error_code' => 0,
            'message'    => 'Successfully',
            'status'     => [
                'files'  => $cnt
            ]
        ];
    }

    /**
     * Returns a value indicating whether the resource is public
     *
     * @return bool
     */
    public function isPublic(): bool
    {
        try {
            $ls = $this->getFilesystem(false)->listContents($this->params['path'], false);
            foreach ($ls as $it) {
                break;
            }
        } catch (\Aws\S3\Exception\S3Exception) {
            return false;
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
        return true;
    }

    /**
     * Returns the total number of files on the remote filesystem.
     *
     * @return int
     */
    public function count(): int
    {
        $cnt = 0;
        try {
            $ls = $this->getFilesystem(true)->listContents($this->params['path'], false);
            foreach ($ls as $it) {
                if ($it->isFile()) {
                    ++$cnt;
                }
            }
        } catch (\Exception $e) {
            throw new RuntimeException("Error accessing remote filesystem {$this->name}", -1, $e);
        }
        return $cnt;
    }

    /**
     * Returns a read-once interable object that contains all files in the directory, not including subdirectories
     *
     * @return object
     */
    public function listFiles(): object
    {
        return $this->getFilesystem(true)->listContents($this->params['path'], false)->filter(
            function ($attr) {
                return $attr->isFile();
            }
        );
    }

    /**
     * Returns file data as a resource
     *
     * @param string $path File path
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        return $this->getFilesystem(true)->readStream($path);
    }

    /**
     * Move a file within the filesystem
     *
     * @param string $sou_path Source file path
     * @param string $des_path Destination file path
     *
     * @return void
     */
    public function move(string $sou_path, string $des_path): void
    {
        $this->getFilesystem(true)->move($sou_path, $des_path);
    }

    /**
     * Deletes a file from the filesystem
     *
     * @param string $path File path
     *
     * @return void
     */
    public function delete(string $path): void
    {
        $this->getFilesystem(true)->delete($path);
    }

    /**
     * Returns an instance of Filesystem
     *
     * @param bool $with_credentials Whether to pass credentials to filesystem adapter
     *
     * @return League\Flysystem\Filesystem|null
     */
    private function getFilesystem(bool $with_credentials)
    {
        $fs = $this->fs;
        if (!$fs || !$with_credentials) {
            switch ($this->type) {
                case 's3':
                    $p = $this->params;
                    if (!$with_credentials) {
                        $p['credentials'] = false;
                    }
                    $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                        new \Aws\S3\S3Client($p),
                        $this->params['bucket']
                    );
                    break;
                default:
                    return null;
            }
            $fs = new \League\Flysystem\Filesystem($adapter);
            if ($with_credentials) {
                $this->fs = $fs;
            }
        }
        return $fs;
    }
}
