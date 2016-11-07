# Queue Manager Bundle

A bundle for accessing job queues

[![Latest Stable Version](https://poser.pugx.org/mcfedr/queue-manager-bundle/v/stable.png)](https://packagist.org/packages/mcfedr/queue-manager-bundle)
[![License](https://poser.pugx.org/mcfedr/queue-manager-bundle/license.png)](https://packagist.org/packages/mcfedr/queue-manager-bundle)

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

## Config

This is an example config if you have installed [`mcfedr/queue-driver-resque-bundle`](https://github.com/mcfedr/resque-queue-driver-bundle)

    mcfedr_queue_manager:
        managers:
            default:
                driver: resque
                options:
                    host: 127.0.0.1
                    port: 6379
                    default_queue: queue


## Usage

You can access the `QueueManagerRegistry` for simple access to your queue.
Just inject `"mcfedr_queue_manager.registry"` and call `put` to add new tasks the queue.

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

## Creating a driver

Firstly a driver needs to implement a `QueueManager`. This should put tasks into queues.

The options argument can be used to accept any extra parameters specific to your implementation.
For example, this might include a `delay` or a `priority` if you support that.

You also need to create a `Job` class, many drivers can just extend `AbstractJob` but you can add any extra data you need.

### Creating a runner

Many drivers can use the `RunnerCommand` as a base, implementing the `getJob` method.

Other queue servers have their own runners, in which case you need to write the code such that the correct worker is called.
The service `mcfedr_queue_manager.job_executor` can help with this.
