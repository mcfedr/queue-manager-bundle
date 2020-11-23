<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Controller;

use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;
use Mcfedr\QueueManagerBundle\Queue\PubSubJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PubSubController extends AbstractController
{

    /**
     * @var JobExecutor
     */
    private $jobExecutor;

    public function __construct(JobExecutor $jobExecutor)
    {
        $this->jobExecutor = $jobExecutor;
    }

    /**
     * @Route("/pubsub", name="pubsub", methods={"POST"})
     */
    public function pubsub(Request $request)
    {
        $message = json_decode($request->getContent(), true);

        if (
            !$message || !isset($message['message']) || !isset($message['message']['data']) || !($data = base64_decode($message['message']['data'], true)) ||
            !($data = json_decode($data, true)) || !isset($data['name']) || !isset($data['arguments']) || !isset($data['retryCount'])
        ) {
            return new Response();
        }

        try {
            $this->jobExecutor->executeJob(new PubSubJob(
                $data['name'],
                $data['arguments'],
                null,
                $data['retryCount']
            ));
        } catch (UnrecoverableJobExceptionInterface $e) {
            return new Response();
        }

        return new Response();
    }
}
