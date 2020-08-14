<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Carbon\Carbon;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Persistence\ManagerRegistry;
use Mcfedr\QueueManagerBundle\Entity\DoctrineDelayJob;
use Mcfedr\QueueManagerBundle\Manager\DoctrineDelayTrait;
use Mcfedr\QueueManagerBundle\Queue\DoctrineDelayWorkerJob;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DoctrineDelayRunnerCommand extends RunnerCommand
{
    use DoctrineDelayTrait;

    /**
     * @var int
     */
    private $batchSize = 20;

    private $reverse = false;

    public function __construct(ManagerRegistry $doctrine, string $name, array $options, JobExecutor $jobExecutor, ?LoggerInterface $logger = null)
    {
        parent::__construct($name, $options, $jobExecutor, $logger);
        $this->doctrine = $doctrine;
        $this->setOptions($options);
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of messages to fetch at once', 20)
            ->addOption('reverse', null, InputOption::VALUE_NONE, 'Fetch jobs from the database in reverse order (newest first)')
        ;
    }

    protected function getJobs(): ?JobBatch
    {
        $now = new Carbon(null, new \DateTimeZone('UTC'));
        $em = $this->getEntityManager();

        try {
            $em->getConnection()->beginTransaction();
            $repo = $em->getRepository(DoctrineDelayJob::class);
            $orderDir = $this->reverse ? 'DESC' : 'ASC';

            $em->getConnection()->executeUpdate(
                "UPDATE DoctrineDelayJob job SET job.processing = TRUE WHERE job.time < :now ORDER BY job.time {$orderDir} LIMIT :limit",
                [
                    'now' => $now,
                    'limit' => $this->batchSize,
                ],
                [
                    'now' => Type::getType(Type::DATETIME),
                    'limit' => Type::getType(Type::INTEGER),
                ]
            );

            $jobs = $repo->createQueryBuilder('job')
                ->andWhere('job.processing = true')
                ->getQuery()
                ->getResult()
            ;

            $repo->createQueryBuilder('job')
                ->delete()
                ->andWhere('job.processing = true')
                ->getQuery()
                ->execute()
            ;

            $em->getConnection()->commit();

            if (\count($jobs)) {
                return new JobBatch(array_map(function (DoctrineDelayJob $job) {
                    return new DoctrineDelayWorkerJob($job);
                }, $jobs));
            }
        } catch (DriverException $e) {
            if (1213 === $e->getErrorCode()) { //Deadlock found when trying to get lock;
                $em->rollback();

                // Just return an empty batch so that the runner sleeps
                if ($this->logger) {
                    $this->logger->warning('Deadlock trying to lock table.', [
                        'exception' => $e,
                    ]);
                }
            }
        }

        return null;
    }

    protected function finishJobs(JobBatch $batch): void
    {
        $retryJobs = $batch->getRetries();
        if (\count($retryJobs)) {
            $em = $this->getEntityManager();

            /** @var DoctrineDelayWorkerJob $job */
            foreach ($retryJobs as $job) {
                $oldJob = $job->getDelayJob();
                $retryCount = $oldJob->getRetryCount() + 1;
                $newJob = new DoctrineDelayJob(
                    $oldJob->getName(),
                    $oldJob->getArguments(),
                    $oldJob->getOptions(),
                    $oldJob->getManager(),
                    new Carbon(sprintf('+%d seconds', $this->getRetryDelaySeconds($retryCount))),
                    $retryCount
                );
                $em->persist($newJob);
            }

            $em->flush();
        }

        $remainingJobs = $batch->getJobs();
        if (\count($remainingJobs)) {
            $em = $this->getEntityManager();

            /** @var DoctrineDelayWorkerJob $job */
            foreach ($remainingJobs as $job) {
                $oldJob = $job->getDelayJob();
                $newJob = new DoctrineDelayJob(
                    $oldJob->getName(),
                    $oldJob->getArguments(),
                    $oldJob->getOptions(),
                    $oldJob->getManager(),
                    new Carbon(),
                    $oldJob->getRetryCount()
                );
                $em->persist($newJob);
            }

            $em->flush();
        }
    }

    protected function handleInput(InputInterface $input): void
    {
        if (($batch = $input->getOption('batch-size'))) {
            $this->batchSize = (int) $batch;
        }
        $this->reverse = $input->getOption('reverse');
    }
}
