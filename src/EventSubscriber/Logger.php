<?php

namespace Mb\DoctrineLogBundle\EventSubscriber;

use Exception;
use DateTimeInterface;
use Doctrine\ORM\Events;
use ReflectionException;
use Psr\Log\LoggerInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Mb\DoctrineLogBundle\Entity\Log as LogEntity;
use Mb\DoctrineLogBundle\Service\AnnotationReader;
use Mb\DoctrineLogBundle\Entity\LoggableInterface;
use Mb\DoctrineLogBundle\Service\Logger as LoggerService;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
/**
 * Class Logger
 *
 * @package CoreBundle\EventListener
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter.Unused)
 */
final class Logger implements EventSubscriber
{
    protected array $logs;

    private EntityManagerInterface $em;

    private LoggerService $loggerService;

    private AnnotationReader $reader;

    private ?LoggerInterface $monolog;

    private ExpressionLanguage $expressionLanguage;

    private array $ignoreProperties = [];

    private bool $enabled = true;

    /**
     * Logger constructor.
     *
     * @param EntityManagerInterface $em
     * @param LoggerService $loggerService
     * @param AnnotationReader $reader
     * @param LoggerInterface|null $monolog
     * @param array $ignoreProperties
     * @param bool $enabled
     */
    public function __construct(
        EntityManagerInterface $em,
        LoggerService $loggerService,
        AnnotationReader $reader,
        ?LoggerInterface $monolog,
        array $ignoreProperties,
        bool $enabled
    )
    {
        $this->em = $em;
        $this->loggerService = $loggerService;
        $this->reader = $reader;
        $this->monolog = $monolog;
        $this->expressionLanguage = new ExpressionLanguage();
        $this->ignoreProperties = $ignoreProperties;
        $this->enabled = $enabled;
    }

    /**
     * Post persist listener
     *
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $this->log($entity, LogEntity::ACTION_CREATE);
    }

    /**
     * Post update listener
     *
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $this->log($entity, LogEntity::ACTION_UPDATE);

    }

    /**
     * Pre remove listener
     *
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $this->log($entity, LogEntity::ACTION_REMOVE);
    }

    /**
     * Get changed in many-to-many and many-to-one collections
     *
     * @param OnFlushEventArgs $args
     * @throws ReflectionException
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        foreach ($args->getEntityManager()->getUnitOfWork()->getScheduledCollectionUpdates() as $collectionUpdate) {
            /** @var PersistentCollection $collectionUpdate */
            $owner = $collectionUpdate->getOwner();
            $this->reader->init($owner);

            $mapping = $collectionUpdate->getMapping();
            if (!$this->reader->isLoggable($mapping['fieldName'])) {
                return;
            }


            $expression = $this->reader->getPropertyExpression($mapping['fieldName']);

            $insertions = [];
            foreach ($collectionUpdate->getInsertDiff() as $relatedObject) {
                $insertions[] = $this->expressionLanguage->evaluate($expression, ['obj' => $relatedObject]);
            }

            $deletions = [];
            foreach ($collectionUpdate->getDeleteDiff() as $relatedObject) {
                $deletions[] = $this->expressionLanguage->evaluate($expression, ['obj' => $relatedObject]);
            }

            $changes = [];

            if (count($insertions)) {
                $changes['insertions'] = $insertions;
            }
            if (count($deletions)) {
                $changes['deletions'] = $deletions;
            }

