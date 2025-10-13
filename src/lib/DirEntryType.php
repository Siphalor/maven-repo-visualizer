<?php

namespace MavenRV;

enum DirEntryType
{
    case ARTIFACT_DIR;
    case VERSION_DIR;
    case OTHER_DIR;
    case ARTIFACT_FILE;
    case SOURCES_ARTIFACT_FILE;
    case MAVEN_METADATA_FILE;
    case MAVEN_POM_FILE;
    case GRADLE_MODULE_FILE;
    case HASH_FILE;
    case OTHER_FILE;

    public function isDirectory(): bool
    {
        return $this == self::ARTIFACT_DIR || $this == self::OTHER_DIR || $this == self::VERSION_DIR;
    }

    public function isFile(): bool
    {
        return $this == self::ARTIFACT_FILE
            || $this == self::SOURCES_ARTIFACT_FILE
            || $this == self::MAVEN_METADATA_FILE
            || $this == self::MAVEN_POM_FILE
            || $this == self::GRADLE_MODULE_FILE
            || $this == self::HASH_FILE
            || $this == self::OTHER_FILE;
    }
}
