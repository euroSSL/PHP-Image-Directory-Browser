<?php
/**
 * Image Directory Browser
 * Salva questo file come images.php nella directory da esaminare.
 */

declare(strict_types=1);

$directory = __DIR__;

$extensions = [
    'jpg', 'jpeg', 'png', 'gif', 'webp',
    'svg', 'avif', 'bmp', 'tif', 'tiff', 'ico'
];

function formatSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }
    return number_format($bytes / 1024, 2, ',', '.') . ' KB';
}

function imageInfo(string $path, string $extension): array
{
    $info = [
        'width' => null,
        'height' => null,
        'mime' => '',
        'bits' => null,
        'channels' => null
    ];

    if ($extension === 'svg') {
        $info['mime'] = 'image/svg+xml';

        // Lettura limitata ai primi 64 KB, senza parser XML esterni.
        $svg = file_get_contents($path, false, null, 0, 65536);

        if ($svg !== false &&
            preg_match('/<svg\b[^>]*>/is', $svg, $match)) {

            $tag = $match[0];

            foreach (['width', 'height'] as $attr) {
                if (preg_match(
                    '/\b' . $attr . '\s*=\s*["\']([0-9.]+)(?:px)?["\']/i',
                    $tag,
                    $m
                )) {
                    $info[$attr] = (float)$m[1];
                }
            }

            if ((!$info['width'] || !$info['height']) &&
                preg_match(
                    '/\bviewBox\s*=\s*["\']\s*' .
                    '[-\d.]+\s+[-\d.]+\s+' .
                    '([\d.]+)\s+([\d.]+)\s*["\']/i',
                    $tag,
                    $m
                )) {
                $info['width'] ??= (float)$m[1];
                $info['height'] ??= (float)$m[2];
            }
        }

        return $info;
    }

    $data = @getimagesize($path);

    if ($data !== false) {
        $info['width'] = $data[0] ?? null;
        $info['height'] = $data[1] ?? null;
        $info['mime'] = $data['mime'] ?? '';
        $info['bits'] = $data['bits'] ?? null;
        $info['channels'] = $data['channels'] ?? null;
    }

    return $info;
}

$images = [];
$totalBytes = 0;

$iterator = new DirectoryIterator($directory);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->isLink()) {
        continue;
    }

    $name = $file->getFilename();
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (!in_array($ext, $extensions, true)) {
        continue;
    }

    $path = $file->getPathname();
    $size = $file->getSize();
    $info = imageInfo($path, $ext);

    $images[] = [
        'name' => $name,
        'url' => rawurlencode($name),
        'ext' => strtoupper($ext),
        'size' => $size,
        'width' => $info['width'],
        'height' => $info['height'],
        'mime' => $info['mime'],
        'bits' => $info['bits'],
        'channels' => $info['channels']
    ];

    $totalBytes += $size;
}

// Ordinamento
$sort = $_GET['sort'] ?? 'name';
$direction = $_GET['dir'] ?? 'asc';

$allowedSorts = ['name', 'size', 'width', 'height', 'ext'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'name';
}

$direction = $direction === 'desc' ? 'desc' : 'asc';

usort($images, function ($a, $b) use ($sort, $direction) {
    $result = in_array($sort, ['name', 'ext'], true)
        ? strnatcasecmp((string)$a[$sort], (string)$b[$sort])
        : (($a[$sort] ?? -1) <=> ($b[$sort] ?? -1));

    return $direction === 'desc' ? -$result : $result;
});

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sortLink(string $column, string $label): string
{
    global $sort, $direction;

    $newDirection = ($sort === $column && $direction === 'asc')
        ? 'desc' : 'asc';

    $url = '?sort=' . urlencode($column) .
           '&dir=' . $newDirection;

    return '<a href="' . e($url) . '">' . e($label) . ' ↕</a>';
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Image Directory Browser</title>

<style>
* { box-sizing: border-box; }

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f5f8;
    color: #263238;
    margin: 0;
    padding: 30px;
}

.container {
    max-width: 1400px;
    margin: auto;
}

