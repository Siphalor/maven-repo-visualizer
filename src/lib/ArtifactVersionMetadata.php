<?php

namespace MavenRV;

class ArtifactVersionMetadata
{
    public ArtifactCoordinates $coordinates;
    public ?string $description;
    public ?string $website;
    public ?string $sourcesWebsite;
    public ?ArtifactCoordinates $relocatedTo = null;
}
