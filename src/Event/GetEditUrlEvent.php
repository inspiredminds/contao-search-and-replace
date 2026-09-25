<?php

declare(strict_types=1);

namespace InspiredMinds\ContaoSearchAndReplace\Event;

use Symfony\Contracts\EventDispatcher\Event;

class GetEditUrlEvent extends Event
{
    private string|null $editUrl = null;

    public function __construct(
        public readonly string $table,
        public readonly int $id,
    ) {
    }

    public function setEditUrl(string $url): self
    {
        $this->editUrl = $url;

        return $this;
    }

    public function getEditUrl(): string|null
    {
        return $this->editUrl;
    }
}
