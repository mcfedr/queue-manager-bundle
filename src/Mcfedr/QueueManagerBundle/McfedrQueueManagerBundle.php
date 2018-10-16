<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle;

use Mcfedr\QueueManagerBundle\DependencyInjection\QueueManagerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class McfedrQueueManagerBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new QueueManagerCompilerPass());
    }
}
