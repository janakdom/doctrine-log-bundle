<?php

namespace Mb\DoctrineLogBundle\Entity;

interface LoggableInterface
{
    public function getOwnerIdentifier() :string;
    public function dumpOnDelete() :array;
}