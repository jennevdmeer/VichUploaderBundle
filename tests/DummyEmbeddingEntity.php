<?php

namespace Vich\UploaderBundle\Tests;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
class DummyEmbeddingEntity
{
    #[ORM\Embedded(class: DummyEmbeddable::class)]
    public DummyEmbeddable $meta;

    #[ORM\Embedded]
    public DummyEmbeddable $typed;
}
