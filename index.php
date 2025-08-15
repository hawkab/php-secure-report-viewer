<?php
header("X-Robots-Tag: noindex");
// Конфигурация
$SECRET_KEYS = [
    '123456' => [
        'code' => 'testCode',
        'name' => 'Тестовое наименование организации'
    ],
];

$BASE_DIR = __DIR__ . '/reports/';
$TEMP_DIR = __DIR__ . '/temp/';

//Получение информации о клиенте
$query = @unserialize(file_get_contents('http://ip-api.com/php/'.$_SERVER[REMOTE_ADDR]));
$countryCode = 'EN';
if ($query && $query['status'] == 'success') {
    $line = date('Y-m-d H:i:s') . " - $_SERVER[REMOTE_ADDR]" . 
        ", city: " . $query['city'] . 
        ", provider: " . $query['org'] . 
        ", provider-reg-name: " . $query['isp'] . 
        ", user-agent: " . $_SERVER['HTTP_USER_AGENT'];
        $countryCode = $query['countryCode'];
}
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    
//Логирование доступа к странице
file_put_contents('reports/visitors.log', $line . PHP_EOL, FILE_APPEND);

// Проверка ключа и определение организации
$org_data = null;
if (isset($_GET['key']) && isset($SECRET_KEYS[$_GET['key']])) {
    $org_data = $SECRET_KEYS[$_GET['key']];
    if ($countryCode !== 'RU') {
        mail("(put receiver emails here)",
            "reports-forbidden access", "Кто-то не из РФ подобрал ключ и попытался получить доступ к отчётам " . $org_data['code'] . " с ключом: '" . $_GET['key'] . "'.<br><br>Информация о клиенте: " . $line, $headers);
        header('HTTP/1.0 403 Forbidden');
        sleep(3);
        die('Access Denied');
    }
    mail("(put receiver emails here)",
            "reports-success access", "Кто-то успешно получил доступ к отчётам " . $org_data['code'] . " с ключом: '" . $_GET['key'] . "'.<br><br>Информация о клиенте: " . $line, $headers);
} else {
    mail("(put receiver emails here)",
            "reports-forbidden access", "Кто-то неуспешно попытался получить доступ к отчётам с ключом: '" . $_GET['key'] . "'.<br><br>Информация о клиенте: " . $line, $headers);
    sleep(3);
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied');
}

$REPORTS_DIR = $BASE_DIR . $org_data['code'] . '/';
$LOGO_PATH = $REPORTS_DIR . 'logo.png';
$logo_data = file_exists($LOGO_PATH) ? base64_encode(file_get_contents($LOGO_PATH)) : null;

// Создаём директории при необходимости
if (!file_exists($REPORTS_DIR)) mkdir($REPORTS_DIR, 0755, true);
if (!file_exists($TEMP_DIR)) mkdir($TEMP_DIR, 0755, true);

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset'])) {
        $_POST['filter_month'] = '';
    }
    
    if (isset($_POST['download']) && !empty($_POST['files'])) {
        $selected_files = is_array($_POST['files']) ? $_POST['files'] : [];
        $valid_files = [];
        
        foreach ($selected_files as $file) {
            $file_path = $REPORTS_DIR . basename($file);
            if (file_exists($file_path)) {
                $valid_files[] = $file_path;
            }
        }
        
        if (count($valid_files) === 1) {
            $file = $valid_files[0];
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            readfile($file);
            exit;
        } elseif (count($valid_files) > 1) {
            $zip_name = $org_data['code'] .'_reports_' . date('Y-m-d_H-i') . '.zip';
            $zip_path = $TEMP_DIR . $zip_name;
            
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE) === true) {
                foreach ($valid_files as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
                
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_name . '"');
                readfile($zip_path);
                unlink($zip_path);
                exit;
            }
        }
    }
}

// Получаем и фильтруем файлы
$all_files = glob($REPORTS_DIR . '*.pdf');
$files = [];
$filter_month = $_POST['filter_month'] ?? '';

if (is_array($all_files)) {
    foreach ($all_files as $file) {
        $filename = basename($file);
        
        if (preg_match('/(\d{4}-\d{2})-\d{2}/', $filename, $matches)) {
            $file_month = $matches[1];
            
            if (empty($filter_month) || $file_month === $filter_month) {
                $files[] = [
                    'name' => $filename,
                    'path' => $file,
                    'month' => $file_month,
                    'size' => filesize($file)
                ];
            }
        }
    }
}

