<?php

namespace Midweste\SlimRedirects;

class RedirectOptions
{

    const ENABLED = 'enabled';
    const FORCEHTTPS = 'forcehttps';
    private $enabled = false;
    private $forcehttps = false;
    private $wildcard = false;

    public static function factory(?array $options = []): RedirectOptions
    {
        $self =  new self($options);
        foreach ($options as $option => $value) {
            if ($self->hasOption($option)) {
                $self->{$option} = $value;
            }
        }
        return $self;
    }

    public function getOption(string $option, $default = null)
    {
        if (!$this->hasOption($option)) {
            throw new \Exception(sprintf('Could not set option: %s', $option));
        }
        $value = $this->{$option};
        return (!is_null($value)) ? $value : $default;
    }

    public function setOption(string $option, $value): self
    {
        if (!$this->hasOption($option)) {
            throw new \Exception(sprintf('Could not set option: %s', $option));
        }
        $this->{$option} = $value;
        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->getOption($this::ENABLED);
    }

    public function setEnabled(bool $enabled): self
    {
        return $this->setOption($this::ENABLED, $enabled);
    }

    public function getForcehttps(): bool
    {
        return $this->getOption($this::FORCEHTTPS);
    }

    public function setForcehttps(bool $forcehttps): self
    {
        return $this->setOption($this::FORCEHTTPS, $forcehttps);
    }

    public function hasOption(string $option): bool
    {
        return (property_exists($this, strtolower($option)));
    }
}
