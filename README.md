# Queue Manager Bundle

A bundle for running background jobs in [Symfony](symfony.com).

[![Latest Stable Version](https://poser.pugx.org/mcfedr/queue-manager-bundle/v/stable.png)](https://packagist.org/packages/mcfedr/queue-manager-bundle)
[![License](https://poser.pugx.org/mcfedr/queue-manager-bundle/license.png)](https://packagist.org/packages/mcfedr/queue-manager-bundle)
[![Build Status](https://travis-ci.org/mcfedr/queue-manager-bundle.svg?branch=master)](https://travis-ci.org/mcfedr/queue-manager-bundle)

This bundle provides a consistent queue interface, with plugable 'drivers' that can schedule jobs using a number of
different queue types.

- [Beanstalkd](https://packagist.org/packages/mcfedr/beanstalk-queue-driver-bundle)
- [Resque](https://packagist.org/packages/mcfedr/resque-queue-driver-bundle) (Redis)
- [Amazon SQS](https://packagist.org/packages/mcfedr/sqs-queue-driver-bundle)

There are also a number of 'helper' plugins

- [Doctrine Delay Queue](https://packagist.org/packages/mcfedr/doctrine-delay-queue-driver-bundle)
  
  This plugins can schedule jobs far in advance and move them into a real time queue when they should be run. Use in
  combination with SQS or Beanstalkd which don't support scheduling jobs
   
- [Perioidic Jobs](https://packagist.org/packages/mcfedr/periodic-queue-driver-bundle)

  Automatically schedule a jobs to run every hour/day/week or other period. Randomizes the actual time to keep an even
  server load.

## Overview

A job is a Symfony service that implements the `Worker` interface. This has a single method `execute(array $arguments)`.
The name of the job is the service name.

You add jobs to the queue by calling `$container->get("mcfedr_queue_manager.registry")->put($name, $arguments)`.

Check the documentation of the driver you are using as to how to run the daemon process(es).

## Install

### Composer

    composer require mcfedr/queue-manager-bundle

### AppKernel

Include the bundle in your AppKernel

    public function registerBundles()
    {
        $bundles = [
            ...
            new Mcfedr\QueueManagerBundle\McfedrQueueManagerBundle(),

You will also need to including your driver here.

## Config

You must configure one (or more) drivers to use. Generally you will have just one and call it 'default'

This is an example config if you have installed [`mcfedr/resque-queue-driver-bundle`](https://github.com/mcfedr/resque-queue-driver-bundle)

    mcfedr_queue_manager:
        managers:
            default:
                driver: resque
                options:
                    host: 127.0.0.1
                    port: 6379
                    default_queue: queue

Check the driver docs on how to configure it.

### Additional options

These are the defaults for a number of other options

```yaml
mcfedr_queue_manager:
    retry_limit: 3
    sleep_seconds: 5
    report_memory: false
    doctrine_reset: true
    swift_mailer_batch_size: 10
```

| Option | Means |
|--------|-------|
| `retry_limit` | The number of times a job will be retried when it fails, unless it throws `UnrecoverableJobExceptionInterface` |
| `sleep_seconds` | When a queue doesnt have any jobs it will wait this long before checking again |
| `report_memory` | Enable a listener that reports current memory usage between each job, useful for debugging leaks |
| `doctrine_reset` | This listener will reset doctrine connect between jobs. Be careful with your memory usage if disabled. | 
| `swift_mailer_batch_size` | Listener to clear the swift mailer queue every X jobs. Set to -1 to disable. |

## Usage

You can access the `QueueManagerRegistry` for simple access to your queue.
Just inject `"mcfedr_queue_manager.registry"` and call `put` to add new jobs to the queue.

Also, each manager will be a service you can access with the name `"mcfedr_queue_manager.$name"`.
It implements the `QueueManager` interface, where you can call just 2 simple methods.

    /**
     * Put a new job on a queue
     *
     * @param string $name The service name of the worker that implements {@link \Mcfedr\QueueManagerBundle\Queue\Worker}
     * @param array $arguments Arguments to pass to execute - must be json serializable
     * @param array $options Options for creating the job - these depend on the driver used
     * @return Job
     */
    public function put($name, array $arguments = [], array $options = []);
    
    /**
     * Remove a job, you should call this to cancel a job
     *
     * @param $job
     * @throws WrongJobException
     * @throws NoSuchJobException
     */
    public function delete(Job $job);

## Jobs

Jobs to run are Symfony services that implement `Mcfedr\QueueManagerBundle\Queue\Worker`
There is one method, that is called with the arguments you passed to `QueueManager::put`

    /**
     * Called to start the queued task
     *
     * @param array $arguments
     * @throws \Exception
     */
    public function execute(array $arguments);

If your job throws an exception it will be retried (assuming the driver supports retrying),
unless the exception thrown is an instance of `UnrecoverableJobExceptionInterface`.

## Events

A number of events are triggered during the running of jobs

| Name | Event Object |
|------|--------------|
| mcfedr_queue_manager.job_start | `StartJobEvent` |
| mcfedr_queue_manager.job_finished | `FinishedJobEvent` | 
| mcfedr_queue_manager.job_failed | `FailedJobEvent` |

## Creating your own driver

Firstly a driver needs to implement a `QueueManager`. This should put tasks into queues.

The options argument can be used to accept any extra parameters specific to your implementation.
For example, this might include a `delay` or a `priority` if you support that.

You also need to create a `Job` class, many drivers can just extend `AbstractJob` but you can add any extra data you need.

### Creating a runner

Many drivers can use the `RunnerCommand` as a base, implementing the `getJob` method.

Other queue servers have their own runners, in which case you need to write the code such that the correct worker is called.
The service `mcfedr_queue_manager.job_executor` can help with this.
