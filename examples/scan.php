<?php
/**
 * Универсальный скрипт сканирования файлов.
 * 
 * Основные настройки:
 * 1) ACCESS_BITRIX - укажите true если будет авторизация через bitrix (для админов). Укажите false, если доступ по паролю.
 * 2) ACCESS_PASSWORD - пароль для доступа к скрипту (если не Bitrix).
 * 3) CHUNK_SIZE - сколько файлов читаем за один AJAX-запрос (порция).
 * 4) SKIP_SYMLINKS - пропускать символические ссылки?
 * 5) excludePrefixes - файлы/папки, начинающиеся с этих префиксов, пропускаем.
 * 6) excludeFiles - файлы, которые пропускаем.
 * 7) excludeDirs - папки, которые пропускаем.
 * 8) DEBUG_MODE - включить ли отладку (подробный лог)?
 */

// -------------------------------------
// 1. Конфигурация
// -------------------------------------

define('CHUNK_SIZE', 10);               // Сколько файлов читаем за один AJAX-запрос (порция)
define('ACCESS_BITRIX', true);          // поставить true, если доступ только для админов, false - вход по паролю
define('ACCESS_PASSWORD', '123456');    // Пароль для доступа к скрипту (если не Bitrix)
define('TOKEN_MAX', '800000');         // Максимальное количество токенов в файле (для ограничения размера)
$SKIP_SYMLINKS = false;

// Исключения
$excludePrefixes = ['_', 'test']; // файлы/папки, начинающиеся с этими префиксами, пропускаем
$excludeFiles    = ['composer.lock'];
$excludeDirs     = ['log', 'vendor', 'old'];

// Включить ли отладку (подробный лог)?
define('DEBUG_MODE', false); // можно установить в true для детализации

// -------------------------------------
// 0. Если мы работаем в режиме Bitrix — сначала подключаем ядро
// -------------------------------------
if (defined('ACCESS_BITRIX') && ACCESS_BITRIX) {
    require_once $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php";
    global $USER;
    // если не админ — выходим
    if (!$USER->IsAdmin()) {
        header("HTTP/1.1 403 Forbidden");
        echo "Доступ запрещён";
        exit;
    }
}

// -------------------------------------
// 1. Стартуем сессию (уже после prolog_before)
// -------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------
// 2. Если мы не в режиме Bitrix — проверяем пароль
// -------------------------------------
if (!defined('ACCESS_BITRIX') || !ACCESS_BITRIX) {
    define('ACCESS_PASSWORD', '123456');
    if (empty($_SESSION['is_authenticated'])) {
        if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['password'] ?? '')===ACCESS_PASSWORD) {
            $_SESSION['is_authenticated']=true;
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }
        // форма логина
        echo '<h2>Введите пароль:</h2>';
        if (!empty($_POST)) {
            echo '<p style="color:red;">Неверный пароль</p>';
        }
        echo '<form method="post">
                <input type="password" name="password" required>
                <button>Войти</button>
             </form>';
        exit;
    }
}

// Убедимся, что в сессии есть нужные переменные
if (!isset($_SESSION['SCAN_TMPFILE']))  $_SESSION['SCAN_TMPFILE']  = '';
if (!isset($_SESSION['SCAN_FILECOUNT']))$_SESSION['SCAN_FILECOUNT'] = 0;
if (!isset($_SESSION['SCAN_OFFSET']))   $_SESSION['SCAN_OFFSET']   = 0; 
if (!isset($_SESSION['CUR_FOLDER']))    $_SESSION['CUR_FOLDER']    = '';

// -------------------------------------
// 3. Глобальный лог отладки (если надо)
// -------------------------------------
$GLOBALS['DEBUG_LOG'] = [];
function debugLog($msg) {
    if (DEBUG_MODE) {
        $GLOBALS['DEBUG_LOG'][] = $msg;
    }
}

