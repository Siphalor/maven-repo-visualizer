<?php

namespace MavenRV;

use SplFileInfo;

class DirEntry
{
    public string $location;
    public string $name;
    public string $extension;
    public DirEntryType $type;
    public ?int $size = null;
    public int $lastModified;

    public ?ArtifactMetadata $artifactMetadata = null;
    public ?ArtifactVersionMetadata $versionMetadata = null;

    /**
     * @var DirEntry[]
     */
    public array $hashEntries = array();

    /**
     * @var DirEntry[]
     */
    public array $subEntries = array();

    public static function forPath(string $path): ?DirEntry
    {
        $fileInfo = new SplFileInfo($path);
        if (!$fileInfo->isFile() && !$fileInfo->isDir()) {
            return null;
        }

        $entry = new DirEntry();
        $entry->location = $fileInfo->getPath();
        $entry->extension = $fileInfo->getExtension();
        $entry->name = $fileInfo->getFilename();
        $entry->size = $fileInfo->getSize();
        $entry->lastModified = $fileInfo->getMTime();

        if ($fileInfo->isDir()) {
            $entry->type = DirEntryType::OTHER_DIR;
        } elseif ($entry->extension === 'module') {
            $entry->type = DirEntryType::GRADLE_MODULE_FILE;
        } elseif ($entry->extension === 'pom') {
            $entry->type = DirEntryType::MAVEN_POM_FILE;
        } elseif ($entry->name === 'maven-metadata.xml' || $entry->name === 'maven-metadata-local.xml') {
            $entry->type = DirEntryType::MAVEN_METADATA_FILE;
        } elseif ($entry->extension === 'md5' || str_starts_with($entry->extension, 'sha')) {
            $entry->type = DirEntryType::HASH_FILE;
        } else {
            $entry->type = DirEntryType::OTHER_FILE;
        }
        return $entry;
    }

    public function resolveDirectory(): void
    {
        $mavenMetadataFile = null;
        $mavenPomFile = null;
        foreach (scandir($this->path()) as $subEntryName) {
            $subEntry = self::forPath($this->path() . '/' . $subEntryName);
            $this->subEntries[$subEntryName] = $subEntry;

            if ($subEntry->type === DirEntryType::MAVEN_METADATA_FILE) {
                $mavenMetadataFile = $subEntry;
            } elseif ($subEntry->type === DirEntryType::MAVEN_POM_FILE) {
                $mavenPomFile = $subEntry;
            }
        }

        if ($mavenMetadataFile && !$this->artifactMetadata) {
            $this->type = DirEntryType::ARTIFACT_DIR;
            $mavenMetadataFile->resolveMavenMetadata();
            $this->artifactMetadata = $mavenMetadataFile->artifactMetadata;
            if ($this->artifactMetadata) {
                $latestEntry = $this->subEntries[$this->artifactMetadata->latestVersion];
                if ($latestEntry) {
                    $latestEntry->resolveDirectory();
                    if ($latestEntry->versionMetadata) {
                        $this->versionMetadata = $latestEntry->versionMetadata;
                    }
                }
            }
        } elseif ($mavenPomFile && !$this->versionMetadata) {
            $this->type = DirEntryType::VERSION_DIR;
            $mavenPomFile->resolveMavenPom();
            $this->versionMetadata = $mavenPomFile->versionMetadata;
        }

        foreach ($this->subEntries as $subEntryName => $subEntry) {
            if ($subEntry->type === DirEntryType::OTHER_FILE) {
                if ($this->type === DirEntryType::VERSION_DIR) {
                    if (preg_match('/\\bsources\\.[^.]+$/i', $subEntry->name)) {
                        $subEntry->type = DirEntryType::SOURCES_ARTIFACT_FILE;
                    } else {
                        $subEntry->type = DirEntryType::ARTIFACT_FILE;
                    }
                }
            } elseif ($subEntry->type === DirEntryType::HASH_FILE) {
                $mainEntry = $this->subEntries[$subEntry->nameWithoutExtension()];
                if ($mainEntry) {
                    $mainEntry->hashEntries[$subEntry->extension] = $subEntry;
                    unset($this->subEntries[$subEntryName]);
                }
            }
        }
    }

    private function resolveMavenMetadata()
    {
        $this->artifactMetadata = new ArtifactMetadata();
        $xml = simplexml_load_file($this->path());
        if (!$xml) {
            return null;
        }
        $this->artifactMetadata->groupId = (string) $xml->groupId;
        $this->artifactMetadata->artifactId = (string) $xml->artifactId;
        $versioning = $xml->versioning;
        if (!$versioning) {
            return null;
        }
        $this->artifactMetadata->latestVersion = $versioning->latest;
    }

    private function resolveMavenPom()
    {
        $xml = simplexml_load_file($this->path());
        if (!$xml) {
            return null;
        }
        $this->versionMetadata = new ArtifactVersionMetadata();
        $this->versionMetadata->coordinates = new ArtifactCoordinates();
        $this->versionMetadata->coordinates->groupId = (string) $xml->groupId;
        $this->versionMetadata->coordinates->artifactId = (string) $xml->artifactId;
        $this->versionMetadata->coordinates->version = (string) $xml->version;
        $this->versionMetadata->description = (string) $xml->description;
        $this->versionMetadata->website = (string) $xml->url;
        $pomScm = $xml->scm;
        if ($pomScm) {
            $this->versionMetadata->sourcesWebsite = $pomScm->url;
        }
        $pomDistributionManagement = $xml->distributionManagement;
        if ($pomDistributionManagement) {
            $pomRelocation = $pomDistributionManagement->relocation;
            if ($pomRelocation) {
                $this->versionMetadata->relocatedTo = new ArtifactCoordinates();
                $this->versionMetadata->relocatedTo->groupId = $pomRelocation->groupId;
                $this->versionMetadata->relocatedTo->artifactId = $pomRelocation->artifactId;
                $this->versionMetadata->relocatedTo->version = $pomRelocation->version;
            }
        }
    }

    public function isDirectory(): bool
    {
        return $this->type->isDirectory();
    }

    public function isFile(): bool
    {
        return $this->type->isFile();
    }

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
