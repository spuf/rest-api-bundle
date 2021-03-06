<?php

namespace Tests\Fixture\Mapper;

use RestApiBundle\Mapping\Mapper as Mapper;

class Release implements Mapper\ModelInterface
{
    /**
     * @var string
     *
     * @Mapper\AutoType
     */
    private $country;

    /**
     * @Mapper\DateType()
     */
    private \DateTime $date;

    /**
     * @return string|null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @param string|null $country
     *
     * @return $this
     */
    public function setCountry(?string $country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    /**
     * @param \DateTime|null $date
     *
     * @return $this
     */
    public function setDate(?\DateTime $date)
    {
        $this->date = $date;

        return $this;
    }
}
