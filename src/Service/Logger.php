<?php

namespace Mb\DoctrineLogBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mb\DoctrineLogBundle\Entity\Log as LogEntity;
use Symfony\Component\Security\Core\Security;

/**
 * Class Logger
 *
 * @package Mb\DoctrineLogBundle\Service
 */
class Logger
{
    protected EntityManagerInterface $em;
    protected Security $security;

    /**
     * Logger constructor.
     *
     * @param EntityManagerInterface $em
     * @param Security $security
     */
    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    /**
     * Logs object change
     *
     * @param object $object
     * @param string $action
     * @param string $changes
     * @return LogEntity
     */
    public function log($object, $action, $changes = null) :LogEntity
    {
        $class = $this->em->getClassMetadata(get_class($object));
        $identifier = $class->getIdentifierValues($object);

        $userIdentifier = null;
        if ($this->security->getUser()) {
            $userIdentifier = $this->security->getUser()->getUserIdentifier();
        }
        return new LogEntity(
            $class->getName(),
            implode(", ", $identifier),
            $action,
            $userIdentifier,
            $changes
        );
    }

    /**
     * Saves a log
     *
     * @param LogEntity $log
     * @return bool
     */
    public function save(LogEntity $log) :bool
    {
        $this->em->persist($log);
        $this->em->flush();
        return true;
    }
}
