<?php

namespace Vich\UploaderBundle\Tests\Metadata\Driver;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\Driver\ClassNames;
use Metadata\ClassMetadata as JMSClassMetadata;
use Metadata\Driver\AdvancedDriverInterface;
use Metadata\Driver\DriverChain;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Metadata\ClassMetadata;
use Vich\UploaderBundle\Metadata\Driver\AttributeDriver;
use Vich\UploaderBundle\Metadata\Driver\AttributeReader;
use Vich\UploaderBundle\Metadata\Driver\DoctrineEmbeddedDriver;
use Vich\UploaderBundle\Tests\DummyEmbeddable;
use Vich\UploaderBundle\Tests\DummyEmbeddableParent;
use Vich\UploaderBundle\Tests\DummyEmbeddingEntity;
use Vich\UploaderBundle\Tests\DummyEntity;
use Vich\UploaderBundle\Tests\DummyNestedEmbeddable;

final class DoctrineEmbeddedDriverTest extends TestCase
{
    private DriverChain $innerDriver;

    private DoctrineEmbeddedDriver $driver;

    protected function setUp(): void
    {
        if (\PHP_VERSION_ID < 80400 || !\class_exists(ClassNames::class)) {
            self::markTestSkipped('Requires PHP 8.4 and doctrine/persistence 4.1.');
        }

        $config = ORMSetup::createAttributeMetadataConfig(new ClassNames([DummyEmbeddingEntity::class, DummyEmbeddable::class, DummyNestedEmbeddable::class]), true);
        $config->enableNativeLazyObjects(true);
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(
            static fn (string $class) => \in_array($class, [DummyEmbeddingEntity::class, DummyEmbeddable::class, DummyNestedEmbeddable::class], true) ? $entityManager : null,
        );
        $registry->method('getManagers')->willReturn([$entityManager]);

        $this->innerDriver = new DriverChain([new AttributeDriver(new AttributeReader(), [$registry]), $this->createParentClassDriver()]);
        $this->driver = new DoctrineEmbeddedDriver($this->innerDriver, [$registry]);
    }

    private function createParentClassDriver(): AdvancedDriverInterface
    {
        return new class() implements AdvancedDriverInterface {
            public function loadMetadataForClass(\ReflectionClass $class): ?JMSClassMetadata
            {
                if (DummyEmbeddableParent::class !== $class->name) {
                    return null;
                }

                $metadata = new ClassMetadata($class->name);
                $metadata->fields['document'] = [
                    'mapping' => 'dummy_document',
                    'propertyName' => 'document',
                    'fileNameProperty' => 'documentName',
                    'size' => null,
                    'mimeType' => null,
                    'originalName' => null,
                    'dimensions' => null,
                ];

                return $metadata;
            }

            public function getAllClassNames(): array
            {
                return [];
            }
        };
    }

    #[Test]
    public function addsEmbeddedFieldsUnderTheirPropertyPath(): void
    {
        $metadata = $this->driver->loadMetadataForClass(new \ReflectionClass(DummyEmbeddingEntity::class));

        self::assertInstanceOf(ClassMetadata::class, $metadata);
        self::assertEquals([
            'meta.file' => [
                'mapping' => 'dummy_file',
                'propertyName' => 'meta.file',
                'fileNameProperty' => 'meta.fileName',
                'size' => 'meta.fileSize',
                'mimeType' => 'meta.fileMimeType',
                'originalName' => null,
                'dimensions' => null,
            ],
            'meta.nested.document' => [
                'mapping' => 'dummy_document',
                'propertyName' => 'meta.nested.document',
                'fileNameProperty' => 'meta.nested.documentName',
                'size' => null,
                'mimeType' => null,
                'originalName' => null,
                'dimensions' => null,
            ],
            'meta.nested.image' => [
                'mapping' => 'dummy_image',
                'propertyName' => 'meta.nested.image',
                'fileNameProperty' => 'meta.nested.imageName',
                'size' => null,
                'mimeType' => null,
                'originalName' => null,
                'dimensions' => null,
            ],
        ], $metadata->fields);
    }

    #[Test]
    public function embeddablesAreNotUploadableOnTheirOwn(): void
    {
        self::assertNull($this->driver->loadMetadataForClass(new \ReflectionClass(DummyEmbeddable::class)));
        self::assertNull($this->driver->loadMetadataForClass(new \ReflectionClass(DummyNestedEmbeddable::class)));
    }

    #[Test]
    public function classesWithoutDoctrineMetadataAreLeftToTheInnerDriver(): void
    {
        $class = new \ReflectionClass(DummyEntity::class);

        self::assertEquals($this->innerDriver->loadMetadataForClass($class), $this->driver->loadMetadataForClass($class));
    }

    #[Test]
    public function listsEntitiesWithEmbeddedFieldsAndNoEmbeddables(): void
    {
        self::assertSame([DummyEmbeddingEntity::class], $this->driver->getAllClassNames());
    }
}