// Сортировка
$sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'desc';
usort($files, function($a, $b) use ($sort_order) {
    return $sort_order === 'asc' 
        ? strcmp($a['name'], $b['name'])
        : strcmp($b['name'], $a['name']);
});
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    
    <meta name="robots" content="noindex, nofollow"/>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчёты <?= htmlspecialchars($org_data['name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { width: 30%; min-width: 500px; }
        .header-container { position: fixed; top: 0; left: 0; right: 0; background: white; padding: 10px; box-shadow: 0 -2px 5px rgba(0,0,0,0.1); z-index: 1000;}
        .header { display: flex; align-items: center; margin-bottom: 20px;}
        .logo { width: 64px; height: 64px; margin-right: 15px; object-fit: contain; }
        .report-list { margin-bottom: 60px; margin-top: 170px; border: 1px solid #ddd; border-radius: 4px; }
        .report-header { display: flex; padding: 10px; background: #f5f5f5; font-weight: bold; border-bottom: 1px solid #ddd; }
        .report-month { padding: 8px 10px; background: #e9e9e9; margin-top: 15px; font-weight: bold; }
        .report-item { display: flex; padding: 10px; border-bottom: 1px solid #eee; align-items: center; }
        .report-item:last-child { border-bottom: none; }
        .report-item:hover { background-color: #f9f9f9; }
        .col-checkbox { width: 30px; }
        .col-name { flex: 3; }
        .col-size { flex: 1; text-align: right; }
        .sortable { cursor: pointer; }
        .sortable:hover { text-decoration: underline; }
        .actions { display: flex; gap: 10px; align-items: center; position: fixed; bottom: 0; left: 0; right: 0; background: white; padding: 10px; box-shadow: 0 -2px 5px rgba(0,0,0,0.1); z-index: 1000;}
        .filter { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; }
        button { padding: 8px 15px; cursor: pointer; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        input[type="month"] { padding: 6px; width: 150px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <div class="header">
            <?php if ($logo_data): ?>
                <img src="data:image/png;base64,<?= $logo_data ?>" class="logo" alt="Логотип">
            <?php endif; ?>
            <h1>Отчёты для <?= htmlspecialchars($org_data['name']) ?></h1>
            </div>
            <div>
                <form method="post" class="filter" id="filter-form">
                    <input type="month" name="filter_month" value="<?= htmlspecialchars($filter_month) ?>">
                    <button type="submit" name="filter">Найти</button>
                    <button type="submit" name="reset">Сбросить</button>
                    <input type="hidden" name="sort_order" id="sort_order" value="<?= $sort_order ?>">
                </form>
            </div>
        </div>
        
        <form method="post" id="reports-form">
            <div class="report-list" id="report-list">
                <div class="report-header">
                    <div class="col-checkbox"></div>
                    <div class="col-name sortable" onclick="toggleSort()">Имя <?= $sort_order === 'asc' ? '↑' : '↓' ?></div>
                    <div class="col-size">Размер</div>
                </div>
                
                <?php if (!empty($files)): ?>
                    <?php
                    $current_month = null;
                    foreach ($files as $file):
                        if ($current_month !== $file['month']):
                            $current_month = $file['month'];
                    ?>
                            <div class="report-month"><?= date('F Y', strtotime($current_month . '-01')) ?></div>
                        <?php endif; ?>
                        
                        <div class="report-item">
                            <div class="col-checkbox">
                                <input type="checkbox" name="files[]" value="<?= htmlspecialchars($file['name']) ?>" 
                                       id="file-<?= htmlspecialchars(md5($file['name'])) ?>">
                            </div>
                            <label for="file-<?= htmlspecialchars(md5($file['name'])) ?>" class="col-name"><?= htmlspecialchars($file['name']) ?></label>
                            <div class="col-size"><?= round($file['size'] / 1024) ?> KB</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="report-item" style="padding-left: 15px;">
                        <?= empty($all_files) ? 'В папке нет отчётов' : 'Нет отчётов за выбранный период' ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="actions">
                <button type="button" onclick="toggleAll()" id="selectionToggle">Выделить всё</button>
                <button type="submit" name="download" id="download-btn" disabled>Скачать выбранное</button>
            </div>
        </form>
    </div>
    
    <script>
        // Управление кнопкой скачивания
        const form = document.getElementById('reports-form');
        const downloadBtn = document.getElementById('download-btn');
        
        form.addEventListener('change', updateDownloadButton);
        function updateDownloadButton() {
            const checked = document.querySelectorAll('#reports-form input[type="checkbox"]:checked').length > 0;
            downloadBtn.disabled = !checked;
        }
        
        // Выделение всех/снятие выделения
        function toggleAll(checked) {
            var buttongToggle = document.getElementById('selectionToggle');
            if (checked === undefined) {
                checked = buttongToggle.innerHTML === 'Выделить всё';
                buttongToggle.innerHTML = buttongToggle.innerHTML === 'Выделить всё' ? 'Снять выделение' : 'Выделить всё';
            }
            document.querySelectorAll('#reports-form input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = checked;
            });
            updateDownloadButton();
            
        }
        
        // Сортировка
        function toggleSort() {
            var sortOrderInput = document.getElementById('sort_order');
            sortOrderInput.value = sortOrderInput.value === 'asc' ? 'desc' : 'asc';
            var filterForm = document.getElementById('filter-form');
            filterForm.submit();
        }
        
        // Сброс галочек после скачивания
        form.addEventListener('submit', function() {
            setTimeout(() => {
                toggleAll(false);
            }, 100);
        });
    </script>
</body>
</html>
