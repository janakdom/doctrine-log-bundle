<?php

namespace Mb\DoctrineLogBundle\Entity;

interface LoggableInterface
{
    public function getOwnerIdentifier() :string;
}