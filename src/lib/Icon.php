<?php

namespace MavenRV;

enum Icon
{
    case ARTIFACT_FILE;
    case SOURCES_ARTIFACT_FILE;
    case METADATA_FILE;
    case OTHER_FILE;
    case PARENT_DIR;
    case ARTIFACT_DIR;
    case VERSION_DIR;
    case OTHER_DIR;
    case HASH;
    case ARCHIVED;

    public function iconName(): string
    {
        return match ($this) {
            self::ARTIFACT_FILE => 'artifact_file',
            self::SOURCES_ARTIFACT_FILE => 'sources_artifact_file',
            self::METADATA_FILE => 'metadata_file',
            self::OTHER_FILE => 'file',
            self::PARENT_DIR => 'parent_dir',
            self::ARTIFACT_DIR => 'artifact_dir',
            self::VERSION_DIR => 'version_dir',
            self::OTHER_DIR => 'dir',
            self::HASH => 'hash',
            self::ARCHIVED => 'archived',
        };
    }

    public function altText(): string
    {
        return match ($this) {
            self::ARTIFACT_FILE => 'artifact file',
            self::SOURCES_ARTIFACT_FILE => 'sources artifact file',
            self::METADATA_FILE => 'metadata file',
            self::OTHER_FILE => 'file',
            self::PARENT_DIR => 'parent directory',
            self::ARTIFACT_DIR => 'artifact directory',
            self::VERSION_DIR => 'version directory',
            self::OTHER_DIR => 'directory',
            self::HASH => 'hash',
            self::ARCHIVED => 'archived',
        };
    }
}
