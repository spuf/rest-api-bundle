<?php

class RequestModelHelperTest extends Tests\BaseTestCase
{
    public function testDateTimeTransformer()
    {
        $mapperSchema = new Mapper\DTO\Schema\ScalarType();
        $mapperSchema
            ->setNullable(false)
            ->setTransformerName(Mapper\Transformer\DateTimeTransformer::getName());

        /** @var RestApiBundle\DTO\Docs\Types\TypeInterface $docsSchema */
        $docsSchema = $this->invokePrivateMethod($this->getRequestModelHelper(), 'convert', [$mapperSchema]);

        $this->assertInstanceOf(RestApiBundle\DTO\Docs\Types\DateTimeType::class, $docsSchema);
        $this->assertFalse($docsSchema->getNullable());
    }

    public function testDateTransformer()
    {
        $mapperSchema = new Mapper\DTO\Schema\ScalarType();
        $mapperSchema
            ->setNullable(false)
            ->setTransformerName(Mapper\Transformer\DateTransformer::getName());

        /** @var RestApiBundle\DTO\Docs\Types\TypeInterface $docsSchema */
        $docsSchema = $this->invokePrivateMethod($this->getRequestModelHelper(), 'convert', [$mapperSchema]);

        $this->assertInstanceOf(RestApiBundle\DTO\Docs\Types\DateType::class, $docsSchema);
        $this->assertFalse($docsSchema->getNullable());
    }

    public function testEntityTransformer()
    {
        $mapperSchema = new Mapper\DTO\Schema\ScalarType();
        $mapperSchema
            ->setNullable(false)
            ->setTransformerName(RestApiBundle\Services\Request\MapperTransformer\EntityTransformer::getName())
            ->setTransformerOptions([
                RestApiBundle\Services\Request\MapperTransformer\EntityTransformer::CLASS_OPTION => TestApp\Entity\Genre::class,
                RestApiBundle\Services\Request\MapperTransformer\EntityTransformer::FIELD_OPTION => 'slug',
            ]);

        /** @var RestApiBundle\DTO\Docs\Types\StringType $docsSchema */
        $docsSchema = $this->invokePrivateMethod($this->getRequestModelHelper(), 'convert', [$mapperSchema]);

        $this->assertInstanceOf(RestApiBundle\DTO\Docs\Types\StringType::class, $docsSchema);
        $this->assertSame('Entity "Genre" by field "slug"', $docsSchema->getDescription());
        $this->assertFalse($docsSchema->getNullable());
    }

    public function testEntitiesCollectionTransformer()
    {
        $mapperSchema = new Mapper\DTO\Schema\ScalarType();
        $mapperSchema
            ->setNullable(false)
            ->setTransformerName(RestApiBundle\Services\Request\MapperTransformer\EntitiesCollectionTransformer::getName())
            ->setTransformerOptions([
                RestApiBundle\Services\Request\MapperTransformer\EntitiesCollectionTransformer::CLASS_OPTION => TestApp\Entity\Genre::class,
                RestApiBundle\Services\Request\MapperTransformer\EntitiesCollectionTransformer::FIELD_OPTION => 'slug',
            ]);

        /** @var RestApiBundle\DTO\Docs\Types\ArrayType $docsSchema */
        $docsSchema = $this->invokePrivateMethod($this->getRequestModelHelper(), 'convert', [$mapperSchema]);

        $this->assertInstanceOf(RestApiBundle\DTO\Docs\Types\ArrayType::class, $docsSchema);
        $this->assertFalse($docsSchema->getNullable());

        $innerType = $docsSchema->getInnerType();
        $this->assertInstanceOf(RestApiBundle\DTO\Docs\Types\StringType::class, $innerType);
        $this->assertFalse($innerType->getNullable());
    }
}