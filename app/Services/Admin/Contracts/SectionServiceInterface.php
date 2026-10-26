<?php

namespace App\Services\Admin\Contracts;

use App\Models\Admin\Secciones\Section;

interface SectionServiceInterface
{
    public function getTree(?string $platform = null, bool $onlyActive = false): array;

    public function getParentOptions(?int $excludeSectionId = null, ?string $platform = null): array;

    public function create(array $data): Section;

    public function update(Section $section, array $data): Section;

    public function delete(int $id): void;
}
