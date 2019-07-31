<?php

namespace ArtisanSdk\Support\Concerns;

trait FieldMap
{
    /**
     * Get the field mappings.
     *
     * @return array
     */
    public function mappings(): array
    {
        return $this->mappings ?? [];
    }
}
