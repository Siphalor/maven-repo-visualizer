<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use MavenRV\DirEntry;
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
        DirEntryType::SOURCES_ARTIFACT_FILE,
        DirEntryType::METADATA_FILE,
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

function print_entry_icon(?string $iconName): void
{
    if ($iconName) {
        include __DIR__ . '/icons/' . $iconName . '.svg';
    }
}

function get_entry_icon_name(DirEntry $entry): ?string
{
    switch ($entry->type) {
        case DirEntryType::ARTIFACT_FILE:
            return 'artifact_file';
        case DirEntryType::SOURCES_ARTIFACT_FILE:
            return 'sources_artifact_file';
        case DirEntryType::METADATA_FILE:
            return 'metadata_file';
        case DirEntryType::ARTIFACT_DIR:
            return 'artifact_dir';
        case DirEntryType::VERSION_DIR:
            return 'version_dir';
        default:
            if ($entry->type->isDirectory()) {
                return 'dir';
            } else {
                return 'file';
            }
    }
}

$dir_path = $_SERVER['SCRIPT_NAME'];
$absolute_dir_path = $_SERVER['DOCUMENT_ROOT'] . $dir_path;

$directory = \MavenRV\Directory::fromPath($absolute_dir_path);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            color-scheme: light dark;
            --bg-color: light-dark(#d9d3d1, #333030);
            --subtle-contrast-color: light-dark(#333030, #d9d3d1);
            --subtle-bg-color: color-mix(in srgb, var(--bg-color) 90%, var(--subtle-contrast-color));
            --text-color: light-dark(black, white);
            --primary-color: light-dark(<?= PRIMARY_COLOR ?>, <?= PRIMARY_COLOR_DARK_MODE ?>);
            --border-radius: 0.5rem;
        }

        * {
            font-family: sans-serif;
            margin: 0;
            accent-color: var(--primary-color);
        }

        a {
            color: inherit;
            text-decoration: underline;
            text-decoration-color: var(--primary-color);
            &:hover {
                color: var(--primary-color);
            }
        }
        hr {
            width: max(80%, 20ch);
            margin: 3rem auto;
            border: solid var(--subtle-contrast-color);
            border-image: linear-gradient(to right, transparent, var(--text-color) 30%, var(--text-color) 70%, transparent) 1 0 0 / 1px 0 0;
            border-width: 1px 0 0;
        }
        h1, h2 {
            margin-block: 1rem 0.5rem;
        }
        h3, h4, h5, h6, p {
            margin-block: 0.5rem;
        }
        code {
            background: var(--subtle-bg-color);
            padding: 0.25rem;
            border-radius: var(--border-radius);
            font-family: monospace;
        }
        pre > code {
            display: block;
            padding: 0.5rem;
        }

        .sr-only {
            position: absolute;
            left: -10000px;
            top: auto;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        html {
            background: var(--bg-color);
        }

        body {
            max-width: 90ch;
            padding: 1rem;
            margin-inline: auto;
        }

        .fancy-quotes {
            &::before {
                content: open-quote;
            }
            &::after {
                content: close-quote;
            }
            &::before, &::after {
                color: var(--primary-color);
            }
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            padding: 0.25rem 0.5rem;
            text-align: left;
            &.short-min-width {
                width: 2ch;
            }
            &.no-wrap {
                white-space: nowrap;
            }
            &.time {
                width: max-content;
                max-width: max-content;
            }
            &:first-child {
                border-radius: var(--border-radius) 0 0 var(--border-radius);
            }
            &:last-child {
                border-radius: 0 var(--border-radius) var(--border-radius) 0;
            }
            svg {
                vertical-align: middle;
            }
        }
        :is(thead, :nth-child(2n of tr)) :is(td, th) {
            background: var(--subtle-bg-color);
        }

        .hashes-trigger {
            position: relative;
            .hashes {
                display: none;
            }
            &:hover .hashes {
                display: block;
                position: absolute;
                box-sizing: content-box;
                top: 0;
                left: 0;
                background: var(--subtle-bg-color);
                border-radius: var(--border-radius);
                border: 1px solid var(--subtle-contrast-color);
                padding: 0.5rem;
                z-index: 1;
                font-size: small;
                ul {
                    list-style: none;
                    padding: 0;
                }
                li {
                    white-space: nowrap;
                }
                .type {
                    font-weight: bold;
                }
                .hash {
                    font-family: monospace;
                    user-select: all;
                }
            }
        }

        footer {
            display: flex;
            justify-content: center;
            font-size: x-small;

            & > ul {
                display: contents;
                list-style: none;

                & > li:not(:first-child)::before {
                    content: "  ·  ";
                    white-space: pre;
                }
            }
        }
    </style>
    <title><?= SITE_NAME ?>: <?= $dir_path ?></title>
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
    <hr/>
<?php
if (isset($directory->entries['README.md'])) {
    $readme_path = $directory->entries['README.md']->path(); ?>
    <section class="rendered-markdown">
    <?= format_markdown(file_get_contents($readme_path)) ?>
    </section>
    <hr />
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
                <td class="short-min-width"><a href=".."><?php print_entry_icon("parent_dir") ?></a></td>
                <td><a href="..">Parent directory</a></td>
                <td></td>
                <td></td>
            </tr>
        <?php }
        $entries = sort_entries(filter_entries($directory->entries));
foreach ($entries as $entry) {
    ?>
        <tr>
            <td class="short-min-width"><a
                        href="<?= urlencode($entry->name) ?>"><?php print_entry_icon(get_entry_icon_name($entry)) ?></a></td>
            <td><a href="<?= urlencode($entry->name) ?>"><?= htmlspecialchars($entry->name) ?></a><?php
            if ($entry->hashEntries) {
                $hashes_checkbox_id = "hashes-" . sha1($entry->name);
                ?> <span class="hashes-trigger"><?php
                    print_entry_icon("hash");
                ?><div class="hashes">Hashes for <?= htmlspecialchars($entry->name) ?>:<ul><?php
                        foreach ($entry->hashEntries as $hashEntry) {
                            if ($hashEntry->size < 1024) {
                                ?><li><span class="type"><?= htmlspecialchars($hashEntry->extension) ?>:</span> <span class="hash"><?=
                            htmlspecialchars(file_get_contents($hashEntry->path()))
                                ?></span><?php
                            }
                        }
                ?></ul></div></span> <?php } ?></td>
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
