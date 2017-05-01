<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-27
 * Time: 16:06
 */

namespace inhere\gearman;

/**
 * Class JobInterface
 * @package inhere\gearman
 */
interface JobInterface
{
    /**
     * do the job
     * @param string $workload
     * @param WorkerManager $manger
     * @param \GearmanJob $job
     * @return mixed
     */
    public function run($workload, WorkerManager $manger, \GearmanJob $job);
}