<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use MavenRV\DirEntry;
use MavenRV\Icon;
use MavenRV\DirEntryType;
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

$dir_path = $_SERVER['SCRIPT_NAME'];
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
    <title><?= SITE_NAME ?>: <?= htmlspecialchars(basename($dir_path)) ?></title>
</head>
<body>
<main>
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
?></p>
<?php
if ($directory->versionMetadata !== null) {
    $versionCoords = $directory->versionMetadata->coordinates; ?>
        <h2>Usage</h2>
        <div class="tabs">
            <details name="usage">
                <summary>Maven</summary>
                <div class="content">
                    <pre><code class="select-all">&lt;dependency&gt;
    &lt;groupId&gt;<?= $versionCoords->groupId ?>&lt;/groupId&gt;
    &lt;artifactId&gt;<?= $versionCoords->artifactId ?>&lt;/artifactId&gt;
    &lt;version&gt;<?= $versionCoords->version ?>&lt;/version&gt;
&lt;/dependency&gt;</code></pre>
                </div>
            </details>
            <details name="usage">
                <summary>Gradle</summary>
                <div class="content">
                    <pre><code class="select-all">implementation("<?= $versionCoords->groupId ?>:<?= $versionCoords->artifactId ?>:<?= $versionCoords->version ?>")</code></pre>
                </div>
            </details>
            <details name="usage">
                <summary>Gradle (Version Catalog)</summary>
                <div class="content">
                    <pre><code>[versions]
<?= $versionCoords->artifactId ?> = "<?= $versionCoords->version ?>"

[libraries]
<?= $versionCoords->artifactId ?> = { module = "<?= $versionCoords->groupId ?>:<?= $versionCoords->artifactId ?>", version.ref = "<?= $versionCoords->artifactId ?>" }</code></pre>
            </details>
        </div>
<?php
        $latest_version = $directory->versionMetadata->coordinates->version;
}
?>
    <hr/>
    <?php
    if (isset($directory->subEntries['README.md'])) {
        $readme_path = $directory->subEntries['README.md']->path(); ?>
        <section class="rendered-markdown">
            <?= format_markdown(file_get_contents($readme_path)) ?>
        </section>
        <hr/>
    <?php } ?>
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
            <tr>
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
                ?><span class="relocation-info"> → relocated to: <a href="/<?= $entry->versionMetadata->relocatedTo->pathFromRoot() ?>"
                ><?php
                echo $entry->versionMetadata->relocatedTo->artifactId;
                if ($entry->type !== DirEntryType::ARTIFACT_DIR) {
                    echo '/' . $entry->versionMetadata->relocatedTo->version;
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
<hr/>
<footer>
    <ul>
        <li>Generated by <a href="https://github.com/Siphalor/maven-repo-visualizer">MavenRV</a>
        <li>Icons are from <a href="https://icons.getbootstrap.com/">Bootstrap Icons</a>, licensed as <a
                    href="https://github.com/twbs/icons/blob/79aca213d4c863257fdc90b3b879f35eca15a5e4/LICENSE">MIT</a>.
    </ul>
</footer>
</body>
</html>
