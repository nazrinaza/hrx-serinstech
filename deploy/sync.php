<?php

/**
 * Small rsync replacement for cPanel servers that don't have rsync. Used by deploy/cpanel-deploy.sh.
 *
 *   php sync.php SRC DEST [--exclude=PATTERN]... [--manifest=FILE] [--ignore-existing]
 *
 * --exclude          path relative to SRC, matched from the root. A folder excludes everything in it;
 *                    shell wildcards work (e.g. bootstrap/cache/*.php).
 * --manifest=FILE    remember which files were copied; on the next run, delete files that were copied
 *                    last time but no longer exist in SRC. Files this script never copied are never deleted.
 * --ignore-existing  don't overwrite files that already exist in DEST.
 */
if ($argc < 3) {
    fwrite(STDERR, "usage: php sync.php SRC DEST [--exclude=PATTERN]... [--manifest=FILE] [--ignore-existing]\n");
    exit(2);
}

$src = rtrim(realpath($argv[1]) ?: '', '/');
$dest = rtrim($argv[2], '/');
$excludes = [];
$manifest = null;
$ignoreExisting = false;
foreach (array_slice($argv, 3) as $arg) {
    if (str_starts_with($arg, '--exclude=')) {
        $excludes[] = trim(substr($arg, 10), '/');
    } elseif (str_starts_with($arg, '--manifest=')) {
        $manifest = substr($arg, 11);
    } elseif ($arg === '--ignore-existing') {
        $ignoreExisting = true;
    }
}
if ($src === '' || ! is_dir($src)) {
    fwrite(STDERR, "sync.php: source folder not found: {$argv[1]}\n");
    exit(1);
}

$isExcluded = function (string $rel) use ($excludes): bool {
    foreach ($excludes as $pattern) {
        if ($rel === $pattern || str_starts_with($rel, $pattern.'/') || fnmatch($pattern, $rel, FNM_PATHNAME)) {
            return true;
        }
    }

    return false;
};

// Walk SRC, skipping excluded folders without descending into them.
$copied = [];
$walk = function (string $dir) use (&$walk, &$copied, $src, $dest, $isExcluded, $ignoreExisting) {
    foreach (scandir($dir) as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = "$dir/$name";
        $rel = ltrim(substr($path, strlen($src)), '/');
        if ($isExcluded($rel) || is_link($path)) {
            continue;
        }
        if (is_dir($path)) {
            is_dir("$dest/$rel") || mkdir("$dest/$rel", 0755, true);
            $walk($path);
            continue;
        }
        $target = "$dest/$rel";
        if ($ignoreExisting && file_exists($target)) {
            continue;
        }
        if (! file_exists($target) || filesize($target) !== filesize($path) || md5_file($target) !== md5_file($path)) {
            if (is_link($target) || is_dir($target)) {
                fwrite(STDERR, "sync.php: skipping $rel (a folder or link is in the way)\n");
                continue;
            }
            copy($path, $target) || exit(1);
        }
        @chmod($target, fileperms($path) & 0777);
        $copied[] = $rel;
    }
};

is_dir($dest) || mkdir($dest, 0755, true);
$walk($src);

if ($manifest !== null) {
    $previous = is_file($manifest) ? file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $removed = 0;
    foreach (array_diff($previous, $copied) as $rel) {
        $target = "$dest/$rel";
        if (is_file($target) && ! is_link($target) && ! $isExcluded($rel)) {
            unlink($target);
            $removed++;
            // Remove folders left empty by the deletion (never DEST itself).
            for ($dir = dirname($target); $dir !== $dest && str_starts_with($dir, $dest.'/'); $dir = dirname($dir)) {
                if (count(scandir($dir)) > 2 || ! @rmdir($dir)) {
                    break;
                }
            }
        }
    }
    file_put_contents($manifest, implode("\n", $copied)."\n");
    if ($removed) {
        echo "sync.php: removed $removed file(s) that are no longer in the repository\n";
    }
}
