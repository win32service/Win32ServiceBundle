<?php

declare(strict_types=1);
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:30
 */

namespace Win32ServiceBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Win32Service\Model\RunnerServiceInterface;
use Win32ServiceBundle\DependencyInjection\MessengerPass;
use Win32ServiceBundle\DependencyInjection\TagRunnerCompilerPass;

class Win32ServiceBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $autoconfig = $container->registerForAutoconfiguration(RunnerServiceInterface::class);
        $autoconfig->addTag(TagRunnerCompilerPass::WIN32SERVICE_RUNNER_TAG);

        $container->addCompilerPass(new TagRunnerCompilerPass());
        $container->addCompilerPass(new MessengerPass());
    }
}
