<?php
/** One-off: generate page-*.php inventory (theme root only). */
$theme = dirname(__DIR__);
$setup = file_get_contents($theme . '/functions/admin/page-setup.php');
preg_match_all("/(?:get_page_by_path|post_name)\s*\(\s*'([^']+)'|post_name'\s*=>\s*'([^']+)'/", $setup, $m);
$auto = array_unique(array_filter(array_merge($m[1], $m[2])));

function aidunite_inventory_count_refs(string $theme, string $slug): int {
    $count = 0;
    $needle = '/' . $slug;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($theme, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'js'], true)) {
            continue;
        }
        $path = $file->getPathname();
        if (strpos($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) !== false
            || strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            continue;
        }
        if (preg_match_all('/home_url\s*\(\s*[\'"]\/?' . preg_quote($slug, '/') . '([\'"\/?]|$)/', $content, $mm)) {
            $count += count($mm[0]);
        }
    }
    return $count;
}

$files = glob($theme . '/page-*.php');
sort($files);

$rows = [];
foreach ($files as $f) {
    $base = basename($f);
    $slug = preg_replace('/^page-/', '', pathinfo($base, PATHINFO_FILENAME));
    $head = file_get_contents($f, false, null, 0, 2500);
    $tm = '—';
    if (preg_match('/Template Name:\s*(.+)/u', $head, $mm)) {
        $tm = trim(preg_replace('/\*\/\s*$/', '', $mm[1]));
    }
    $refs = aidunite_inventory_count_refs($theme, $slug);
    $autoY = in_array($slug, $auto, true) ? 'Y' : '';
    $rows[] = compact('base', 'tm', 'slug', 'refs', 'autoY');
}

$out = $theme . '/docs/reports/page-inventory-tsv.txt';
$fp = fopen($out, 'w');
fwrite($fp, "file\ttemplate\tslug\thome_url_refs\tauto_page\n");
foreach ($rows as $r) {
    fwrite($fp, "{$r['base']}\t{$r['tm']}\t{$r['slug']}\t{$r['refs']}\t{$r['autoY']}\n");
}
fwrite($fp, 'TOTAL: ' . count($files) . "\n");
fclose($fp);
echo "Wrote {$out} (" . count($files) . " pages)\n";
