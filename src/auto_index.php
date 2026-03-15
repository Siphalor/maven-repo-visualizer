<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use MavenRV\DirEntry;
use MavenRV\DirEntrySubType;
use MavenRV\DirEntryType;
use MavenRV\Icon;
use MavenRV\SemverLikeComparator;

function format_markdown(string $text): string
{
    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);
    return $parsedown->text($text);
}

/**
 * @param DirEntry[] $entries
 * @return DirEntry[]
 */
function filter_entries(array $entries): array
{
    return array_filter($entries, fn ($entry) => is_entry_included($entry));
}

function is_entry_included(DirEntry $dirEntry): bool
{
    if (str_starts_with($dirEntry->name, '.')) {
        return false;
    }
    foreach (IGNORED_EXTENSIONS as $ignored_extension) {
        if (str_ends_with($dirEntry->name, $ignored_extension)) {
            return false;
        }
    }
    return true;
}

const TYPE_SORT_ORDER = array(
        DirEntryType::OTHER_DIR,
        DirEntryType::ARTIFACT_DIR,
        DirEntryType::VERSION_DIR,
        DirEntryType::ARTIFACT_FILE,
        DirEntryType::MAVEN_METADATA_FILE,
        DirEntryType::SOURCES_ARTIFACT_FILE,
        DirEntryType::MAVEN_POM_FILE,
        DirEntryType::GRADLE_MODULE_FILE,
        DirEntryType::OTHER_FILE,
        DirEntryType::HASH_FILE
);

/**
 * @param DirEntry[] $entries
 * @return DirEntry[]
 */
function sort_entries(array $entries): array
{
    usort($entries, function (DirEntry $a, DirEntry $b) {
        $a_type_index = array_search($a->type, TYPE_SORT_ORDER);
        $b_type_index = array_search($b->type, TYPE_SORT_ORDER);
        if ($a_type_index !== $b_type_index) {
            return $a_type_index <=> $b_type_index;
        }

        if ($a->type !== DirEntryType::VERSION_DIR) {
            return strcmp($a->name, $b->name);
        }

        if (VERSIONS_SORT_BY === 'name') {
            $cmp = SemverLikeComparator::compare($a->name, $b->name);
        } else {
            $cmp = $a->lastModified <=> $b->lastModified;
        }
        return VERSIONS_SORT_ORDER === 'desc' ? -$cmp : $cmp;
    });
    return $entries;
}

