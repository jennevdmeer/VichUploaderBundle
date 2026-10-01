<?php

namespace Vich\UploaderBundle\Tests\Metadata\Driver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Metadata\ClassMetadata;
use Vich\UploaderBundle\Metadata\Driver\AttributeDriver;
use Vich\UploaderBundle\Metadata\Driver\AttributeReader;
use Vich\UploaderBundle\Tests\DummyEmbeddingEntity;

final class AttributeDriverTest extends TestCase
{
    #[Test]
    public function readUploadableFieldsOfEmbeddedClasses(): void
    {
        $driver = new AttributeDriver(new AttributeReader(), []);
        $metadata = $driver->loadMetadataForClass(new \ReflectionClass(DummyEmbeddingEntity::class));

        self::assertInstanceOf(ClassMetadata::class, $metadata);
        self::assertEquals([
            'meta.file' => [
                'mapping' => 'dummy_file',
                'propertyName' => 'meta.file',
                'fileNameProperty' => 'meta.fileName',
                'size' => null,
                'mimeType' => null,
                'originalName' => null,
                'dimensions' => null,
            ],
            'typed.file' => [
                'mapping' => 'dummy_file',
                'propertyName' => 'typed.file',
                'fileNameProperty' => 'typed.fileName',
                'size' => null,
                'mimeType' => null,
                'originalName' => null,
                'dimensions' => null,
            ],
        ], $metadata->fields);
    }
}
