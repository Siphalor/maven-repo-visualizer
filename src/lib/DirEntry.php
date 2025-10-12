<?php

namespace MavenRV;

class DirEntry
{
    public string $location;
    public string $name;
    public string $extension;
    public DirEntryType $type;
    public ?int $size;
    public int $lastModified;

    /**
     * @var DirEntry[]
     */
    public array $hashEntries = array();

    public function path(): string
    {
        return $this->location . '/' . $this->name;
    }

    public function nameWithoutExtension(): string
    {
        $length = strlen($this->name) - strlen($this->extension) - 1;
        return substr($this->name, 0, $length);
    }
}
