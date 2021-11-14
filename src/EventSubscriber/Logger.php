<?php

namespace Mb\DoctrineLogBundle\EventSubscriber;

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
                $labeledChangeSet = [];

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
                        if (in_array($key, $this->ignoreProperties) || !$this->reader->isLoggable($key)) {
                            // ignore configured properties
                            unset($changeSet[$key]);
                        }

                        $expression = $this->reader->getPropertyExpression($key);

                        if ($expression != null) {
                            if (is_object($values[0])) {
                                $values[0] = $this->expressionLanguage->evaluate($expression, ['obj' => $values[0]]);
                            }
                            if (is_object($values[1])) {
                                $values[1] = $this->expressionLanguage->evaluate($expression, ['obj' => $values[1]]);
                            }
                        } else {
                            if ($values[0] instanceof \DateTime) {
                                $values[0] = $values[0]->format('Y-m-d H:i:s');
                            }
                            if ($values[1] instanceof \DateTime) {
                                $values[1] = $values[1]->format('Y-m-d H:i:s');
                            }

                            if (is_object($values[0]) && method_exists($values[0], 'getId')) {
                                $values[0] = $values[0]->getId();
                            }

                            if (is_object($values[1]) && method_exists($values[1], 'getId')) {
                                $values[1] = $values[1]->getId();
                            }
                        }


                        $label = $this->reader->getPropertyLabel($key);
                        if($label) {
                            $labeledChangeSet[$label] = $values;
                        } else {
                            $labeledChangeSet[$key] = $values;
                        }
                    }
                }

                if($action === LogEntity::ACTION_REMOVE) {
                    $expression = $this->reader->getOnDeleteLogExpression();

                    if(!empty($expression)) {
                        $labeledChangeSet['_remove'] = $this->expressionLanguage->evaluate($expression, ['obj' => $entity]);
                    }
                }

                if ($action !== LogEntity::ACTION_UPDATE || !empty($labeledChangeSet)) {
                    if (isset($this->logs[spl_object_hash(($entity))])) {
                        $labeledChangeSet = array_merge($labeledChangeSet, $this->logs[spl_object_hash($entity)]->getChanges());
                    }
                    $this->logs[spl_object_hash($entity)] = $this->loggerService->log(
                        $entity,
                        $action,
                        $labeledChangeSet,
                        $this->reader->getLabel()
                    );

                }
            }
        } catch (\Exception $e) {
           if($this->monolog) {
               $this->monolog->error($e->getMessage());
           }
        }
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
}
