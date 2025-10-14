<?php

namespace MavenRV;

class ArtifactCoordinates
{
    public string $groupId;
    public string $artifactId;
    public string $version;

    public function pathFromRoot(): string
    {
        return str_replace('.', '/', $this->groupId) . '/' . $this->artifactId . '/' . $this->version;
    }
}
