<?php
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:30
 */

namespace Win32ServiceBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Win32ServiceBundle\DependencyInjection\TagRunnerCompilerPass;

class Win32ServiceBundle extends \Symfony\Component\HttpKernel\Bundle\Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new TagRunnerCompilerPass());
    }
}
