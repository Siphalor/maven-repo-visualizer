<?php

namespace MavenRV;

use SplFileInfo;

class Directory
{
    public string $path;
    public DirEntryType $type;
    /**
     * @var DirEntry[]
     */
    public array $entries;

    public static function fromPath(string $path): Directory
    {
        $dir = new Directory();
        $dir->entries = array();
        $dir->type = DirEntryType::OTHER_DIR;

        foreach (scandir($path) as $entry_name) {
            $entry_path = $path . '/' . $entry_name;
            $entry_info = new SplFileInfo($entry_path);
            $entry = new DirEntry();
            $entry->location = $path;
            $entry->name = $entry_name;
            $entry->extension = $entry_info->getExtension();
            $entry->size = $entry_info->getSize();
            $entry->lastModified = $entry_info->getMTime();

            if ($entry_info->isDir()) {
                $entry->type = self::determineSubDirectoryType($entry_path);
            } else {
                if ($entry->extension == 'pom' || $entry->extension == 'module') {
                    $entry->type = DirEntryType::METADATA_FILE;
                    $dir->type = DirEntryType::VERSION_DIR;
                } elseif (self::isMavenMetadataFile($entry_name)) {
                    $entry->type = DirEntryType::METADATA_FILE;
                    $dir->type = DirEntryType::ARTIFACT_DIR;
                } elseif ($entry->extension == 'md5' || str_starts_with($entry->extension, 'sha')) {
                    $entry->type = DirEntryType::HASH_FILE;
                } else {
                    $entry->type = DirEntryType::OTHER_FILE;
                }
            }

            $dir->entries[$entry_name] = $entry;
        }

        unset($entry_name);
        foreach ($dir->entries as $entry_name => $entry) {
            if ($entry->type == DirEntryType::OTHER_FILE) {
                if ($dir->type == DirEntryType::VERSION_DIR) {
                    if (preg_match('/\\bsources\\.[^.]+$/i', $entry->name)) {
                        $entry->type = DirEntryType::SOURCES_ARTIFACT_FILE;
                    } else {
                        $entry->type = DirEntryType::ARTIFACT_FILE;
                    }
                }
            } elseif ($entry->type == DirEntryType::HASH_FILE) {
                $main_entry = $dir->entries[$entry->nameWithoutExtension()];
                if ($main_entry) {
                    $main_entry->hashEntries[] = $entry;
                    unset($dir->entries[$entry_name]);
                }
            }
        }

        return $dir;
    }

    private static function determineSubDirectoryType(string $path): DirEntryType
    {
        $entries = scandir($path);
        foreach ($entries as $entry) {
            if (self::isMavenMetadataFile($entry)) {
                return DirEntryType::ARTIFACT_DIR;
            } elseif (str_ends_with($entry, ".pom") || str_ends_with($entry, ".module")) {
                return DirEntryType::VERSION_DIR;
            }
        }
        return DirEntryType::OTHER_DIR;
    }

    private static function isMavenMetadataFile(string $name): bool
    {
        return $name == 'maven-metadata.xml' || $name == 'maven-metadata-local.xml';
    }

    public function file_entries(): array
    {
        return array_filter($this->entries, fn ($entry) => $entry->type->isFile());
    }
}
