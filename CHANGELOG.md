# Changelog

### 6.5.0

- Add default period and delay managers.
- Add support for getName in Worker that replaces the default name.
- Fix deprecated usage of EventDispatcher.

### 6.4.4

- Fix Periodic throwing wrong delete exception.

### 6.4.3

- Fix Pub/Sub throwing wrong delete exception.

### 6.4.1

- Add support for Google Cloud Pub/Sub.
- Throw when queuing to missing queues.

### 6.3.0

- Increased reserved memory.
- Throw when job batch used incorrectly.

### 6.2.0

- Changes log levels.

### 6.1.2

- Fix delete job and not pass manager, will try to delete it using all the managers.

### 6.1.1

- Fix DoctrineDelayJob constructor should take optional manager.

### 6.1.0

- Considering 6.0 a broken release.
- Now the supported drivers are bundled.
- Removes container dependencies from all drivers.
- Changes the Worker interface.
- Checks for missing driver components.
- Handle out of memory errors.
- Changes to RunnerCommand interface.

### 6.0.0

- Min php requirement is 7.2.
- Removes container dependencies.
- Requires tags (or autowiring) on all workers.
- Remove deprecated methods and consts from runner.
- Changes constructor params for runner.
- Moves all drivers into single package.
