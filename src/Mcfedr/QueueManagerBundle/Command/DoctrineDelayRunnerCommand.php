<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Carbon\Carbon;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Types\Type;
use Mcfedr\QueueManagerBundle\Entity\DoctrineDelayJob;
use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Manager\DoctrineDelayTrait;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\WorkerJob;
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

    protected function getJobs(): array
    {
        $now = new Carbon(null, new \DateTimeZone('UTC'));
        $em = $this->getEntityManager();

        try {
            $em->getConnection()->beginTransaction();
            $repo = $em->getRepository(DoctrineDelayJob::class);
            $orderDir = $this->reverse ? 'DESC' : 'ASC';

            $em->getConnection()->executeUpdate(
                "UPDATE DoctrineDelayJob job SET job.processing = TRUE WHERE job.time < :now ORDER BY job.time ${orderDir} LIMIT :limit",
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

            return array_map(function (DoctrineDelayJob $job) {
                return new WorkerJob($job);
            }, $jobs);
        } catch (DriverException $e) {
            if (1213 === $e->getErrorCode()) { //Deadlock found when trying to get lock;
                $em->rollback();

                throw new UnexpectedJobDataException('Deadlock trying to lock table', 0, $e);
            }
        }
    }

    protected function finishJobs(array $okJobs, array $retryJobs, array $failedJobs): void
    {
        if (\count($retryJobs)) {
            $em = $this->getEntityManager();

            /** @var WorkerJob $job */
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
    }

    protected function handleInput(InputInterface $input): void
    {
        if (($batch = $input->getOption('batch-size'))) {
            $this->batchSize = (int) $batch;
        }
        $this->reverse = $input->getOption('reverse');
    }
}