// -------------------------------------
// 4. AJAX: Обработка одного "чанка" (scanChunk)
// -------------------------------------
if (
    isset($_REQUEST['ajax']) && $_REQUEST['ajax'] === 'Y'
    && isset($_REQUEST['action']) && $_REQUEST['action'] === 'scanChunk'
) {
    // Возвращаем только JSON
    header('Content-Type: application/json; charset=UTF-8');

    $tmpFile   = $_SESSION['SCAN_TMPFILE']   ?? '';
    $total     = (int) ($_SESSION['SCAN_FILECOUNT'] ?? 0);
    $offset    = (int) ($_SESSION['SCAN_OFFSET'] ?? 0);

    // Новый: путь к jsonl-файлу и json-файлу
    $jsonlFile = '';
    $jsonFile = '';
    if ($tmpFile) {
        $jsonlFile = preg_replace('/\.txt$/', '.jsonl', $tmpFile);
        $jsonFile = preg_replace('/\.txt$/', '.json', $tmpFile);
        if (!isset($_SESSION['SCAN_JSONLFILE'])) {
            $_SESSION['SCAN_JSONLFILE'] = $jsonlFile;
        }
        if (!isset($_SESSION['SCAN_JSONFILE'])) {
            $_SESSION['SCAN_JSONFILE'] = $jsonFile;
        }
    }

    // Если список файлов не сформирован
    if (!$tmpFile || !file_exists($tmpFile) || $total <= 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Список файлов не инициализирован или пуст.'
        ]);
        exit;
    }

    // Функция проверки на бинарность
    function isBinaryFile($path) {
        $h = @fopen($path, 'rb');
        if (!$h) return true; // если не удалось открыть, считаем "бинарным", чтоб пропустить
        $chunk = fread($h, 1024);
        fclose($h);
        return (strpos($chunk, "\0") !== false);
    }

    // Чтение N строк из файла, начиная с байтовой позиции
    function readPathsChunk($filePath, $byteOffset, $count) {
        $paths = [];
        $h = fopen($filePath, 'rb');
        fseek($h, $byteOffset);
        $readLines = 0;
        while (!feof($h) && $readLines < $count) {
            $line = fgets($h);
            if ($line === false) break;
            $line = trim($line);
            if ($line !== '') {
                $paths[] = $line;
                $readLines++;
            }
        }
        $newOffset = ftell($h);
        fclose($h);
        return [$paths, $newOffset];
    }

    // Читаем порцию путей
    list($paths, $newOffset) = readPathsChunk($tmpFile, $offset, CHUNK_SIZE);

    if (empty($paths)) {
        // Подсчёт токенов в jsonl-файле
        $tokenCount = 0;
        $jsonlFilePath = $_SESSION['SCAN_JSONLFILE'] ?? '';
        if ($jsonlFilePath && file_exists($jsonlFilePath)) {
            $fh = fopen($jsonlFilePath, 'rb');
            while (($line = fgets($fh)) !== false) {
                $row = json_decode($line, true);
                if (isset($row['content'])) {
                    $tokenCount += str_word_count($row['content']);
                }
            }
            fclose($fh);
        }

        // Формируем JSON-файлы по частям
        $jsonFilePath = $_SESSION['SCAN_JSONFILE'] ?? '';
        $jsonParts = [];
        $jsonPartFiles = [];
        $folderName = $_SESSION['SCAN_FOLDERNAME'] ?? 'result';
        $safeFolder = preg_replace('/[^a-zA-Z0-9_\-]+/u', '_', $folderName);
        $tokenMax = (int)constant('TOKEN_MAX');
        $zipFile = '';
        if ($jsonlFilePath && file_exists($jsonlFilePath) && $jsonFilePath) {
            $fh = fopen($jsonlFilePath, 'rb');
            $currentPart = [];
            $currentTokens = 0;
            $partNum = 1;
            while (($line = fgets($fh)) !== false) {
                $row = json_decode($line, true);
                if (!$row) continue;
                $tokens = isset($row['content']) ? str_word_count($row['content']) : 0;
                // Если добавление строки превысит лимит, сохраняем часть и начинаем новую
                if ($currentTokens + $tokens > $tokenMax && count($currentPart) > 0) {
                    $partFile = dirname($jsonFilePath) . '/' . $safeFolder . '_' . $partNum . '.json';
                    file_put_contents($partFile, json_encode($currentPart, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                    $jsonPartFiles[] = basename($partFile);
                    $partNum++;
                    $currentPart = [];
                    $currentTokens = 0;
                }
                $currentPart[] = $row;
                $currentTokens += $tokens;
            }
            fclose($fh);
            // Сохраняем последнюю часть
            if (count($currentPart) > 0) {
                $partFile = dirname($jsonFilePath) . '/' . $safeFolder . '_' . $partNum . '.json';
                file_put_contents($partFile, json_encode($currentPart, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                $jsonPartFiles[] = basename($partFile);
            }

            // Архивируем все части в zip
            if (count($jsonPartFiles) > 0) {
                $zipFile = dirname($jsonFilePath) . '/' . $safeFolder . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    foreach ($jsonPartFiles as $f) {
                        $zip->addFile(dirname($jsonFilePath) . '/' . $f, $f);
                    }
                    $zip->close();
                } else {
                    $zipFile = '';
                }
            }
        }

        echo json_encode([
            'status'    => 'done',
            'message'   => 'Все файлы обработаны',
            'processed' => 0,
            'total'     => $total,
            'jsonl'     => '',
            'jsonl_file'=> basename($_SESSION['SCAN_JSONLFILE'] ?? ''),
            'json_file' => basename($_SESSION['SCAN_JSONFILE'] ?? ''),
            'json_parts'=> $jsonPartFiles,
            'zip_file'  => $zipFile ? basename($zipFile) : '',
            'token_count' => $tokenCount
        ]);
        $_SESSION['SCAN_OFFSET'] = $newOffset;
        exit;
    }

    // Обрабатываем пути
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $jsonl   = '';
    $count   = 0;

    // Открываем jsonl-файл для дозаписи
    $jsonlFp = @fopen($jsonlFile, 'ab');
    if (!$jsonlFp) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Не удалось открыть файл для записи: ' . $jsonlFile
        ]);
        exit;
    }

    foreach ($paths as $p) {
        // Пропуск бинарных
        if (isBinaryFile($p)) {
            continue;
        }
        $content = @file_get_contents($p);
        if ($content === false) {
            continue;
        }
        // Путь относительно корня
        $rel = ltrim(str_replace($docRoot, '', $p), '/');
        $data = [
            'file'    => $rel,
            'content' => $content
        ];
        $line = json_encode($data, JSON_UNESCAPED_UNICODE)."\n";
        fwrite($jsonlFp, $line);
        $count++;
    }
    fclose($jsonlFp);

    // Обновляем позицию
    $_SESSION['SCAN_OFFSET'] = $newOffset;

    // Выдаём результат
    echo json_encode([
        'status'    => 'ok',
        'processed' => $count,
        'total'     => $total,
        'jsonl'     => '',
        'jsonl_file'=> basename($jsonlFile),
        'json_file' => basename($jsonFile)
        // json_parts не возвращаем на промежуточных чанках
    ]);
    exit;
}

