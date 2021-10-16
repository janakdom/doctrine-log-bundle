<?php

namespace Mb\DoctrineLogBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Contract\Entity\BlameableInterface;
use Knp\DoctrineBehaviors\Model\Blameable\BlameableTrait;

/**
 * Class Log
 *
 * @ORM\Entity
 * @ORM\Table(name="mb_entity_log")
 *
 * @package CoreBundle\Entity
 */
class Log
{
    /**
     * Action create
     */
    const ACTION_CREATE = 'create';

    /**
     * Action update
     */
    const ACTION_UPDATE = 'update';

    /**
     * Action remove
     */
    const ACTION_REMOVE = 'remove';

    /**
     * @var int $id
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string $objectClass
     *
     * @ORM\Column(name="object_class", type="string")
     */
    protected $objectClass;

    /**
     * @var string $foreignKey
     *
     * @ORM\Column(name="foreign_key", type="string", length=1024)
     */
    protected $foreignKey;

    /**
     * @var string $action
     *
     * @ORM\Column(name="action", type="string")
     */
    protected $action;

    /**
     * @var array $changes
     *
     * @ORM\Column(name="changes", type="json", nullable=true)
     */
    protected $changes;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime_immutable", nullable=false)
     */
    protected $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     */
    protected $changedBy;

    /**
     * Log constructor.
     */
    public function __construct(
        string $objectClass,
        string $foreignKey,
        string $action,
        ?string $changedBy,
        ?array $changes
    ) {
        $this->objectClass = $objectClass;
        $this->foreignKey = $foreignKey;
        $this->action = $action;
        $this->changes = $changes;

        $this->changedBy = $changedBy;
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * Get objectClass
     *
     * @return string
     */
    public function getObjectClass() : string
    {
        return $this->objectClass;
    }

    /**
     * Get foreignKey
     *
     * @return string
     */
    public function getForeignKey() : string
    {
        return $this->foreignKey;
    }

    /**
     * Get action
     *
     * @return string
     */
    public function getAction() : string
    {
        return $this->action;
    }

    /**
     * Get changes
     *
     * @return array
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
