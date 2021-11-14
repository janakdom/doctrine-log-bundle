<?php

namespace Mb\DoctrineLogBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Mb\DoctrineLogBundle\Entity\Log as LogEntity;
use Mb\DoctrineLogBundle\Entity\LoggableInterface;

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
     * @param array|null $changes
     * @param null $label
     * @return LogEntity
     */
    public function log(object $object, string $action, array $changes = null, $label = null) :LogEntity
    {
        $class = $this->em->getClassMetadata(get_class($object));
        $identifier = $class->getIdentifierValues($object);

        $userIdentifier = null;
        if ($this->security->getUser()) {
            $userIdentifier = $this->security->getUser()->getUserIdentifier();
        }

        $ownerIdentifier = null;
        if ($object instanceof LoggableInterface) {
            $ownerIdentifier = $object->getOwnerIdentifier();
        }

        return new LogEntity(
            $class->getName(),
            implode(", ", $identifier),
            $action,
            $userIdentifier,
            $ownerIdentifier,
            $changes,
            $label
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
