<?php

namespace MavenRV;

enum DirEntryType
{
    case ARTIFACT_DIR;
    case OTHER_DIR;
    case VERSION_DIR;
    case ARTIFACT_FILE;
    case SOURCES_ARTIFACT_FILE;
    case METADATA_FILE;
    case HASH_FILE;
    case OTHER_FILE;

    public function isDirectory(): bool
    {
        return $this == self::ARTIFACT_DIR || $this == self::OTHER_DIR || $this == self::VERSION_DIR;
    }

    public function isFile(): bool
    {
        return $this == self::ARTIFACT_FILE || $this == self::METADATA_FILE || $this == self::HASH_FILE || $this == self::OTHER_FILE;
    }
}
