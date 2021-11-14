<?php
namespace Mb\DoctrineLogBundle\Service;

use ReflectionClass;
use ReflectionException;
use Doctrine\Common\Annotations\Reader;
use Mb\DoctrineLogBundle\Annotation\Log;
use Mb\DoctrineLogBundle\Annotation\Exclude;
use Mb\DoctrineLogBundle\Annotation\Loggable;

/**
 * Class AnnotationReader
 *
 * @package Mb\DoctrineLogBundle\Service
 */
class AnnotationReader
{
    private Reader $reader;

    private ?Loggable $classAnnotation;

    private object $entity;

    /**
     * AnnotationReader constructor.
     *
     * @param Reader $reader
     */
    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Init the entity
     *
     * @param object $entity
     * @throws ReflectionException
     */
    public function init(object $entity)
    {
        $this->entity = $entity;
        $class = new ReflectionClass(str_replace('Proxies\__CG__\\', '', get_class($entity)));
        $this->classAnnotation = $this->reader->getClassAnnotation($class, Loggable::class);
    }

    /**
     * Check if class or property is loggable
     *
     * @param string|null $property
     * @return bool
     * @throws ReflectionException
     */
    public function isLoggable(string $property = null) :bool
    {
        return !$property ? $this->classAnnotation instanceof Loggable : $this->isPropertyLoggable($property);
    }

    public function getOnDeleteLogExpression(): ?string {
        if($this->classAnnotation instanceof Loggable) {
            return $this->classAnnotation->onDeleteExpr;
        }

        return null;
    }

    public function getLabel(): ?string {
        if($this->classAnnotation instanceof Loggable) {
            return $this->classAnnotation->label;
        }

        return null;
    }

    /**
     * Check if property is loggable
     *
     * @param string $property
     * @return bool
     * @throws \ReflectionException
     */
    private function isPropertyLoggable(string $property) :bool
    {
        $property = new \ReflectionProperty(
            str_replace('Proxies\__CG__\\', '', get_class($this->entity)),
            $property
        );

        if ($this->classAnnotation->strategy === Loggable::STRATEGY_EXCLUDE_ALL) {
            // check for log annotation
            $annotation = $this->reader->getPropertyAnnotation($property, Log::class);

            return $annotation instanceof Log;
        }

        // include all strategy, check for exclude
        $annotation = $this->reader->getPropertyAnnotation($property, Exclude::class);

        return !$annotation instanceof Exclude;
    }

    /**
     * @param string $property
     * @return string|null
     * @throws \ReflectionException
     */
    public function getPropertyExpression(string $property) :?string
    {
        $property = new \ReflectionProperty(
            str_replace('Proxies\__CG__\\', '', get_class($this->entity)),
            $property
        );

        $annotation = $this->reader->getPropertyAnnotation($property, Log::class);

        if ($annotation instanceof Log) {
            return $annotation->expression;
        }

        return null;
    }

    /**
     * @param string $property
     * @return string|null
     * @throws \ReflectionException
     */
    public function getPropertyLabel(string $property) :?string
    {
        $property = new \ReflectionProperty(
            str_replace('Proxies\__CG__\\', '', get_class($this->entity)),
            $property
        );

        $annotation = $this->reader->getPropertyAnnotation($property, Log::class);

        if ($annotation instanceof Log) {
            return $annotation->label;
        }

        return null;
    }
}
