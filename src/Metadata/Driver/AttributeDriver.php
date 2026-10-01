<?php

namespace Vich\UploaderBundle\Metadata\Driver;

use Doctrine\ORM\Mapping\Embedded;
use Metadata\ClassMetadata as JMSClassMetadata;
use Metadata\Driver\AdvancedDriverInterface;
use Vich\UploaderBundle\Exception\DoctrineEmbeddedTypeNotFound;
use Vich\UploaderBundle\Mapping\Attribute\Uploadable;
use Vich\UploaderBundle\Mapping\Attribute\UploadableField;
use Vich\UploaderBundle\Metadata\ClassMetadata;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 * @author Konstantin Myakshin <koc-dp@yandex.ru>
 */
class AttributeDriver implements AdvancedDriverInterface
{
    /**
     * @param \Doctrine\Persistence\ManagerRegistry[] $managerRegistryList
     */
    public function __construct(
        protected readonly AttributeReader $reader,
        private readonly array $managerRegistryList
    ) {
    }

    public function loadMetadataForClass(\ReflectionClass $class): ?JMSClassMetadata
    {
        if (!$this->isUploadable($class)) {
            return null;
        }

        $classMetadata = new ClassMetadata($class->name);
        $classMetadata->fileResources[] = $class->getFileName();

        $this->addUploadableClassProperties($classMetadata, $class);

        return $classMetadata;
    }

    public function getAllClassNames(): array
    {
        $classes = [];
        $metadata = [];

        foreach ($this->managerRegistryList as $managerRegistry) {
            $managers = $managerRegistry->getManagers();
            foreach ($managers as $manager) {
                $metadata[] = $manager->getMetadataFactory()->getAllMetadata();
            }
        }

        $metadata = \array_merge(...$metadata);

        /** @var \Doctrine\Persistence\Mapping\ClassMetadata $classMeta */
        foreach ($metadata as $classMeta) {
            if ($this->isUploadable(new \ReflectionClass($classMeta->getName()))) {
                $classes[] = $classMeta->getName();
            }
        }

        return $classes;
    }

    /**
     * @return \ReflectionProperty[]
     */
    protected function getClassProperties(\ReflectionClass $class): array
    {
        $classes = [];
        do {
            $classes[] = $class;
            $class = $class->getParentClass();
        } while (false !== $class);
        $classes = \array_reverse($classes);

        $properties = [];
        foreach ($classes as $cls) {
            $properties = [...$properties, ...$cls->getProperties()];
        }

        return $properties;
    }

    protected function isUploadable(\ReflectionClass $class): bool
    {
        return null !== $this->reader->getClassAttribute($class, Uploadable::class);
    }

    private function addUploadableClassProperties(ClassMetadata $classMetadata, \ReflectionClass $class, string $propertyPath = ''): void
    {
        foreach ($this->getClassProperties($class) as $property) {
            $uploadableField = $this->reader->getPropertyAttribute($property, UploadableField::class);
            if ($uploadableField instanceof UploadableField) {
                $this->addFieldMetadata($classMetadata, $property, $uploadableField, $propertyPath);
                continue;
            }

            $embedded = $this->getDoctrineEmbeddedAttribute($property);
            if ($embedded) {
                $type = $this->getEmbeddedType($classMetadata, $property, $embedded);

                $this->addUploadableClassProperties($classMetadata, new \ReflectionClass($type), $propertyPath.$property->getName().'.');
            }
        }
    }

    private function addFieldMetadata(ClassMetadata $classMetadata, \ReflectionProperty $property, UploadableField $uploadableField, string $propertyPath): void
    {
        $propertyName = $propertyPath.$property->getName();

        $fieldMetadata = [
            'mapping' => $uploadableField->getMapping(),
            'propertyName' => $propertyName,
            'fileNameProperty' => $propertyPath.$uploadableField->getFileNameProperty(),
            'size' => $uploadableField->getSize(),
            'mimeType' => $uploadableField->getMimeType(),
            'originalName' => $uploadableField->getOriginalName(),
            'dimensions' => $uploadableField->getDimensions(),
        ];

        // TODO: store UploadableField object instead of array
        $classMetadata->fields[$propertyName] = $fieldMetadata;
    }

    private function getDoctrineEmbeddedAttribute(\ReflectionProperty $property): ?Embedded
    {
        if (!\class_exists(Embedded::class)) {
            return null;
        }

        $attributes = $property->getAttributes(Embedded::class);
        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function getEmbeddedType(ClassMetadata $classMetadata, \ReflectionProperty $property, Embedded $embedded): string
    {
        if ($embedded->class) {
            if (!\class_exists($embedded->class)) {
                throw new DoctrineEmbeddedTypeNotFound(\sprintf('Embedded class "%s" not found for "%s::%s".', $embedded->class, $classMetadata->name, $property->getName()));
            }

            return $embedded->class;
        }

        $propertyType = $property->getType();
        if ($propertyType instanceof \ReflectionNamedType) {
            return $propertyType->getName();
        }

        throw new DoctrineEmbeddedTypeNotFound(\sprintf('Embedded property type not found for "%s::%s", either typehint your property or set the type using the attribute.', $classMetadata->name, $property->getName()));
    }
}
