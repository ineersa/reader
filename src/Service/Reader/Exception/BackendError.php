<?php

declare(strict_types=1);

namespace App\Service\Reader\Exception;

class BackendError extends \RuntimeException
{
    private ?string $hint = null;

    public function setHint(string $hint): self
    {
        $this->hint = $hint;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }
}