// -------------------------------------
// 5. Функции для формирования списка файлов
// -------------------------------------
function shouldProcessPath($fullPath, &$visitedInodes) {
    global $SKIP_SYMLINKS;

    if ($SKIP_SYMLINKS && is_link($fullPath)) {
        debugLog("[shouldProcessPath] Пропускаем symlink: $fullPath");
        return false;
    }

    $inode = @fileinode($fullPath);
    // если уже видел (и это не сам линк) — пропускаем
    if ($inode && isset($visitedInodes[$inode]) && !is_link($fullPath)) {
        debugLog("[shouldProcessPath] Повтор inode, пропускаем: $fullPath");
        return false;
    }
    // отмечаем любой НЕ-линк, чтобы не заходить туда снова
    if ($inode && !is_link($fullPath)) {
        $visitedInodes[$inode] = true;
    }
    return true;
}

function collectFilePathsToFile($dir, $fp, &$visitedInodes) {
    global $excludePrefixes, $excludeFiles, $excludeDirs;
    
    // Получаем все элементы в папке
    $items = @scandir($dir);
    
    if ($items === false) {
        debugLog("[scandir failed] $dir → " . print_r(error_get_last(), true));
        return;
    }

    if (!is_array($items)) return;

    $hasFiles = false;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        // Пропускаем папки с указанными префиксами
        foreach ($excludePrefixes as $pref) {
            if (strpos($item, $pref) === 0) {
                continue 2;
            }
        }

        // Полный путь к файлу или папке
        $full = $dir . '/' . $item;

        // Логируем текущий обрабатываемый элемент
        debugLog("[collectFilePathsToFile] Проверяем: $full");

        if (!shouldProcessPath($full, $visitedInodes)) {
            continue;
        }

        // Если это папка
        if (is_dir($full)) {
            if (in_array($item, $excludeDirs)) {
                continue;
            }
            // Рекурсивно обрабатываем вложенные папки
            collectFilePathsToFile($full, $fp, $visitedInodes);
        } elseif (is_file($full)) {
            // Если это файл, записываем его путь
            if (in_array($item, $excludeFiles)) {
                continue;
            }
            fwrite($fp, $full . "\n");
            $hasFiles = true;
        }
    }

    if ($hasFiles) {
        debugLog("[collectFilePathsToFile] Папка с файлами: $dir");
    } else {
        debugLog("[collectFilePathsToFile] Папка пуста: $dir");
    }
}

