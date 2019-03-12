# From 5 to 6

1. The main difference from v5 to 6 is that you no longer need extra 'driver'
bundles, they are all included. You should remove any queue driver bundles you 
have installed, but make sure you add any dependencies they have directly, 
e.g. for SQS you should install `aws/aws-sdk-php`.

1. You also need to make sure all your `Worker` classes are tagged with
`mcfedr_queue_manager.worker`. This will happen automatically with Symfony 
autowiring enabled, but for legacy projects you might need to add the tags
manually.

1. The bundle no longer requires your `Worker`s be public.

1. The method `Worker::execute` now has a `void` return type.

## Driver implementation

If you have a custom (non `mcfedr/`) queue driver you will need to update it.

1. The abstract `RunnerCommand` has changed the interfaces to give and take
`JobBatch`.

1. When `finishJobs` is called, its possible the batch has unhandled `Job`s in it
that must be made available again. This is because the runner now attempts to
handle out of memory errors gracefully.

1. `QueueManager::put` and `::delete` now have return types added to the interface.

1. If you have a custom runner, it should be using `JobExecutor`, and the interface
for start and stop batch now takes a `JobBatch` instead of `array`s.
  
