<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Command;

use Mcfedr\QueueManagerBundle\Exception\UnexpectedJobDataException;
use Mcfedr\QueueManagerBundle\Manager\PheanstalkClientTrait;
use Mcfedr\QueueManagerBundle\Queue\BeanstalkJob;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Pheanstalk\Contract\PheanstalkInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class BeanstalkCommand extends RunnerCommand
{
    use PheanstalkClientTrait;

    private PheanstalkInterface $pheanstalk;

    public function __construct(PheanstalkInterface $pheanstalk, string $name, array $options, JobExecutor $jobExecutor, ?LoggerInterface $logger = null)
    {
        parent::__construct($name, $options, $jobExecutor, $logger);
        $this->pheanstalk = $pheanstalk;
        $this->setOptions($options);
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'The queue to watch, can be a comma separated list')
        ;
    }

    protected function handleInput(InputInterface $input): void
    {
        if (($queues = $input->getOption('queue'))) {
            foreach (explode(',', $queues) as $queue) {
                $this->pheanstalk->watch($queue);
            }
        } else {
            $this->pheanstalk->watch($this->defaultQueue);
        }
    }

    protected function getJobs(): ?JobBatch
    {
        $job = $this->pheanstalk->reserve();
        if (!$job) {
            return null;
        }

        $data = json_decode($job->getData(), true);
        if (!\is_array($data) || !isset($data['name']) || !isset($data['arguments']) || !isset($data['retryCount']) || !isset($data['priority']) || !isset($data['ttr'])) {
            $this->pheanstalk->delete($job);

            throw new UnexpectedJobDataException('Beanstalkd message missing data fields name, arguments, retryCount, priority and ttr');
        }

        return new JobBatch([new BeanstalkJob($data['name'], $data['arguments'], $data['priority'], $data['ttr'], $job->getId(), $data['retryCount'], $job)]);
    }

    protected function finishJobs(JobBatch $batch): void
    {
        /** @var BeanstalkJob $job */
        foreach ($batch->getOks() as $job) {
            $this->pheanstalk->delete($job->getJob());
        }

        /** @var BeanstalkJob $job */
        foreach ($batch->getRetries() as $job) {
            $this->pheanstalk->delete($job->getJob());
            $job->incrementRetryCount();
            $this->pheanstalk->put($job->getData(), $job->getPriority(), $job->getRetryCount() * $job->getRetryCount() * 30, $job->getTtr());
        }

        /** @var BeanstalkJob $job */
        foreach ($batch->getFails() as $job) {
            $this->pheanstalk->delete($job->getJob());
        }

        /** @var BeanstalkJob $job */
        foreach ($batch->getJobs() as $job) {
            $this->pheanstalk->release($job->getJob());
        }
    }
}
