<?php

namespace Vich\UploaderBundle\Tests;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Embeddable]
#[Vich\Uploadable]
class DummyEmbeddable
{
    #[Vich\UploadableField('dummy_file', fileNameProperty: 'fileName')]
    public ?object $file = null;

    public ?string $fileName = null;
}
