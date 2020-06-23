<?php

namespace Midweste\SlimRedirects;

class RedirectRule
{
    private $id = null;
    private $source = '';
    private $type = 'path';
    private $destination = '';
    private $httpStatus = 302;
    private $active = 1;

    public function __construct()
    {
    }

    public static function factory($values = []): RedirectRule
    {
        $called = get_called_class();
        $self = new $called;
        foreach ((object) $values as $key => $value) {
            if (property_exists($self, $key)) {
                $self->{"set$key"}($value);
            }
        }
        return $self;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getActive(): int
    {
        return $this->active;
    }

    public function setActive(int $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): self
    {
        $this->destination = $destination;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }


    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function isWildcard(): bool
    {
        return (strpos($this->source, '*') !== false) ? true : false;
    }

    public function toArray(): array
    {
        return \get_object_vars($this);
    }

    public function toObject(): object
    {
        return (object) $this->toArray();
    }
}
