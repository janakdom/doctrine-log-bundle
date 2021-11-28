<?php

namespace Mb\DoctrineLogBundle\Service;

use ReflectionProperty;

/**
 * Class Logger
 *
 * @package Mb\DoctrineLogBundle\Service
 */
class DeleteDumper
{
    /**
     * Extract object attributes
     *
     * @param object $object
     * @param mixed ...$properties
     * @return array
     */
    public static function dump(object $object, ...$properties) :array
    {
        $data = [];
        foreach ($properties as $property) {
            $val = self::getValue($object, $property);
            if($val) {
                $data[$property] = $val;
            }
        }
        return $data;
    }

    /**
     * @param object $object
     * @param $key
     * @return mixed|null
     */
    private static function getValue(object $object, $key)
    {
        $method = "get" . ucfirst($key);
        if (method_exists($object, $method)) {
            return $object->$method();
        }

        if (property_exists($object, $key)) {
            $rp = new ReflectionProperty($object, $key);
            if ($rp->isPublic()) {
                return $object->$key;
            }
        }

        return null;
    }
}
