<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Manager;

use Carbon\Carbon;
use Doctrine\Persistence\ManagerRegistry;
use Mcfedr\QueueManagerBundle\Entity\DoctrineDelayJob;
use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;

class DoctrineDelayQueueManager implements QueueManager
{
    use DoctrineDelayTrait;

    private QueueManagerRegistry $queueManagerRegistry;

    public function __construct(QueueManagerRegistry $queueManagerRegistry, ManagerRegistry $doctrine, array $options)
    {
        $this->queueManagerRegistry = $queueManagerRegistry;
        $this->doctrine = $doctrine;
        $this->setOptions($options);
    }

    /**
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public function put(string $name, array $arguments = [], array $options = []): Job
    {
        if (\array_key_exists('manager_options', $options)) {
            $jobOptions = array_merge($this->defaultManagerOptions, $options['manager_options']);
        } else {
            $jobOptions = array_merge($this->defaultManagerOptions, array_diff_key($options, ['manager' => 1, 'time' => 1, 'delay' => 1]));
        }

        if (\array_key_exists('manager', $options)) {
            $jobManager = $options['manager'];
        } else {
            $jobManager = $this->defaultManager;
        }

        if (isset($options['time'])) {
            /** @var \DateTime $jobTime */
            $jobTime = $options['time'];
            if ('UTC' !== $jobTime->getTimezone()->getName()) {
                $jobTime = clone $jobTime;
                $jobTime->setTimezone(new \DateTimeZone('UTC'));
            }
        } elseif (isset($options['delay'])) {
            $jobTime = new Carbon("+{$options['delay']} seconds", new \DateTimeZone('UTC'));
        }

        if (!isset($jobTime) || $jobTime < new \DateTime('+30 seconds', new \DateTimeZone('UTC'))) {
            return $this->queueManagerRegistry->put($name, $arguments, $jobOptions, $jobManager);
        }

        $job = new DoctrineDelayJob($name, $arguments, $jobOptions, $jobManager, $jobTime);

        $em = $this->getEntityManager();
        $em->persist($job);
        $em->flush($job);

        return $job;
    }

    /**
     * @throws NoSuchJobException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @throws WrongJobException
     */
    public function delete(Job $job): void
    {
        if (!$job instanceof DoctrineDelayJob) {
            throw new WrongJobException('Doctrine delay queue manager can only delete doctrine delay jobs');
        }

        $em = $this->getEntityManager();
        if (!$em->contains($job)) {
            if (!$job->getId()) {
                throw new NoSuchJobException('Doctrine delay queue manager cannot delete a job that hasnt been persisted');
            }

            $job = $em->getReference(DoctrineDelayJob::class, $job->getId());
        }

        $em->remove($job);
        $em->flush($job);
    }
}