function human_file_size(int $bytes): string
{
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function print_entry_icon(Icon $icon): void
{
    if (ASSETS_SERVE_PATH === '$EMBED') {
        include __DIR__ . '/icons/' . $icon->iconName() . '.svg';
    } else {
        ?><span
        class="icon"
        style='--icon-url: url("<?= ASSETS_SERVE_PATH ?>/icons/<?= $icon->iconName() ?>.svg");'
        role="img"
        aria-label="<?=$icon->altText()?>"></span><?php
    }
}

function get_entry_icon(DirEntry $entry): Icon
{
    if ($entry->versionMetadata && $entry->versionMetadata->relocatedTo && $entry->isDirectory()) {
        return Icon::ARCHIVED;
    }
    switch ($entry->type) {
        case DirEntryType::ARTIFACT_FILE:
            return Icon::ARTIFACT_FILE;
        case DirEntryType::SOURCES_ARTIFACT_FILE:
            return Icon::SOURCES_ARTIFACT_FILE;
        case DirEntryType::MAVEN_METADATA_FILE:
        case DirEntryType::MAVEN_POM_FILE:
        case DirEntryType::GRADLE_MODULE_FILE:
            return Icon::METADATA_FILE;
        case DirEntryType::ARTIFACT_DIR:
            if ($entry->subType == DirEntrySubType::GRADLE_PLUGIN) {
                return Icon::GRADLE_PLUGIN_DIR;
            }
            return Icon::ARTIFACT_DIR;
        case DirEntryType::VERSION_DIR:
            return Icon::VERSION_DIR;
        default:
            if ($entry->type->isDirectory()) {
                return Icon::OTHER_DIR;
            } else {
                return Icon::OTHER_FILE;
            }
    }
}

function resolve_gradle_catalog_alias(string $artifactId): string
{
    $matches = array();
    if (preg_match(GRADLE_CATALOG_ALIAS_REGEX, $artifactId, $matches) && isset($matches[1])) {
        $base = $matches[1];
    } else {
        $base = $artifactId;
    }
    return preg_replace('/\W+/', '-', $base);
}

$dir_path = $_SERVER['PATH_INFO'];
if (!str_starts_with($dir_path, '/')) {
    $dir_path = '/' . $dir_path;
}
$absolute_dir_path = $_SERVER['DOCUMENT_ROOT'] . $dir_path;

$directory = DirEntry::forPath($absolute_dir_path);
if ($directory == null) {
    http_response_code(404);
    exit;
}
$directory->resolveDirectory();
foreach (array_filter($directory->subEntries, fn ($entry) => $entry->type->isDirectory()) as $subDir) {
    $subDir->resolveDirectory();
}

$repository_root_uri = '';
{
    $index = strrpos($_SERVER['REQUEST_URI'], $dir_path);
    if ($index === false) {
        $path = '/';
    } else {
        $path = substr($_SERVER['REQUEST_URI'], 0, $index);
    }
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    if (preg_match('{\w+://}', $path)) {
        $repository_root_uri = $path;
    } else {
        $https = (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        $scheme = $https ? 'https' : 'http';
        $port = $_SERVER['SERVER_PORT'] == ($https ? 443 : 80) ? '' : ':' . $_SERVER['SERVER_PORT'];
        $repository_root_uri = "$scheme://$_SERVER[SERVER_NAME]$port$path";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --external-icon-url: "<?= ASSETS_SERVE_PATH ?>/icons/";
            --primary-color: light-dark(<?=PRIMARY_COLOR ?>, <?=PRIMARY_COLOR_DARK_MODE ?>);
        }
    </style>
    <?php if (ASSETS_SERVE_PATH === '$EMBED') { ?>
        <style><?php include __DIR__ . '/assets/styles.css' ?></style>
    <?php } else { ?>
        <link rel="stylesheet" href="<?= ASSETS_SERVE_PATH ?>/styles.css">
    <?php } ?>
    <title><?php
echo htmlspecialchars(SITE_NAME);
if ($dir_path !== '/') {
    echo ': '.htmlspecialchars(basename($dir_path));
}
?></title>
</head>
<body>
<main>
    <div class="meta">
    <h1>Index of <span class="fancy-quotes"><?= htmlspecialchars(basename($dir_path)) ?: '/' ?></span></h1>
    <p>Full path: /<?php

    $path_parts = array_filter(explode('/', substr($dir_path, 1)));
foreach ($path_parts as $i => $path_part) {
    echo '<a href="';
    echo str_repeat('../', count($path_parts) - $i - 1);
    echo '">';
    echo htmlspecialchars($path_part);
    echo '</a>/';
}
if (
    $directory->versionMetadata
    && (
        $directory->versionMetadata->website
            || $directory->versionMetadata->sourcesWebsite
            || $directory->versionMetadata->description
            || $directory->versionMetadata->relocatedTo
    )
) {
    echo '<h2>Artifact information</h2>';
    echo '<div class="links">';
    if ($directory->versionMetadata->website) {
        ?><a href="<?= htmlspecialchars($directory->versionMetadata->website) ?>"><?php print_entry_icon(Icon::HOMEPAGE) ?> Homepage</a><?php
    }
    if ($directory->versionMetadata->sourcesWebsite) {
        ?><a href="<?= htmlspecialchars($directory->versionMetadata->sourcesWebsite) ?>"><?php print_entry_icon(Icon::SOURCE_CODE) ?> Source code</a><?php
    }
    echo '</div>';
    if ($directory->versionMetadata->relocatedTo) {
        echo '<div>';
        print_entry_icon(Icon::ARCHIVED);
        echo ' Relocated to <a href="/';
        echo htmlspecialchars($directory->versionMetadata->relocatedTo->pathFromRoot());
        echo '">';
        echo htmlspecialchars($directory->versionMetadata->relocatedTo->groupId);
        echo '/';
        echo htmlspecialchars($directory->versionMetadata->relocatedTo->artifactId);
        if ($directory->type !== DirEntryType::ARTIFACT_DIR) {
            echo '/';
            echo htmlspecialchars($directory->versionMetadata->relocatedTo->version);
        }
        echo '</a></div>';
    }
    if ($directory->versionMetadata->description) {
        echo '<p>' . htmlspecialchars($directory->versionMetadata->description) . '</p>';
    }
}
?></p>
<?php
if ($directory->versionMetadata) {
    $versionCoords = $directory->versionMetadata->coordinates;
    if ($directory->subType == DirEntrySubType::GRADLE_PLUGIN) {
        $gradlePluginId = preg_replace('/\.gradle\.plugin$/', '', $versionCoords->artifactId);
        $artifactAlias = resolve_gradle_catalog_alias($gradlePluginId);
    } else {
        $artifactAlias = resolve_gradle_catalog_alias($versionCoords->artifactId);
    }
    ?>
        <h2>Usage</h2>
        <div class="tabs">
            <?php if ($directory->subType != DirEntrySubType::GRADLE_PLUGIN) { ?>
            <details name="usage">
                <summary>Maven</summary>
                <div class="content">
                    <pre><code class="select-all">&lt;dependency&gt;
    &lt;groupId&gt;<?= htmlspecialchars($versionCoords->groupId) ?>&lt;/groupId&gt;
    &lt;artifactId&gt;<?= htmlspecialchars($versionCoords->artifactId) ?>&lt;/artifactId&gt;
    &lt;version&gt;<?= htmlspecialchars($versionCoords->version) ?>&lt;/version&gt;
&lt;/dependency&gt;</code></pre>
                </div>
            </details>
            <?php } ?>
            <details name="usage">
                <summary>Gradle</summary>
                <div class="content">
                    <?php if (isset($gradlePluginId)) { ?>
                    <pre><code>plugins {
    <span class="select-all">id("<?= htmlspecialchars($gradlePluginId) ?>")</span>
}</code></pre>
                        For use as build dependency:
                    <?php } ?>
                    <pre><code class="select-all">implementation("<?=
                            htmlspecialchars($versionCoords->groupId) ?>:<?=
                            htmlspecialchars($versionCoords->artifactId) ?>:<?=
                            htmlspecialchars($versionCoords->version) ?>")</code></pre>
                </div>
            </details>
            <details name="usage">
                <summary>Gradle (Version Catalog)</summary>
                <div class="content">
                    <pre><code><?php
    echo "[versions]\n".htmlspecialchars($artifactAlias).' = "'.htmlspecialchars($versionCoords->version).'"';
    if (isset($gradlePluginId)) {
        echo "\n\n[plugins]\n".htmlspecialchars($artifactAlias);
        echo ' = { id = "'.htmlspecialchars($gradlePluginId);
        echo '", version.ref = "'.htmlspecialchars($artifactAlias).'" }';
    }
    echo "\n\n[libraries]\n".htmlspecialchars($artifactAlias);
    if (GRADLE_CATALOG_LIBRARY_STYLE == 'module') {
        echo ' = { module = "' . htmlspecialchars($versionCoords->groupId) . ':';
        echo htmlspecialchars($versionCoords->artifactId);
    } else {
        echo ' = { group = "' . htmlspecialchars($versionCoords->groupId);
        echo '", name = "' . htmlspecialchars($versionCoords->artifactId);
    }
    echo '", version.ref = "'.htmlspecialchars($artifactAlias).'" }';
    ?></code></pre>
            </details>
        </div>
<?php
}
$heading = $dir_path === '/' ? 'h2' : 'h3';
?>
        <<?=$heading?>>Repository Setup</<?=$heading?>>
        <div class="tabs">
            <details name="usage">
                <summary>Maven (settings.xml)</summary>
                <div class="content">
                    <pre><code>&lt;repository&gt;
    &lt;id&gt;<?= htmlspecialchars($_SERVER['SERVER_NAME']) ?>&lt;/id&gt;
    &lt;name&gt;<?= htmlspecialchars(SITE_NAME) ?>&lt;/name&gt;
    &lt;url&gt;<?= htmlspecialchars($repository_root_uri) ?>&lt;/url&gt;
&lt;/repository&gt;</code></pre>
                </div>
            </details>
            <details name="usage">
                <summary>Gradle</summary>
                <div class="content">
                    <pre><code>repositories {
    maven {
        name = "<?= htmlspecialchars(SITE_NAME) ?>"
        url = uri("<?= htmlspecialchars($repository_root_uri) ?>")
    }
}</code></pre>
                </div>
            </details>
            <?php
            if (isset($directory->subEntries['README.md'])) {
                $readme_path = $directory->subEntries['README.md']->path(); ?>
        <hr class="meta-description-divider" />
        <section class="rendered-markdown">
            <?= format_markdown(file_get_contents($readme_path)) ?>
        </section>
    <?php } ?>
    </div>
    <hr class="meta-file-list-divider" />
    <table>
        <thead>
        <tr>
            <th class="short-min-width"><span class="sr-only">Type</span></th>
            <th>Name</th>
            <th class="no-wrap">Size</th>
            <th class="time">Last Modified</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($dir_path !== '/') { ?>
            <tr class="special">
                <td class="short-min-width"><a href=".."><?php print_entry_icon(Icon::PARENT_DIR) ?></a></td>
                <td><a href="..">Parent directory</a></td>
                <td></td>
                <td></td>
            </tr>
        <?php
        }
$entries = sort_entries(filter_entries($directory->subEntries));
foreach ($entries as $entry) {
    ?>
            <tr<?= $entry->versionMetadata && $entry->versionMetadata->relocatedTo ? ' class="relocated"' : '' ?>>
                <td class="short-min-width"><a
                            href="<?= urlencode($entry->name) ?>"><?php print_entry_icon(get_entry_icon($entry)) ?></a>
                </td>
                <td><a href="<?= urlencode($entry->name) ?>"><?= htmlspecialchars($entry->name) ?></a><?php
            if ($entry->hashEntries) {
                $hashes_checkbox_id = "hashes-" . sha1($entry->name);
                ?> <span class="hashes-trigger"><?php
                    print_entry_icon(Icon::HASH);
                ?><div class="hashes">Hashes for <?= htmlspecialchars($entry->name) ?>:<ul><?php
                        foreach ($entry->hashEntries as $hashEntry) {
                            if ($hashEntry->size < 1024) {
                                ?>
                                            <li><span
                                                    class="type"><?= htmlspecialchars($hashEntry->extension) ?>:</span>
                                            <span class="hash"><?=
                                        htmlspecialchars(file_get_contents($hashEntry->path()))
                                ?></span><?php
                            }
                        }
                ?></ul></div></span> <?php }
            if ($entry->versionMetadata && $entry->versionMetadata->relocatedTo) {
                ?> <span class="relocation-info">→ relocated to: <a href="/<?= htmlspecialchars($entry->versionMetadata->relocatedTo->pathFromRoot()) ?>"
                ><?php
                echo htmlspecialchars($entry->versionMetadata->relocatedTo->artifactId);
                if ($entry->type !== DirEntryType::ARTIFACT_DIR) {
                    echo '/' . htmlspecialchars($entry->versionMetadata->relocatedTo->version);
                }?></a></span><?php
            } ?></td>
                <td class="no-wrap"><?php if ($entry->type->isFile() && $entry->size) {
                    echo human_file_size($entry->size);
                } ?></td>
                <td class="time"><?= date("Y-\u{200b}m-d\u{200b}\\TH:i:s\u{200b}P", $entry->lastModified) ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</main>
<hr class="file-list-footer-divider" />
<footer>
    <ul>
        <li>Generated by <a href="https://github.com/Siphalor/maven-repo-visualizer">MavenRV</a>
        <li>Icons are from <a href="https://icons.getbootstrap.com/">Bootstrap Icons</a>, licensed as <a
                    href="https://github.com/twbs/icons/blob/79aca213d4c863257fdc90b3b879f35eca15a5e4/LICENSE">MIT</a>.
    </ul>
</footer>
</body>
</html>
