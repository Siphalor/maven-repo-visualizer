<?php

namespace MavenRV;

class ArtifactVersionMetadata
{
    public ArtifactCoordinates $coordinates;
    public ?string $description = null;
    public ?string $website = null;
    public ?string $sourcesWebsite = null;
    public ?ArtifactCoordinates $relocatedTo = null;
}
