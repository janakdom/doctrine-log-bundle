<?php

namespace Mb\DoctrineLogBundle\Annotation;

/**
 * Class Log
 * @package Mb\DoctrineLogBundle\Annotation
 *
 * @Annotation
 * @Target("PROPERTY")
 */
class Log
{
    /** @var string Expression to convert an object to a string  */
    public $expression = null;
}
