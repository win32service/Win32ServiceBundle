# Win32ServiceBundle
The bundle for use Service-Library and Win32Service PHP ext into a Symfony Project.


# Install

```shell
composer require win32service/win32servicebundle
```

# Configure

Add file `config/win32service.yaml` with this content:

```yaml
win32_service:
    windows_local_encoding: ISO-8859-15 # The windows local encoding for convert the string UTF-8 from configuration to the local encoding.
    logging_extra: # This configuration enable a processor for Monolog
      enable: false # Enable a processor for add the thread number into the extra fields
        channels: # The list of channels whom the processor add the thread number. If empty, the thread number is added for all channels.
         - ref1
    services:
      -
        service_id: "" # The service id. If `thread_count` > 1, you can use `%d` to define the place of the thread number
        machine: "" # the machine name for this service. 
        displayed_name: "" # The friendly name of the service. If `thread_count` > 1, you can use `%d` to define the place of the thread number
        script_path: ~ # the script name. If not defined, the integrated execute command is used.
        script_params: "" # the arguments for the script. if `thread_count` > 1, you can use `%d` to define the place of the thread number
        run_max: 1000 # number of loop before exit
        thread_count: 1 # the number of this service need to register. Use `%d` into `service_id`, `displayed_name` and `script_params` for contains the service number.
        description: "" # the service description
        delayed_start: false # If true the starting for the service is delayed
        exit: # Settings for the exit mode
          graceful: true # if false, the exit fire the recovery action
          code: 0 # If graceful is true, this value must not be 0
        user: # The User set on the service
          account: ~ # the account name
          password: ~ # the password
        recovery:
          enable: false # The recovery action is disabled
          delay: 60000 # The delay before run action (in millisecond)
          action1: 0 # The action for the first fail
          action2: 0 # The action for the second fail
          action3: 0 # The action for the next fail
          reboot_msg: "" # The message send to log if action is reboot the server
          command: ""  # The command to be execute if the action is 'run command'
          reset_period: 86400 # The period before reset the fail count (in minutes)
        dependencies: # The list of service depends
          - Netman # An example of dependency.

```

# Define the runner

For each service, add a sub-class of `Win32Service\Model\AbstractServiceRunner`.
The sub-class must be configured in service with the tag `win32service.runner` with an alias name corresponding to the `service_id`.

## Exemple with one thread

Extension configuration:

```yaml
win32_service:
    windows_local_encoding: ISO-8859-1
    services:
      -
        service_id: "my_service"
        displayed_name: "My beautiful service"
        #[...]

```


Sub-class:

```php

class MyRunner extends \Win32Service\Model\AbstractServiceRunner
{}

```

Service configuration :

```yaml
services:
    MyRunner:
      tags:
        - { name: win32service.runner, alias: 'my_service'}
```



## Exemple with many thread

Extension configuration:

```yaml
win32_service:
    windows_local_encoding: ISO-8859-1
    services:
      -
        service_id: "my_service_%d"
        displayed_name: "My beautiful service"
        thread_count: 3
        #[...]

```


Sub-class:

```php

class MyRunner extends \Win32Service\Model\AbstractServiceRunner
{}

```

Service configuration :

```yaml
services:
    MyRunner:
      tags:
        - { name: win32service.runner, alias: 'my_service_%d'}
```

# Manage the service

Open an administrator cmd window and go to your project.

## Register the service

For register all service, run this command:

```bash
php bin\console win32service:register
```

For register only one service (without thread number) run:

```bash
php bin\console win32service:register --service-name=my_service
```
For register only one service (with thread number) run:

```bash
php bin\console win32service:register --service-name=my_service_%d
```

## Unregister the service

For unregister all service, run this command:

```bash
php bin\console win32service:unregister
```

For unregister only one service (without thread number) run:

```bash
php bin\console win32service:unregister --service-name=my_service
```

For unregister only one service (with thread number) run:

```bash
php bin\console win32service:unregister --service-name=my_service_%d
```


## Sent action at your services

This command start all service (and all thread) defined in your project.

```bash
php bin\console win32service:action start
```

For sent the action for one service and all thread:

```bash
php bin\console win32service:action start --service-name=my_service_%d
```

For sent the action for one thread of one service (work same if the service has only one thread):

```bash
php bin\console win32service:action start --service-name=my_service_1
```


