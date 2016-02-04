# Queue Manager Bundle

A bundle for accessing job queues

[![Latest Stable Version](https://poser.pugx.org/mcfedr/queue-manager-bundle/v/stable.png)](https://packagist.org/packages/mcfedr/queue-manager-bundle)
[![License](https://poser.pugx.org/mcfedr/queue-manager-bundle/license.png)](https://packagist.org/packages/mcfedr/queue-manager-bundle)

## Install

### Composer

    php composer.phar require mcfedr/queue-manager-bundle

### AppKernel

Include the bundle in your AppKernel

    public function registerBundles()
    {
        $bundles = array(
            ...
            new mcfedr\Queue\QueueManagerBundle\mcfedrQueueManagerBundle(),

## Config

This is an example config if you have installed [`mcfedr/queue-driver-pheanstalk-bundle`](https://github.com/mcfedr/queue-driver-pheanstalk-bundle)

    mcfedr_queue_manager:
        managers:
            default:
                driver: beanstalkd
                options:
                    host: 127.0.0.1
                    port: 11300
                    default_queue: mcfedr_queue


## Usage

Each manager will be a service you can access with the name `"mcfedr_queue_manager.$name"`.
It implements the `QueueManager` interface, where you can call just 2 simple methods.
