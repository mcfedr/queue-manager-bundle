# Queue Manager Bundle

A bundle for running background jobs in [Symfony](symfony.com).

[![Latest Stable Version](https://poser.pugx.org/mcfedr/queue-manager-bundle/v/stable.png)](https://packagist.org/packages/mcfedr/queue-manager-bundle)
[![License](https://poser.pugx.org/mcfedr/queue-manager-bundle/license.png)](https://packagist.org/packages/mcfedr/queue-manager-bundle)
[![Build Status](https://travis-ci.org/mcfedr/queue-manager-bundle.svg?branch=master)](https://travis-ci.org/mcfedr/queue-manager-bundle)

This bundle provides a consistent queue interface, with plugable 'drivers' that can schedule jobs using a number of
different queue types.

- [Beanstalkd](https://beanstalkd.github.io/)
- [Amazon SQS](https://aws.amazon.com/sqs/)

There are also a number of 'helper' plugins

- [Doctrine Delay Queue](https://www.doctrine-project.org/)
  
  This plugins can schedule jobs far in advance and move them into a real time queue when they should be run. Use in
  combination with SQS or Beanstalkd which don't support scheduling jobs
   
- Perioidic Jobs

  Automatically schedule a jobs to run every hour/day/week or other period. Randomizes the actual time to keep an even
  server load.

## Overview

A job is a Symfony service that implements the `Worker` interface. This has a single method `execute(array $arguments)`.
The name of the job is the service name.

You add jobs to the queue by calling `$container->get(QueueManagerRegistry::class)->put($name, $arguments)`.

Check the documentation of the driver you are using as to how to run the daemon process(es).

## Install

### Composer

```bash
composer require mcfedr/queue-manager-bundle
```

### AppKernel

Include the bundle in your AppKernel

```php
    public function registerBundles()
    {
        $bundles = [
            ...
            new Mcfedr\QueueManagerBundle\McfedrQueueManagerBundle(),
```

You will also need to including your driver here.

## Config

You must configure one (or more) drivers to use. Generally you will have just one and call it 'default'

### Beanstalk

#### Usage

The beanstalk runner is a Symfony command. You can runner multiple instances if you need to
handle higher numbers of jobs.

```bash
./bin/console mcfedr:queue:{name}-runner
```

Where `{name}` is what you used in the config. Add `-v` or more to get detailed logs.

#### Config

```yaml
mcfedr_queue_manager:
    managers:
        default:
            driver: beanstalkd
            options:
                host: 127.0.0.1
                port: 11300
                default_queue: mcfedr_queue
```

#### Supported options to `QueueManager::put`

* `queue` - The name of the queue to put the job in
* `priority` - The job priority
* `ttr` - Beanstalk Time to run, the time given for a job to finish before it is repeated
* `time` - A `\DateTime` object of when to schedule this job
* `delay` - Number of seconds from now to schedule this job

### SQS

#### Usage

The sqs runner is a Symfony command. You can runner multiple instances if you need to
handle higher numbers of jobs.

```bash
./bin/console mcfedr:queue:{name}-runner
```

Where `{name}` is what you used in the config. Add `-v` or more to get detailed logs.

#### Config

```yaml
mcfedr_queue_manager:
    managers:
        default:
            driver: sqs
            options:
                default_url: https://sqs.eu-west-1.amazonaws.com/...
                region: eu-west-1
                credentials:
                    key: 'my-access-key-id'
                    secret: 'my-secret-access-key'
                queues:
                    name: https://sqs.eu-west-1.amazonaws.com/...
                    name2: https://sqs.eu-west-1.amazonaws.com/...
```

* `default_url` - Default SQS queue url
* `region` **required** - The region where your queue is
* `credentials` *optional* - [Specify your key and secret](http://docs.aws.amazon.com/aws-sdk-php/v3/guide/guide/credentials.html#using-hard-coded-credentials)
  This is optional because the SDK can pick up your credentials from a [variety of places](http://docs.aws.amazon.com/aws-sdk-php/v3/guide/guide/credentials.html)
* `queues` *optional* - Allows you to setup a mapping of short names for queues, this makes it easier to use multiple queues and keep the config in one place

#### Supported options to `QueueManager::put`

* `url` - A `string` with the url of a queue
* `queue` - A `string` with the name of a queue in the config
* `time` - A `\DateTime` object of when to schedule this job. **Note:** SQS can delay jobs up to 15 minutes 
* `delay` - Number of seconds from now to schedule this job. **Note:** SQS can delay jobs up to 15 minutes
* `visibilityTimeout` - Number of seconds during which Amazon SQS prevents other consumers from receiving and processing the message.

### Periodic

This driver doesn't run jobs, it requires another driver to actually process jobs.

#### Usage

There is no runner daemon for this driver as it just plugs into other drivers. Use it by
`put`ting jobs into this driver with the `period` option.

#### Config

```yaml
mcfedr_queue_manager:
    managers:
        periodic:
            driver: periodic
            options:
                default_manager: delay
                default_manager_options: []
```

This will create a `QueueManager` service named `"mcfedr_queue_manager.periodic"`

* `default_manager` - Default job processor, must support delayed jobs, for example [Doctrine Delay](https://packagist.org/packages/mcfedr/doctrine-delay-queue-driver-bundle)
* `default_manager_options` - Default options to pass to job processor `put`

#### Supported options to `QueueManager::put`

* `period` - The average number of seconds between job runs
* `manager` - Use a different job processor for this job
* `manager_options` - Options to pass to the processors `put` method

### Doctrine Delay

This driver doesn't run jobs, it requires another driver to actually process jobs.

It currently **only** works with MySQL as a native query is required to find jobs in a concurrency safe way.

#### Usage

You should run the daemon for delay in addition to any other daemons you are using.
This runner simply moves jobs from Doctrine into your other job queues. Because its 
not doing much work generally a single instance can cope with a high number of jobs.

```bash
./bin/console mcfedr:queue:{name}-runner
```

Where `{name}` is what you used in the config. Add `-v` or more to get detailed logs.

#### Config

```yaml
mcfedr_queue_manager:
    managers:
        delay:
            driver: doctrine_delay
            options:
                entity_manager: default
                default_manager: default
                default_manager_options: []
```

This will create a `QueueManager` service named `"mcfedr_queue_manager.delay"`

* `entity_manager` - Doctrine entity manager to use
* `default_manager` - Default job processor
* `default_manager_options` - Default options to pass to job processor `put`

#### Supported options to `QueueManager::put`

* `time` - A `\DateTime` object of when to schedule this job
* `delay` - Number of seconds from now to schedule this job
* `manager` - Use a different job processor for this job
* `manager_options` - Options to pass to the processors `put` method

#### Note

If `delay` or `time` option is less then 30 seconds the job will be scheduled for immediate execution

#### Tables

After you have installed you will need to do a schema update so that the table of delayed tasks is created

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

### Doctrine

To avoid memory leaks entity manager is being reset after job execution.

Resetting a non-lazy manager service is deprecated since Symfony 3.2 and will throw an exception in version 4.0.
So if you use Symfony 3.2 or greater you need to install symfony/proxy-manager-bridge to support [Lazy Services](https://symfony.com/doc/current/service_container/lazy_services.html)

    composer require proxy-manager-bridge

## Usage

You can access the `QueueManagerRegistry` for simple access to your queue.
Just inject `QueueManagerRegistry::class` and call `put` to add new jobs to the queue.

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

Workers should be tagged with `mcfedr_queue_manager.worker`, if you are using autowiring this will
happen automatically.

By default the job name is the class, but you can also add tags with specific ids, e.g.

```yaml
Worker:
  tags:
  - { name: 'mcfedr_queue_manager.worker', id: 'test_worker' }
```

Now you can schedule this job with both

```php
$queueManager->put(Worker::class, ...)
$queueManager->put('test_worker', ...)
```

## Events

A number of events are triggered during the running of jobs

| Name | Event Object |
|------|--------------|
| mcfedr_queue_manager.job_start | `StartJobEvent` |
| mcfedr_queue_manager.job_finished | `FinishedJobEvent` | 
| mcfedr_queue_manager.job_failed | `FailedJobEvent` |
| mcfedr_queue_manager.job_batch_start | `StartJobBatchEvent` |
| mcfedr_queue_manager.job_batch_finished | `FinishedJobBatchEvent` |

## Creating your own driver

Firstly a driver needs to implement a `QueueManager`. This should put tasks into queues.

The options argument can be used to accept any extra parameters specific to your implementation.
For example, this might include a `delay` or a `priority` if you support that.

You also need to create a `Job` class, many drivers can just extend `AbstractJob` but you can add any extra data you need.

### Creating a runner

Many drivers can use the `RunnerCommand` as a base, implementing the `getJob` method.

Other queue servers have their own runners, in which case you need to write the code such that the correct worker is called.
The service `mcfedr_queue_manager.job_executor` can help with this.
