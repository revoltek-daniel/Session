<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Session\Business\Handler;

use Spryker\Shared\NewRelic\NewRelicApiInterface;

class SessionHandlerFile implements \SessionHandlerInterface
{

    const METRIC_SESSION_DELETE_TIME = 'File/Session_delete_time';
    const METRIC_SESSION_WRITE_TIME = 'File/Session_write_time';
    const METRIC_SESSION_READ_TIME = 'File/Session_read_time';

    /**
     * @var string
     */
    protected $keyPrefix = 'session:';

    /**
     * @var int
     */
    protected $lifetime;

    /**
     * @var string
     */
    protected $savePath;

    /**
     * @var \Spryker\Shared\NewRelic\NewRelicApiInterface
     */
    protected $newRelicApi;

    /**
     * @param string $savePath
     * @param int $lifetime
     * @param \Spryker\Shared\NewRelic\NewRelicApiInterface $newRelicApi
     */
    public function __construct($savePath, $lifetime, NewRelicApiInterface $newRelicApi)
    {
        $this->savePath = $savePath;
        $this->lifetime = $lifetime;
        $this->newRelicApi = $newRelicApi;
    }

    /**
     * @param string $savePath
     * @param string $sessionName
     *
     * @return bool
     */
    public function open($savePath, $sessionName)
    {
        $this->savePath = $savePath;
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0775, true);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * @param string $sessionId
     *
     * @return string|null
     */
    public function read($sessionId)
    {
        $startTime = microtime(true);
        $key = $this->keyPrefix . $sessionId;
        $sessionFile = $this->savePath . DIRECTORY_SEPARATOR . $key;
        if (!file_exists($sessionFile)) {
            return null;
        }

        $content = file_get_contents($sessionFile);

        $this->newRelicApi->addCustomMetric(self::METRIC_SESSION_READ_TIME, microtime(true) - $startTime);

        return $content;
    }

    /**
     * @param string $sessionId
     * @param string $sessionData
     *
     * @return bool
     */
    public function write($sessionId, $sessionData)
    {
        $key = $this->keyPrefix . $sessionId;

        if (empty($sessionData)) {
            return false;
        }

        $startTime = microtime(true);
        $result = file_put_contents($this->savePath . DIRECTORY_SEPARATOR . $key, $sessionData);
        $this->newRelicApi->addCustomMetric(self::METRIC_SESSION_WRITE_TIME, microtime(true) - $startTime);

        return $result > 0;
    }

    /**
     * @param int|string $sessionId
     *
     * @return bool
     */
    public function destroy($sessionId)
    {
        $key = $this->keyPrefix . $sessionId;
        $file = $this->savePath . DIRECTORY_SEPARATOR . $key;
        if (file_exists($file)) {
            $startTime = microtime(true);
            unlink($file);
            $this->newRelicApi->addCustomMetric(self::METRIC_SESSION_DELETE_TIME, microtime(true) - $startTime);

            return true;
        }

        return false;
    }

    /**
     * @param int $maxLifetime
     *
     * @return bool
     */
    public function gc($maxLifetime)
    {
        foreach (glob($this->savePath . DIRECTORY_SEPARATOR . $this->keyPrefix . '*') as $file) {
            if (filemtime($file) + $maxLifetime < time() && file_exists($file)) {
                unlink($file);
            }
        }

        return true;
    }

}