// -------------------------------------
// 6. Обработка формы
// -------------------------------------
$method                 = $_POST['folder_select_method'] ?? 'manual'; 
$currentFolderDropdown  = $_SESSION['CUR_FOLDER'] ?? '';
$docRoot                = realpath($_SERVER['DOCUMENT_ROOT']);

// Сброс
if (isset($_POST['reset'])) {
    $_SESSION['SCAN_TMPFILE']   = '';
    $_SESSION['SCAN_FILECOUNT'] = 0;
    $_SESSION['SCAN_OFFSET']    = 0;
    $_SESSION['CUR_FOLDER']     = '';
    $_SESSION['SCAN_FOLDERNAME'] = '';
    $currentFolderDropdown      = '';
}

// Раскрытие подпапок
if (
    $method === 'dropdown'
    && isset($_POST['expandDropdown'])
    && !isset($_POST['collect_and_scan'])
) {
    $sel   = trim($_POST['selected_subfolder'] ?? '', '/');
    if ($sel !== '') {
        $tryRel  = $currentFolderDropdown 
                   ? "$currentFolderDropdown/$sel" 
                   : $sel;
        $fullTry = $docRoot . '/' . $tryRel;
        if (is_dir($fullTry)) {
            $_SESSION['CUR_FOLDER']    = $tryRel;
            $currentFolderDropdown     = $tryRel;
        }
    }
}

// -------------------------------------
// 7. "Сформировать и сканировать"
// -------------------------------------
$scanMessage = '';
if (isset($_POST['collect_and_scan'])) {
    $_SESSION['SCAN_TMPFILE']   = '';
    $_SESSION['SCAN_FILECOUNT'] = 0;
    $_SESSION['SCAN_OFFSET']    = 0;
    $_SESSION['SCAN_JSONLFILE'] = '';
    $_SESSION['SCAN_FOLDERNAME'] = '';

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $fullPathToScan = '';
    $folderName = '';

    if ($method === 'manual') {
        $input = ltrim($_POST['scan_folder'] ?? '', '/');
        $path  = $docRoot . '/' . $input;
        $folderName = $input !== '' ? basename($input) : 'root';
        if (is_dir($path) && is_readable($path)) {
            $fullPathToScan = $path;
        } else {
            $scanMessage = "Папка «{$path}» не найдена или нет прав на чтение.";
        }
    } else {
        $try = $docRoot . '/' . ltrim($currentFolderDropdown, '/');
        $folderName = $currentFolderDropdown !== '' ? basename($currentFolderDropdown) : 'root';
        if (is_dir($try)) {
            $fullPathToScan = $try;
        }
    }

    // Сохраняем имя папки в сессию
    $_SESSION['SCAN_FOLDERNAME'] = $folderName;

    if (!$fullPathToScan) {
        $scanMessage = "Папка не найдена или не входит в DOCUMENT_ROOT.";
    } else {
        $tmpDir = $docRoot . '/scan_tmp'; 
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }
        // Формируем имя файла по имени папки
        $safeFolder = preg_replace('/[^a-zA-Z0-9_\-]+/u', '_', $folderName);
        $tmpFilePath = $tmpDir . '/scan_' . $safeFolder . '_' . uniqid() . '.txt';

        $fp = @fopen($tmpFilePath, 'wb');
        if (!$fp) {
            $scanMessage = "Не удалось создать временный файл: $tmpFilePath";
        } else {
            $visited = [];
            collectFilePathsToFile($fullPathToScan, $fp, $visited);
            fclose($fp);

            // Сколько строк
            $lineCount = 0;
            $check = fopen($tmpFilePath, 'rb');
            while (!feof($check)) {
                $line = fgets($check);
                if ($line !== false) $lineCount++;
            }
            fclose($check);

            $_SESSION['SCAN_TMPFILE']   = $tmpFilePath;
            $_SESSION['SCAN_FILECOUNT'] = $lineCount;
            $_SESSION['SCAN_OFFSET']    = 0;
            // jsonl-файл тоже с этим именем
            $_SESSION['SCAN_JSONLFILE'] = preg_replace('/\.txt$/', '.jsonl', $tmpFilePath);

            $scanMessage = "Список сформирован. Всего файлов: $lineCount. Начинаем автосканирование...";
        }
    }
}

