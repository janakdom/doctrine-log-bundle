<?php

namespace Mb\DoctrineLogBundle\Annotation;

/**
 * Class Loggable
 * @package Mb\DoctrineLogBundle\Annotation
 *
 * @Annotation
 * @Target("CLASS")
 */
class Loggable
{
    const STRATEGY_EXCLUDE_ALL = 'exclude_all';
    const STRATEGY_INCLUDE_ALL = 'include_all';

    /**
     * @var string
     * @Enum({"exclude_all", "include_all"})
     */
    public $strategy = self::STRATEGY_INCLUDE_ALL;

    /**
     * @var string Expression, what to log on delete requests (to make log-analysis easier)
     */
    public $onDeleteLog = null;
}