            if ($changes != []) {
                foreach ($collectionUpdate as $item) {
                    $changes['newSet'][] = $this->expressionLanguage->evaluate($expression, ['obj' => $item]);
                }

                if (isset($this->logs[spl_object_hash($item)])) {
                    $changes = array_merge($this->logs[spl_object_hash($item)]->getChanges(), [$mapping['fieldName'] => $changes]);
                } else {
                    $changes = [$mapping['fieldName'] => $changes];
                }
                $this->logs[spl_object_hash($owner)] = $this->loggerService->log($owner, LogEntity::ACTION_UPDATE, $changes);
            }
        }
    }

    /**
     * Flush logs. Can't flush inside post update
     *
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (!empty($this->logs)) {
            foreach ($this->logs as $log) {
                $this->em->persist($log);
            }

            $this->logs = [];
            $this->em->flush();
        }
    }

    /**
     * Log the action
     *
     * @param object $entity
     * @param string $action
     */
    private function log($entity, $action)
    {
       try {
            $this->reader->init($entity);
            if ($this->reader->isLoggable()) {
                $changeSet = [];

                if ($action === LogEntity::ACTION_UPDATE) {
                    $uow = $this->em->getUnitOfWork();

                    // get changes => should be already computed here (is a listener)
                    $changeSet = $uow->getEntityChangeSet($entity);
                    // if we have no changes left => don't create revision log
                    if (empty($changeSet)) {
                        return;
                    }

                    // just getting the changed objects ids
                    foreach ($changeSet as $key => &$values) {
                        $ignore = $this->reader->getPropertyIgnore($key);
                        if (in_array($key, $this->ignoreProperties) || !$this->reader->isLoggable($key) || $ignore) {
                            // ignore configured properties
                            unset($changeSet[$key]);
                        }
                    }
                }

                if($action === LogEntity::ACTION_REMOVE) {
                    $data = [];
                    if($entity instanceof LoggableInterface){
                        $data = $entity->dumpOnDelete();
                    } else {
                        $expression = $this->reader->getOnDeleteLogExpression();
                        if(!empty($expression)) {
                            $data = $this->expressionLanguage->evaluate($expression, ['obj' => $entity]);
                        }
                    }
                    $changeSet['_remove'] = $data;
                }

                if ($action !== LogEntity::ACTION_UPDATE || !empty($changeSet)) {
                    if (isset($this->logs[spl_object_hash(($entity))])) {
                        $changeSet = array_merge($changeSet, $this->logs[spl_object_hash($entity)]->getChanges());
                    }

                    $labeledChangeSet = $this->formatArray($changeSet);

                    $this->logs[spl_object_hash($entity)] = $this->loggerService->log(
                        $entity,
                        $action,
                        $labeledChangeSet,
                        $this->reader->getLabel()
                    );

                }
            }
        } catch (\Exception $e) {
           $this->monolog($e);
        }
    }

    private function getItemLabel($key) {
        $label = $key;
        try {
            $label = $this->reader->getPropertyLabel($key);
            if(!$label) {
                $label = $key;
            }
        } catch (Exception $e) {
        }
        return $label;
    }

    /**
     * @param array $items
     * @return mixed
     */
    private function formatArray(array $items)
    {
        try {
            $formated = [];
            foreach ($items as $key => $item) {
                $label = $this->getItemLabel($key);
                $formated[$label] = is_array($item)
                    ? $this->formatArray($item) : $this->formatValue($item, $key);
            }
            return $formated;
        } catch (Exception $e) {
            $this->monolog($e);
        }
        return [];
    }

    /**
     * @param $value
     * @param null $key
     * @return mixed
     * @throws ReflectionException
     */
    private function formatValue($value, $key = null)
    {
        if(is_array($value)) {
            return $this->formatArray($value);
        }

        if($key != null && is_object($value)) {
            $expression = $this->reader->getPropertyExpression($key);
            if($expression) {
                return $this->expressionLanguage->evaluate($expression, ['obj' => $value]);
            }
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s P');
        }
        else if(is_object($value) && method_exists($value, "toString")) {
            return $value->toString();
        }
        else if (is_object($value) && method_exists($value, 'getId')) {
            return $value->getId();
        }

        return $value;
    }

    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        if(!$this->enabled)
            return [];

        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
            Events::onFlush,
            Events::postFlush
        ];
    }

    private function monolog(Exception $e) {
        if($this->monolog) {
            $this->monolog->error($e->getMessage());
        }
    }
}