h1 {
    margin-bottom: 8px;
    color: #193653;
}

.summary {
    background: white;
    padding: 18px 24px;
    border-radius: 8px;
    margin: 20px 0;
    box-shadow: 0 2px 8px #00000010;
}

.table-wrapper {
    overflow-x: auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px #00000010;
}

table {
    border-collapse: collapse;
    width: 100%;
}

th {
    background: #234e70;
    color: white;
    padding: 15px;
    text-align: left;
    white-space: nowrap;
}

th a {
    color: white;
    text-decoration: none;
}

th a:hover {
    text-decoration: underline;
}

td {
    padding: 12px 15px;
    border-bottom: 1px solid #e5e9ed;
    vertical-align: middle;
}

tr:hover td {
    background: #f4f9fd;
}

.preview {
    width: 130px;
    height: 100px;
    object-fit: contain;
    background: #f8f8f8;
    border: 1px solid #e5e5e5;
    border-radius: 5px;
    padding: 5px;
}

.filename {
    font-weight: bold;
    overflow-wrap: anywhere;
}

.details {
    line-height: 1.7;
    font-size: 13px;
    color: #52616b;
}

.button {
    display: inline-block;
    padding: 9px 14px;
    background: #2878b5;
    color: white;
    border-radius: 5px;
    text-decoration: none;
    white-space: nowrap;
}

.button:hover {
    background: #185984;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #777;
}

@media (max-width: 700px) {
    body { padding: 12px; }
    .preview { width: 90px; height: 75px; }
    td, th { padding: 8px; }
}
</style>
</head>

<body>
<div class="container">

<h1>Image Directory Browser</h1>

<p>
Directory:
<strong><?= e(basename($directory)) ?></strong>
</p>

<div class="summary">
    <strong>Immagini trovate:</strong>
    <?= count($images) ?>
    &nbsp; | &nbsp;
    <strong>Spazio totale:</strong>
    <?= formatSize($totalBytes) ?>
</div>

<div class="table-wrapper">
<table>
<thead>
<tr>
    <th>Anteprima</th>
    <th><?= sortLink('name', 'Nome file') ?></th>
    <th>Caratteristiche</th>
    <th>Link</th>
</tr>
</thead>
<tbody>

<?php foreach ($images as $image): ?>
<tr>
    <td>
        <a href="<?= e($image['url']) ?>"
           target="_blank" rel="noopener noreferrer">
            <img
                class="preview"
                src="<?= e($image['url']) ?>"
                alt="<?= e($image['name']) ?>"
                loading="lazy">
        </a>
    </td>

    <td class="filename">
        <?= e($image['name']) ?>
    </td>

    <td class="details">
        <strong>Formato:</strong>
        <?= e($image['ext']) ?><br>

        <strong>Risoluzione:</strong>
        <?php if ($image['width'] && $image['height']): ?>
            <?= e((string)$image['width']) ?>
            ×
            <?= e((string)$image['height']) ?> px
        <?php else: ?>
            Non disponibile
        <?php endif; ?>
        <br>

        <strong>Dimensione file:</strong>
        <?= formatSize($image['size']) ?><br>

        <?php if ($image['mime']): ?>
            <strong>MIME:</strong>
            <?= e($image['mime']) ?><br>
        <?php endif; ?>

        <?php if ($image['bits']): ?>
            <strong>Bit per canale:</strong>
            <?= (int)$image['bits'] ?><br>
        <?php endif; ?>

        <?php if ($image['channels']): ?>
            <strong>Canali:</strong>
            <?= (int)$image['channels'] ?>
        <?php endif; ?>
    </td>

    <td>
        <a class="button"
           href="<?= e($image['url']) ?>"
           target="_blank"
           rel="noopener noreferrer">
            Apri immagine ↗
        </a>
    </td>
</tr>
<?php endforeach; ?>

<?php if (empty($images)): ?>
<tr>
    <td colspan="4" class="empty">
        Nessuna immagine trovata nella directory.
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

</div>
</body>
</html>
