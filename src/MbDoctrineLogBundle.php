<?php

namespace Mb\DoctrineLogBundle;

use Mb\DoctrineLogBundle\DependencyInjection\MbDoctrineLogExtension;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MbDoctrineLogBundle
 * @package Mb\DoctrineLog
 */
class MbDoctrineLogBundle extends Bundle
{
    public function getContainerExtension(): Extension
    {
        return new MbDoctrineLogExtension();
    }

}