// Обработка кнопки "На уровень выше"
if (isset($_POST['goUp']) && $currentFolderDropdown) {
    // Разбиваем текущий путь по символу "/"
    $parentFolder = dirname($currentFolderDropdown);
    $_SESSION['CUR_FOLDER'] = $parentFolder === '/' ? '' : $parentFolder;
    $currentFolderDropdown = $_SESSION['CUR_FOLDER'];  // Обновляем текущую папку
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сканер файлов</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
    <h1 class="mb-4">Сканер файлов</h1>

    <!-- Форма -->
    <form method="post" action="" class="mb-4" id="scanForm">
        <div class="mb-3">
            <label class="form-label fw-bold">Способ указания папки:</label>

            <div class="form-check">
                <input 
                    class="form-check-input" 
                    type="radio" 
                    name="folder_select_method" 
                    id="radioManual" 
                    value="manual"
                    <?php if ($method === 'manual') echo 'checked'; ?>
                >
                <label class="form-check-label" for="radioManual">
                    Указать путь вручную ("/" — корень)
                </label>
            </div>

            <div class="ms-3 mt-2" id="manualBlock" style="display:none;">
                <label for="scan_folder" class="form-label">
                    Примеры: "/", "/local", "/catalog/images"
                </label>
                <input 
                    type="text" 
                    name="scan_folder" 
                    id="scan_folder" 
                    class="form-control" 
                    value="<?=htmlspecialchars($_POST['scan_folder'] ?? '')?>"
                    placeholder="Например: /local"
                >
            </div>
        </div>

        <div class="mb-3">
            <div class="form-check">
                <input 
                    class="form-check-input" 
                    type="radio" 
                    name="folder_select_method" 
                    id="radioDropdown"
                    value="dropdown"
                    <?php if ($method === 'dropdown') echo 'checked'; ?>
                >
                <label class="form-check-label" for="radioDropdown">
                    Выбрать из списка
                </label>
            </div>

            <div class="mt-2" id="dropdownBlock" style="display:none;">
                <div>
                <?php
                $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
                if (!$currentFolderDropdown) {
                    echo '<div class="alert alert-secondary p-2 mb-3">
                            <strong>Текущая папка:</strong> Корень сайта
                        </div>';
                } else {
                    echo '<div class="alert alert-secondary p-2 mb-3">
                            <strong>Текущая папка:</strong> /' . htmlspecialchars($currentFolderDropdown) . '
                        </div>';
                }
                // Кнопка для перехода на уровень выше
                if ($currentFolderDropdown) {
                    echo '<button type="submit" name="goUp" class="btn btn-secondary d-block mb-3">На уровень выше</button>';
                }

                // Список подпапок
                $subfolders = [];
                $fullDrop = $docRoot . '/' . trim($currentFolderDropdown, '/');
                if (is_dir($fullDrop)) {
                    $items = @scandir($fullDrop);
                    if (is_array($items)) {
                        foreach ($items as $d) {
                            if ($d === '.' || $d === '..') continue;
                            $td = $fullDrop . '/' . $d;
                            if (is_dir($td)) {
                                $subfolders[] = $d;
                            }
                        }
                    }
                }
                sort($subfolders);

                if (!empty($subfolders)) {
                    ?>
                    <!-- Автоматический сабмит -->
                    <input type="hidden" name="expandDropdown" value="1">
                    <select name="selected_subfolder"
                            class="form-select w-auto d-inline-block"
                            onchange="this.form.submit();">
                        <option value="">-- Выбрать подпапку --</option>
                        <?php foreach ($subfolders as $sf): ?>
                            <option value="<?=htmlspecialchars($sf)?>"><?=$sf?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                } else {
                    echo '<div class="text-muted">Нет подпапок.</div>';
                }
                ?>
            </div>
        </div>

        <div class="mt-4 mb-4">
            <button type="submit" name="collect_and_scan" class="btn btn-primary me-2">
                Сформировать и сканировать
            </button>
            <button type="submit" name="reset" class="btn btn-outline-danger">
                Сбросить
            </button>
        </div>
    </form>

    <!-- Вывод сообщений -->
    <?php if ($scanMessage !== ''): ?>
        <div class="alert alert-info">
            <?=htmlspecialchars($scanMessage)?>
        </div>
    <?php endif; ?>

    <?php
    // Проверяем наличие списка файлов для сканирования
    $total   = (int)$_SESSION['SCAN_FILECOUNT'];
    $tmpFile = $_SESSION['SCAN_TMPFILE'];
    $offset  = $_SESSION['SCAN_OFFSET'];
    $jsonlFile = $_SESSION['SCAN_JSONLFILE'] ?? '';
    $scanFolderName = $_SESSION['SCAN_FOLDERNAME'] ?? 'result';
    // Формируем имя для скачивания
    $downloadFileName = preg_replace('/[^a-zA-Z0-9_\-]+/u', '_', $scanFolderName) . '.jsonl';
    $downloadJsonFileName = preg_replace('/[^a-zA-Z0-9_\-]+/u', '_', $scanFolderName) . '.json';

    if ($tmpFile && file_exists($tmpFile) && $total > 0):
    ?>
        <div id="scanProgress" class="alert alert-warning">
            Автоматический процесс сканирования...<br>
            Обработано: <span id="processedCount">0</span> из <?=$total?>
        </div>

        <div id="downloadBlock" style="display:none;" class="mb-3">
            <div class="alert alert-success">
                <b>Готово!</b> 
                <a id="downloadLink" href="" download="<?=htmlspecialchars($downloadFileName)?>">Скачать результат (JSONL)</a>
                <span id="jsonDownloadSpan" style="display:none;">
                    &nbsp;|&nbsp;
                    <a id="downloadJsonLink" href="" download="<?=htmlspecialchars($downloadJsonFileName)?>">Скачать результат (JSON)</a>
                </span>
                <span id="zipDownloadSpan" style="display:none;">
                    <br><b>Архив:</b>
                    <a id="downloadZipLink" href="" download="">Скачать архив (ZIP)</a>
                </span>
                <span id="jsonPartsSpan" style="display:none;">
                    <br><b>Части:</b>
                    <span id="jsonPartsLinks"></span>
                </span>
                <div id="tokenCountBlock" class="mt-2"></div>
            </div>
        </div>
        <script>
        function formatNumber(n) {
            return n.toLocaleString('ru-RU').replace(/,/g, ' ');
        }
        let totalFiles = <?=$total?>;
        let isScanning = true;
        let jsonlFile = <?=json_encode($jsonlFile ? basename($jsonlFile) : '')?>;
        let jsonFile = <?=json_encode($_SESSION['SCAN_JSONFILE'] ? basename($_SESSION['SCAN_JSONFILE']) : '')?>;
        let downloadFileName = <?=json_encode($downloadFileName)?>;
        let downloadJsonFileName = <?=json_encode($downloadJsonFileName)?>;
        function scanNextChunk() {
            if (!isScanning) return;
            let xhr = new XMLHttpRequest();
            xhr.open('GET', '?ajax=Y&action=scanChunk&_t=' + (new Date().getTime()), true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        let resp = JSON.parse(xhr.responseText);
                        let sp = document.getElementById('scanProgress');

                        if (resp.status === 'error') {
                            sp.classList.remove('alert-warning', 'alert-success');
                            sp.classList.add('alert-danger');
                            sp.textContent = resp.message; 
                            isScanning = false;
                        } 
                        else if (resp.status === 'done') {
                            sp.classList.remove('alert-warning', 'alert-danger');
                            sp.classList.add('alert-success');
                            sp.textContent = 'Все файлы обработаны!';
                            isScanning = false;
                            // Показать ссылку на скачивание и токены
                            if (resp.jsonl_file) {
                                let block = document.getElementById('downloadBlock');
                                let link = document.getElementById('downloadLink');
                                link.href = '/scan_tmp/' + resp.jsonl_file;
                                link.setAttribute('download', downloadFileName);
                                // JSON download
                                if (resp.json_file) {
                                    let jsonSpan = document.getElementById('jsonDownloadSpan');
                                    let jsonLink = document.getElementById('downloadJsonLink');
                                    jsonLink.href = '/scan_tmp/' + resp.json_file;
                                    jsonLink.setAttribute('download', downloadJsonFileName);
                                    jsonSpan.style.display = '';
                                }
                                // ZIP download
                                if (resp.zip_file) {
                                    let zipSpan = document.getElementById('zipDownloadSpan');
                                    let zipLink = document.getElementById('downloadZipLink');
                                    zipLink.href = '/scan_tmp/' + resp.zip_file;
                                    zipLink.setAttribute('download', resp.zip_file);
                                    zipSpan.style.display = '';
                                }
                                // JSON parts
                                if (resp.json_parts && resp.json_parts.length > 0) {
                                    let partsSpan = document.getElementById('jsonPartsSpan');
                                    let partsLinks = document.getElementById('jsonPartsLinks');
                                    partsLinks.innerHTML = '';
                                    resp.json_parts.forEach(function(fname, idx) {
                                        let a = document.createElement('a');
                                        a.href = '/scan_tmp/' + fname;
                                        a.download = fname;
                                        a.textContent = fname;
                                        if (idx > 0) partsLinks.appendChild(document.createTextNode(', '));
                                        partsLinks.appendChild(a);
                                    });
                                    partsSpan.style.display = '';
                                }
                                block.style.display = '';
                                if (resp.token_count !== undefined) {
                                    document.getElementById('tokenCountBlock').textContent =
                                        'Токенов (слов) в файле: ' + formatNumber(resp.token_count);
                                }
                            }
                        }
                        else if (resp.status === 'ok') {
                            let processedSpan = document.getElementById('processedCount');
                            let pCount = parseInt(processedSpan.innerText, 10);
                            pCount += resp.processed;
                            processedSpan.innerText = pCount;

                            if (pCount < totalFiles) {
                                scanNextChunk();
                            } else {
                                sp.classList.remove('alert-warning', 'alert-danger');
                                sp.classList.add('alert-success');
                                sp.textContent = 'Все файлы обработаны!';
                                isScanning = false;
                                // Показать ссылку на скачивание
                                if (resp.jsonl_file) {
                                    let block = document.getElementById('downloadBlock');
                                    let link = document.getElementById('downloadLink');
                                    link.href = '/scan_tmp/' + resp.jsonl_file;
                                    link.setAttribute('download', downloadFileName);
                                    // JSON download
                                    if (resp.json_file) {
                                        let jsonSpan = document.getElementById('jsonDownloadSpan');
                                        let jsonLink = document.getElementById('downloadJsonLink');
                                        jsonLink.href = '/scan_tmp/' + resp.json_file;
                                        jsonLink.setAttribute('download', downloadJsonFileName);
                                        jsonSpan.style.display = '';
                                    }
                                    // ZIP download
                                    if (resp.zip_file) {
                                        let zipSpan = document.getElementById('zipDownloadSpan');
                                        let zipLink = document.getElementById('downloadZipLink');
                                        zipLink.href = '/scan_tmp/' + resp.zip_file;
                                        zipLink.setAttribute('download', resp.zip_file);
                                        zipSpan.style.display = '';
                                    }
                                    block.style.display = '';
                                }
                            }
                        }
                    } catch (e) {
                        let sp = document.getElementById('scanProgress');
                        sp.classList.remove('alert-warning', 'alert-success');
                        sp.classList.add('alert-danger');
                        sp.textContent = 'Ошибка парсинга ответа: ' + e;
                        isScanning = false;
                    }
                }
            };
            xhr.send();
        }

        // Запускаем первый запрос
        scanNextChunk();
        </script>
    <?php endif; ?>

    <?php if (DEBUG_MODE): ?>
        <hr>
        <pre><?= print_r($GLOBALS['DEBUG_LOG'], true) ?></pre>
    <?php endif; ?>

</div>

<!-- Bootstrap JS (опционально) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Переключение блоков (radio manual / dropdown)
(function(){
    let radMan = document.getElementById('radioManual');
    let radDrop = document.getElementById('radioDropdown');
    let manBlock = document.getElementById('manualBlock');
    let dropBlock = document.getElementById('dropdownBlock');

    function toggleMeth() {
        if (radMan.checked) {
            manBlock.style.display = '';
            dropBlock.style.display = 'none';
        } else {
            manBlock.style.display = 'none';
            dropBlock.style.display = '';
        }
    }

    radMan.addEventListener('change', toggleMeth);
    radDrop.addEventListener('change', toggleMeth);
    toggleMeth();
})();
</script>

</body>
</html>