<?php

namespace Mb\DoctrineLogBundle\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Model\Blameable\BlameableTrait;
use Knp\DoctrineBehaviors\Contract\Entity\BlameableInterface;

/**
 * Class Log
 *
 * @ORM\Entity
 * @ORM\Table(name="changes_log")
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
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected int $id;

    /**
     * @ORM\Column(type="string", length=1024)
     */
    protected string $instanceId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected ?string $instanceOwner;

    /**
     * @ORM\Column(type="string")
     */
    protected string $objectClass;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected ?string $label;

    /**
     * @ORM\Column(type="string")
     */
    protected string $action;

    /**
     * @ORM\Column(name="changes", type="json", nullable=true)
     */
    protected ?array $changes;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=false)
     */
    protected DateTimeImmutable $createdAt;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected ?string $changedBy;

    /**
     * Log constructor.
     */
    public function __construct(
        string $objectClass,
        string $instanceId,
        string $action,
        ?string $changedBy,
        ?string $instanceOwner,
        ?array $changes,
        ?string $label
    ) {
        $this->objectClass = $objectClass;
        $this->instanceId = $instanceId;
        $this->action = $action;
        $this->changes = $changes;
        $this->label = $label;

        $this->changedBy = $changedBy;
        $this->instanceOwner = $instanceOwner;
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
     * Get instance id
     *
     * @return string
     */
    public function getInstanceId() : string
    {
        return $this->instanceId;
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
     * @return array|null
     */
    public function getChanges() :?array
    {
        return $this->changes;
    }

    /**
     * Get editor
     *
     * @return string|null
     */
    public function getChangedBy() :?string
    {
        return $this->changedBy;
    }

    /**
     * Get instance owner
     *
     * @return string|null
     */
    public function getInstanceOwner() :?string
    {
        return $this->instanceOwner;
    }

    /**
     * Get instance label
     *
     * @return string|null
     */
    public function getLabel() :?string
    {
        return $this->label;
    }

    /**
     * @return DateTimeInterface
     */
    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
