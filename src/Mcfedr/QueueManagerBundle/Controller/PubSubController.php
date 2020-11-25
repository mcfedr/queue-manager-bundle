<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Controller;

use Google\Auth\AccessToken;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobExceptionInterface;
use Mcfedr\QueueManagerBundle\Model\PubSubData;
use Mcfedr\QueueManagerBundle\Model\PubSubMessage;
use Mcfedr\QueueManagerBundle\Queue\PubSubJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class PubSubController extends AbstractController
{
    public const DEFAULT_AUDIENCE = 'default_audience';
    /**
     * @var JobExecutor
     */
    private $jobExecutor;
    /**
     * @var AccessToken
     */
    private $accessToken;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(JobExecutor $jobExecutor, AccessToken $accessToken, SerializerInterface $serializer)
    {
        $this->jobExecutor = $jobExecutor;
        $this->accessToken = $accessToken;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/pubsub/{queue}", name="pubsub", methods={"POST"})
     */
    public function pubsub(Request $request, string $queue)
    {
        $headers = getallheaders();
        if (!($auth = $request->headers->get('Authorization')) && isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
        }

        if (!$auth) {
            throw new AccessDeniedHttpException('Authorization header not provided.');
        }
        $jwt = str_replace('Bearer ', '', $auth);

        $payload = $this->accessToken->verify($jwt);
        $audience = $this->getParameter("mcfedr_queue_manager.{$queue}.audience");
        if (!$payload || !isset($payload['aud']) || ($payload['aud'] !== $audience && self::DEFAULT_AUDIENCE !== $audience)) {
            throw new AccessDeniedHttpException('Could not verify token!');
        }

        $message = $this->serializer->deserialize($request->getContent(), PubSubMessage::class, 'json')->getMessage();

        if (!$message || !isset($message['data'])) {
            return new Response();
        }

        /** @var PubSubData $data */
        $data = $this->serializer->deserialize(base64_decode($message['data'], true), PubSubData::class, 'json');

        try {
            $this->jobExecutor->executeJob(new PubSubJob(
                $data->getName(),
                $data->getArguments(),
                null,
                $data->getRetryCount()
            ));
        } catch (UnrecoverableJobExceptionInterface $e) {
            return new Response();
        }

        return new Response();
    }
}
