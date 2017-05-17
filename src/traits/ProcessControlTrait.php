<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-28
 * Time: 17:03
 */

namespace inhere\gearman\traits;

use inhere\gearman\Helper;

/**
 * Trait ProcessControlTrait
 * @package inhere\gearman\traits
 *
 * property bool $waitForSignal
 */
trait ProcessControlTrait
{
//////////////////////////////////////////////////////////////////////
/// process control method
//////////////////////////////////////////////////////////////////////

    /**
     * Do shutdown Manager
     * @param  int $pid Master Pid
     * @param  boolean $quit Quit, When stop success?
     */
    protected function stopMaster($pid, $quit = true)
    {
        $this->stdout("Stop the manager(PID:$pid)");

        // do stop
        // 向主进程发送此信号(SIGTERM)服务器将安全终止；也可在PHP代码中调用`$server->shutdown()` 完成此操作
        if (!$this->killProcess($pid, SIGTERM)) {
            $this->stdout("Stop the manager process(PID:$pid) failed!");
        }

        $startTime = time();
        $timeout = 30;
        $this->stdout("Stopping .", false);

        // wait exit
        while (true) {
            if (!$this->isRunning($pid)) {
                break;
            }

            if (time() - $startTime > $timeout) {
                $this->stdout("Stop the manager process(PID:$pid) failed(timeout)!", true, -2);
                break;
            }

            $this->stdout('.', false);
            sleep(1);
        }

        // stop success
        $this->stdout("\nThe manager stopped.\n");

        if ($quit) {
            $this->quit();
        }

        // clear file info
        clearstatcache();

        $this->stdout("Begin restart manager ...");
    }

    /**
     * Daemon, detach and run in the background
     */
    protected function runAsDaemon()
    {
        $pid = pcntl_fork();

        if ($pid > 0) {// at parent
            // disable trigger stop event in the __destruct()
            $this->isMaster = false;
            $this->clear();
            $this->quit();
        }

        $this->pid = getmypid();
        posix_setsid();

        return true;
    }

    /**
     * check process exist
     * @param $pid
     * @return bool
     */
    public function isRunning($pid)
    {
        return ($pid > 0) && @posix_kill($pid, 0);
    }

    /**
     * setProcessTitle
     * @param $title
     */
    public function setProcessTitle($title)
    {
        if (!Helper::isMac()) {
            cli_set_process_title($title);
        }
    }

    /**
     * Registers the process signal listeners
     * @param bool $isMaster
     */
    protected function registerSignals($isMaster = true)
    {
        // ignore SIGPIPE
        pcntl_signal(SIGPIPE, SIG_IGN, false);

        if ($isMaster) {
            $this->log('Registering signal handlers for master process', self::LOG_DEBUG);

            pcntl_signal(SIGTERM, [$this, 'signalHandler'], false);
            pcntl_signal(SIGINT, [$this, 'signalHandler'], false);
            pcntl_signal(SIGUSR1, [$this, 'signalHandler'], false);
            pcntl_signal(SIGUSR2, [$this, 'signalHandler'], false);
            pcntl_signal(SIGHUP, [$this, 'signalHandler'], false);
        } else {
            $this->log("Registering signal handlers for current worker process", self::LOG_DEBUG);

            if (!pcntl_signal(SIGTERM, [$this, 'signalHandler'], false)) {
                $this->quit(-170);
            }
        }
    }

    /**
     * dispatchSignal
     */
    protected function dispatchSignal()
    {
        // receive and dispatch sig
        pcntl_signal_dispatch();
    }

    /**
     * Handles signals
     * @param int $sigNo
     */
    public function signalHandler($sigNo)
    {
        if ($this->isMaster) {
            static $stopCount = 0;

            switch ($sigNo) {
                case SIGINT: // Ctrl + C
                case SIGTERM:
                    $sigText = $sigNo === SIGINT ? 'SIGINT(Ctrl+C)' : 'SIGTERM';
                    $this->log("Shutting down($sigText)...", self::LOG_PROC_INFO);
                    $this->stopWork();
                    $stopCount++;

                    if ($stopCount < 5) {
                        $this->stopWorkers();
                    } else {
                        $this->log("Stop workers failed by($sigText), will force kill workers by(SIGKILL)", self::LOG_PROC_INFO);
                        $this->stopWorkers(SIGKILL);
                    }
                    break;
                case SIGHUP:
                    $this->log('Restarting workers(SIGHUP)', self::LOG_PROC_INFO);
                    $this->openLogFile();
                    $this->stopWorkers();
                    break;
                case SIGUSR1: // reload workers and reload handlers
                    $this->log('Reloading workers and handlers(SIGUSR1)', self::LOG_PROC_INFO);
                    $this->stopWork();
                    $this->start();
                    break;
                case SIGUSR2:
                    break;
                default:
                    // handle all other signals
            }

        } else {
            $this->stopWork();
            $this->log("Received 'stopWork' signal(SIGTERM), will be exiting.", self::LOG_PROC_INFO);
        }
    }


    /**
     * kill process by PID
     * @param int $pid
     * @param int $signal
     * @param int $timeout
     * @return bool
     */
    public function killProcess($pid, $signal = SIGTERM, $timeout = 3)
    {
        return $this->sendSignal($pid, $signal, $timeout);
    }

    /**
     * send signal to the process
     * @param int $pid
     * @param int $signal
     * @param int $timeout
     * @return bool
     */
    public function sendSignal($pid, $signal, $timeout = 0)
    {
        if ($pid <= 0) {
            return false;
        }

        // do kill
        if ($ret = posix_kill($pid, $signal)) {
            return true;
        }

        // don't want retry
        if ($timeout <= 0) {
            return $ret;
        }

        // failed, try again ...

        $timeout = $timeout > 0 && $timeout < 10 ? $timeout : 3;
        $startTime = time();

        // retry stop if not stopped.
        while (true) {
            // success
            if (!$isRunning = @posix_kill($pid, 0)) {
                break;
            }

            // have been timeout
            if ((time() - $startTime) >= $timeout) {
                return false;
            }

            // try again kill
            $ret = posix_kill($pid, $signal);

            usleep(50000);
        }

        return $ret;
    }

    /**
     * @param string $user
     * @param string $group
     */
    protected function changeScriptOwner($user, $group = '')
    {
        $uInfo = posix_getpwnam($user);

        if (!$uInfo || !isset($uInfo['uid'])) {
            $this->showHelp("User ({$user}) not found.");
        }

        $uid = (int)$uInfo['uid'];

        // Get gid.
        if ($group) {
            if (!$gInfo = posix_getgrnam($group)) {
                $this->showHelp("Group {$group} not exists", -300);
            }

            $gid = (int)$gInfo['gid'];
        } else {
            $gid = (int)$uInfo['gid'];
        }

        if (!posix_initgroups($uInfo['name'], $gid)) {
            $this->showHelp("The user [{$user}] is not in the user group ID [GID:{$gid}]", -300);
        }

        posix_setgid($gid);

        if (posix_geteuid() !== $gid) {
            $this->showHelp("Unable to change group to {$user} (UID: {$gid}).", -300);
        }

        posix_setuid($uid);

        if (posix_geteuid() !== $uid) {
            $this->showHelp("Unable to change user to {$user} (UID: {$uid}).", -300);
        }

        $this->log("User set to {$user}", self::LOG_PROC_INFO);
    }
}
