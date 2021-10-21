<?php
declare(strict_types=1);
namespace Mb\DoctrineLogBundle\DependencyInjection;

use Mb\DoctrineLogBundle\EventSubscriber\Logger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Class MbDoctrineLogExtension
 *
 * @package Mb\DoctrineLog\DependencyInjection
 */
class MbDoctrineLogExtension extends Extension
{
    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {
        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $containerBuilder);
        $config = $this->processConfiguration($configuration, $configs);

        $containerBuilder->setParameter('mb_doctrine_log.ignore_properties', $config['ignore_properties']);
        $containerBuilder->setParameter('mb_doctrine_log.enabled', $config['enabled']);
    }
}
