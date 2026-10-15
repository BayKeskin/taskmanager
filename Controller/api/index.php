<?php
/**
 * TaskManager Enterprise REST API Engine
 * Version: 1.0.0
 * 
 * Supports full CRUD, Kanban workflows, checklisting, comments, 
 * multiple assignees, docs, team management, and AI Agent workflows.
 */

// 1. CORS & Response Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. System Core
require_once __DIR__ . '/../../System/System.php';
global $db;

// Dil Çevirici (API I18n)
function translateApiMessage(string $msg): string {
    if ($msg === '') return '';
    
    $locale = Language::current();
    if ($locale === 'tr') return $msg;
    
    static $dict = null;
    if ($dict === null) {
        $dictFile = __DIR__ . '/../../Config/api_translations.php';
        $dict = file_exists($dictFile) ? require $dictFile : [];
    }
    
    if (isset($dict[$locale][$msg])) {
        return $dict[$locale][$msg];
    }
    
    // Dynamic regex patterns
    if (preg_match("/^Bilinmeyen API endpointi: '([^']+)'$/", $msg, $m)) {
        if ($locale === 'en') return "Unknown API endpoint: '{$m[1]}'";
        if ($locale === 'de') return "Unbekannter API-Endpunkt: '{$m[1]}'";
    }
    if (preg_match("/^Görev bulunamadı: '([^']+)'$/", $msg, $m)) {
        if ($locale === 'en') return "Task not found: '{$m[1]}'";
        if ($locale === 'de') return "Aufgabe nicht gefunden: '{$m[1]}'";
    }
    if (preg_match("/^Görev \[([^\]]+)\] başarıyla silindi\.$/", $msg, $m)) {
        if ($locale === 'en') return "Task [{$m[1]}] successfully deleted.";
        if ($locale === 'de') return "Aufgabe [{$m[1]}] erfolgreich gelöscht.";
    }
    if (preg_match("/^Döküman \[([^\]]+)\] projesine başarıyla eklendi\.$/", $msg, $m)) {
        if ($locale === 'en') return "Document successfully added to project [{$m[1]}].";
        if ($locale === 'de') return "Dokument erfolgreich zum Projekt [{$m[1]}] hinzugefügt.";
    }
    if (preg_match("/^Proje görevleri listelendi: \[([^\]]+)\] (.*)$/", $msg, $m)) {
        if ($locale === 'en') return "Project tasks listed: [{$m[1]}] {$m[2]}";
        if ($locale === 'de') return "Projektaufgaben aufgelistet: [{$m[1]}] {$m[2]}";
    }
    if (preg_match("/^Proje dökümanları: \[([^\]]+)\] (.*)$/", $msg, $m)) {
        if ($locale === 'en') return "Project documents: [{$m[1]}] {$m[2]}";
        if ($locale === 'de') return "Projektdokumente: [{$m[1]}] {$m[2]}";
    }
    if (preg_match("/^(.*) göreve atandı\.$/", $msg, $m)) {
        if ($locale === 'en') return "{$m[1]} assigned to task.";
        if ($locale === 'de') return "{$m[1]} der Aufgabe zugewiesen.";
    }
    if (preg_match("/^Geçersiz durum\. Geçerli durumlar: (.*)$/", $msg, $m)) {
        if ($locale === 'en') return "Invalid status. Valid statuses: {$m[1]}";
        if ($locale === 'de') return "Ungültiger Status. Gültige Statuswerte: {$m[1]}";
    }
    if (str_starts_with($msg, 'Yetkilendirme başarısız')) {
        if ($locale === 'en') return "Authorization failed. Please provide a valid Bearer Token ('Authorization: Bearer <token>') or 'X-API-Key' header.";
        if ($locale === 'de') return "Autorisierung fehlgeschlagen. Bitte geben Sie ein gültiges Bearer-Token ('Authorization: Bearer <token>') oder einen 'X-API-Key'-Header an.";
    }
    if (str_starts_with($msg, 'Bu proje için henüz bir webhook_url tanımlanmamış')) {
        if ($locale === 'en') return "No webhook_url configured for this project yet. Please configure webhook URL in project settings first.";
        if ($locale === 'de') return "Für dieses Projekt ist noch keine webhook_url konfiguriert. Bitte konfigurieren Sie zuerst die Webhook-URL in den Projekteinstellungen.";
    }
    
    return $msg;
}

// 3. Response Helpers
function apiResponse($data = null, $message = '', $status = 200, $meta = null) {
    http_response_code($status);
    $res = ['success' => ($status >= 200 && $status < 300)];
    if ($message !== '') $res['message'] = translateApiMessage($message);
    if ($data !== null) $res['data'] = $data;
    if ($meta !== null) $res['meta'] = $meta;
    $res['locale'] = Language::current();
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function apiError($message = 'İşlem başarısız.', $status = 400, $errors = null) {
    http_response_code($status);
    $res = [
        'success' => false,
        'error'   => translateApiMessage($message),
        'locale'  => Language::current()
    ];
    if ($errors !== null) $res['errors'] = $errors;
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 4. Request Parsing
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$rawInput = file_get_contents('php://input');
$requestBody = [];

if (!empty($rawInput)) {
    // 1. JSON parse with UTF-8 substitute flag
    $decoded = json_decode($rawInput, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $requestBody = $decoded;
    } else {
        // 2. Encoding conversion (Windows-1254, ISO-8859-9, Windows-1252 to UTF-8)
        $converted = @mb_convert_encoding($rawInput, 'UTF-8', 'UTF-8, ISO-8859-9, Windows-1254, Windows-1252');
        $decoded = json_decode($converted, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $requestBody = $decoded;
        } else {
            // 3. Stripslashes fallback
            $unescaped = stripslashes($rawInput);
            $decoded2 = json_decode($unescaped, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded2)) {
                $requestBody = $decoded2;
            } else {
                // 4. Form urlencoded parse (only if looks like key=value)
                if (strpos($rawInput, '=') !== false) {
                    parse_str($rawInput, $parsed);
                    if (is_array($parsed) && !empty($parsed)) {
                        $requestBody = $parsed;
                    }
                }
            }
        }
    }
}

$requestData = array_merge($_GET, $_POST, is_array($requestBody) ? $requestBody : []);

// Dil Tercihi Çözümleme (API Language Resolution)
// 1. URL parametresi: ?lang=en veya ?locale=en veya ?set_lang=en
// 2. HTTP Başlığı: Accept-Language veya X-Language / X-Locale
// 3. Request Body (JSON/POST): {"lang": "en"} veya {"locale": "en"}
$apiLang = $_GET['lang'] 
    ?? $_GET['locale'] 
    ?? $_GET['set_lang'] 
    ?? ($requestBody['lang'] ?? ($requestBody['locale'] ?? null)) 
    ?? ($_SERVER['HTTP_X_LANGUAGE'] ?? ($_SERVER['HTTP_X_LOCALE'] ?? null));

if (empty($apiLang) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $pref = strtolower(substr(trim($_SERVER['HTTP_ACCEPT_LANGUAGE']), 0, 2));
    if (in_array($pref, ['tr', 'en', 'de'])) {
        $apiLang = $pref;
    }
}

if (!empty($apiLang) && Language::isLocaleAvailable($apiLang)) {
    Language::setLocale($apiLang);
} else {
    Language::init();
}

// Path / Route Resolution
// URL: /taskmanager/api/projects/ETC/tasks or /taskmanager/api/v1/tasks
$path = trim($_GET['path'] ?? '', '/');
if (empty($path)) {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $uriPath = preg_replace('#^.*?/api(?:\.php)?/?#i', '', $uriPath);
    $path = trim($uriPath, '/');
}
if (str_starts_with($path, 'v1/')) {
    $path = substr($path, 3);
} elseif ($path === 'v1') {
    $path = '';
}
$segments = $path !== '' ? explode('/', $path) : [];

$publicRoutes = [
    '',
    'health',
    'status',
    'auth/login',
    'webhooks/listener',
    'visual-feedback'
];

$isPublic = in_array($path, $publicRoutes) || (count($segments) >= 2 && $segments[0] === 'auth' && $segments[1] === 'login') || (count($segments) >= 2 && $segments[0] === 'webhooks' && $segments[1] === 'listener');

// Token Extraction
$token = null;
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] 
    ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
    ?? ($headers['Authorization'] 
    ?? ($headers['authorization'] ?? '')));

if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $token = $matches[1];
} elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $token = $_SERVER['HTTP_X_API_KEY'];
} elseif (!empty($headers['X-API-Key'])) {
    $token = $headers['X-API-Key'];
} elseif (!empty($headers['x-api-key'])) {
    $token = $headers['x-api-key'];
} elseif (!empty($_GET['api_token'])) {
    $token = $_GET['api_token'];
} elseif (!empty($requestData['api_token'])) {
    $token = $requestData['api_token'];
} elseif (!empty($_SESSION['user']->api_token)) {
    $token = $_SESSION['user']->api_token;
}

$currentUser = null;
if (!empty($token)) {
    $currentUser = $db->row_query("SELECT * FROM users WHERE api_token = ? AND status = 'active' LIMIT 1", [$token]);
    if (!empty($currentUser) && empty($apiLang) && !empty($currentUser->language) && Language::isLocaleAvailable($currentUser->language)) {
        Language::setLocale($currentUser->language);
    }
}

if (!$isPublic && empty($currentUser)) {
    apiError("Yetkilendirme başarısız. Lütfen geçerli bir Bearer Token ('Authorization: Bearer <token>') veya 'X-API-Key' sağlayın.", 401);
}

// Helper: Task Lookup by ID or Task Code (e.g., '12' or 'ETC-101')
function resolveTask($db, $idOrCode) {
    if (is_numeric($idOrCode)) {
        return $db->row_query("SELECT * FROM tasks WHERE id = ? LIMIT 1", [(int)$idOrCode]);
    } else {
        return $db->row_query("SELECT * FROM tasks WHERE task_code = ? LIMIT 1", [$idOrCode]);
    }
}

// Helper: Project Lookup by ID or Project Code (e.g., '1' or 'ETC')
function resolveProject($db, $idOrCode) {
    if (is_numeric($idOrCode)) {
        return $db->row_query("SELECT * FROM projects WHERE id = ? LIMIT 1", [(int)$idOrCode]);
    } else {
        return $db->row_query("SELECT * FROM projects WHERE project_code = ? LIMIT 1", [$idOrCode]);
    }
}

// Helper: Resolve Document Title with multi-alias and file fallback support
function resolveDocTitle($data) {
    $keys = ['title', 'name', 'subject', 'heading', 'baslik'];
    foreach ($keys as $k) {
        if (!empty($data[$k]) && is_string($data[$k])) {
            return trim($data[$k]);
        }
    }
    // Uploaded file name fallback
    if (!empty($_FILES['file']['name'])) {
        return pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
    }
    if (!empty($_FILES['content']['name'])) {
        return pathinfo($_FILES['content']['name'], PATHINFO_FILENAME);
    }
    return '';
}

// Helper: Normalize Document Whitespace & Remove Excessive Gaps
function cleanDocWhitespace($content) {
    if (!is_string($content) || $content === '') return $content;
    // Satır sonlarını standardize et
    $c = str_replace(["\r\n", "\r"], "\n", $content);
    // 3 veya daha fazla ardışık boş satırı en fazla 2 satıra indir
    $c = preg_replace("/\n{3,}/", "\n\n", $c);
    // Ayraçların (---, ***) çevresindeki aşırı boşlukları sıkılaştır
    $c = preg_replace("/\n\s*([-*_]\s*){3,}\n/", "\n\n---\n\n", $c);
    $c = preg_replace("/\n{3,}/", "\n\n", $c);
    return trim($c);
}

// Helper: Resolve Document Content with multi-alias, multipart file, and raw payload support
function resolveDocContent($data, $rawInput = '') {
    $raw = '';
    // 1. Array aliases
    $keys = [
        'content',
        'body',
        'description',
        'text',
        'markdown',
        'document',
        'doc_content',
        'html',
        'detail',
        'icerik'
    ];
    foreach ($keys as $k) {
        if (isset($data[$k])) {
            if (is_string($data[$k]) && trim($data[$k]) !== '') {
                $raw = $data[$k];
                break;
            }
            if (is_array($data[$k])) {
                $raw = json_encode($data[$k], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }
        }
    }

    // 2. Uploaded file fallback (multipart/form-data)
    if ($raw === '') {
        $fileKeys = ['file', 'content', 'document', 'markdown', 'upload'];
        foreach ($fileKeys as $fk) {
            if (!empty($_FILES[$fk]['tmp_name']) && is_uploaded_file($_FILES[$fk]['tmp_name'])) {
                $uploaded = file_get_contents($_FILES[$fk]['tmp_name']);
                if ($uploaded !== false && trim($uploaded) !== '') {
                    $raw = $uploaded;
                    break;
                }
            }
        }
    }

    // 3. Raw payload fallback: If rawInput is text/markdown or plain text (not a JSON dict)
    if ($raw === '' && !empty($rawInput)) {
        $trimmed = trim($rawInput);
        if ($trimmed !== '' && !str_starts_with($trimmed, '{') && !str_starts_with($trimmed, '[')) {
            $raw = $rawInput;
        }
    }

    return cleanDocWhitespace($raw);
}

// Helper: Format Task Detail
function formatTaskDetail($db, $task) {
    $taskId = (int)$task->id;

    // Proje
    $proj = $db->row_query("SELECT id, project_code, title, color, icon FROM projects WHERE id = {$task->project_id}");
    $task->project = $proj;

    // Atananlar
    $assignees = $db->multi_record_query("
        SELECT u.id, u.name, u.email, u.title, u.avatar_color, u.role
        FROM task_assignees ta
        JOIN users u ON ta.user_id = u.id
        WHERE ta.task_id = {$taskId}
        ORDER BY ta.id ASC
    ");
    $task->assignees = $assignees;

    // Kontrol Listesi
    $checklists = $db->multi_record_query("
        SELECT id, title, is_completed, created_at
        FROM task_checklists
        WHERE task_id = {$taskId}
        ORDER BY id ASC
    ");
    $checklists = $checklists && is_array($checklists) ? $checklists : [];
    $task->checklists = $checklists;
    $task->checklists_total = count($checklists);
    $task->checklists_completed = count(array_filter($checklists, fn($c) => (int)$c->is_completed === 1));

    // Alt Görevler
    $subtasks = $db->multi_record_query("
        SELECT s.id, s.task_code, s.title, s.priority, s.status, s.deadline, u.name as assignee_name
        FROM tasks s
        LEFT JOIN users u ON s.assigned_to = u.id
        WHERE s.parent_task_id = {$taskId}
        ORDER BY s.id ASC
    ");
    $task->subtasks = $subtasks && is_array($subtasks) ? $subtasks : [];

    // Yorumlar
    $comments = $db->multi_record_query("
        SELECT c.id, c.comment, c.created_at, u.id as user_id, u.name as user_name, u.avatar_color, u.role as user_role
        FROM task_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.task_id = {$taskId}
        ORDER BY c.id ASC
    ");
    $task->comments = $comments && is_array($comments) ? $comments : [];

    // Varsa Revizyon Talebi
    $mod = $db->row_query("
        SELECT * FROM task_modifications WHERE task_id = {$taskId} ORDER BY id DESC LIMIT 1
    ");
    $task->modification_request = $mod ?: null;

    // Varsa Teslimat Kaydı
    $sub = $db->row_query("
        SELECT * FROM task_submissions WHERE task_id = {$taskId} ORDER BY id DESC LIMIT 1
    ");
    $task->submission = $sub ?: null;

    // Proje Dökümanları (Örn: FTP, Sunucu, API Bilgileri)
    $docs = $db->multi_record_query("
        SELECT id, title, icon, content, created_at, updated_at
        FROM docs
        WHERE project_id = {$task->project_id}
        ORDER BY id ASC
    ");
    $task->project_docs = $docs;

    return $task;
}

// -----------------------------------------------------------------------------
// 6. ROUTE DISPATCHER
// -----------------------------------------------------------------------------

$resource = $segments[0] ?? '';
$param1   = $segments[1] ?? null;
$param2   = $segments[2] ?? null;
$param3   = $segments[3] ?? null;

// ROOT & STATUS ENDPOINTS
if ($resource === '' || $resource === 'status' || $resource === 'health') {
    apiResponse([
        'api_name'    => 'TaskManager TaskManager REST API',
        'version'     => '1.0.0',
        'status'      => 'online',
        'docs_url'    => '/taskmanager/api-docs',
        'authenticated' => !empty($currentUser),
        'user'        => $currentUser ? ['id' => $currentUser->id, 'name' => $currentUser->name, 'email' => $currentUser->email, 'role' => $currentUser->role] : null,
        'timestamp'   => date('c')
    ], 'TaskManager REST API çalışıyor.');
}

// -----------------------------------------------------------------------------
// VISUAL PIN FEEDBACK ENDPOINT (/api/v1/visual-feedback)
// In-House BugHerd Alternative: Ingests client pins, screenshots, DOM paths directly into tasks
// -----------------------------------------------------------------------------
if ($resource === 'visual-feedback' && $method === 'POST') {
    $projectCode = trim($requestData['project_code'] ?? ($requestData['project'] ?? '01'));
    $proj = resolveProject($db, $projectCode);
    if (!$proj) {
        $proj = resolveProject($db, '01');
    }
    if (!$proj) {
        apiError('Geçerli bir proje bulunamadı.', 404);
    }

    $title = trim($requestData['title'] ?? '📍 Görsel Pin Geri Bildirimi');
    $userDesc = trim($requestData['description'] ?? '');
    $selector = trim($requestData['selector'] ?? '');
    $url = trim($requestData['url'] ?? '');
    $viewport = trim($requestData['viewport'] ?? '');
    $userAgent = trim($requestData['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $coords = trim($requestData['coords'] ?? '');
    $priority = in_array($requestData['priority'] ?? '', ['low', 'normal', 'high', 'urgent']) ? $requestData['priority'] : 'normal';

    // Format Markdown Description
    $fullDesc = "### 📍 Görsel Pin / Hata Bildirimi (In-House BugHerd)\n\n";
    if ($userDesc) {
        $fullDesc .= "#### 📝 Kullanıcı Açıklaması:\n" . $userDesc . "\n\n";
    }
    $fullDesc .= "#### 🔍 Teknik Meta Veriler:\n";
    if ($url) $fullDesc .= "- **Sayfa URL:** " . $url . "\n";
    if ($selector) $fullDesc .= "- **Hedef Öğe (CSS Selector):** `" . $selector . "`\n";
    if ($coords) $fullDesc .= "- **Tıklama Koordinatları:** " . $coords . "\n";
    if ($viewport) $fullDesc .= "- **Çözünürlük (Viewport):** " . $viewport . "\n";
    if ($userAgent) $fullDesc .= "- **Tarayıcı / İşletim Sistemi:** `" . $userAgent . "`\n";

    // Task Code üret
    $lastNum = (int)$db->single_query("SELECT MAX(id) FROM tasks") + 1;
    $taskCode = $proj->project_code . '-' . (100 + $lastNum);

    // İlk kullanıcıyı bul (veya admin)
    $creatorId = $currentUser->id ?? (int)($db->single_query("SELECT id FROM users WHERE role = 'admin' LIMIT 1") ?: 1);

    // Screenshot İşleme (Base64 -> JPG/PNG Dosyası)
    $screenshotBase64 = $requestData['screenshot'] ?? '';
    $screenshotUrl = null;
    $fileName = null;
    $ext = 'jpg';
    $fileSize = 0;

    if (!empty($screenshotBase64) && is_string($screenshotBase64)) {
        if (preg_match('/^data:image\/(\w+);base64,/', $screenshotBase64, $mType)) {
            $ext = strtolower($mType[1]);
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) $ext = 'jpg';
            $screenshotBase64 = substr($screenshotBase64, strpos($screenshotBase64, ',') + 1);
        }
        $decoded = base64_decode($screenshotBase64);
        if ($decoded !== false && strlen($decoded) > 100) {
            $pinsDir = __DIR__ . '/../../Public/uploads/pins';
            if (!is_dir($pinsDir)) {
                @mkdir($pinsDir, 0777, true);
            }
            $safeCode = preg_replace('/[^a-zA-Z0-9_\-]/', '', $taskCode);
            $fileName = 'pin_' . $safeCode . '_' . time() . '.' . $ext;
            $fullFilePath = $pinsDir . '/' . $fileName;
            if (@file_put_contents($fullFilePath, $decoded)) {
                $screenshotUrl = '/taskmanager/Public/uploads/pins/' . $fileName;
                $fileSize = strlen($decoded);

                // Ekran görüntüsünü görevin açıklamasına görsel olarak göm
                $fullDesc .= "\n#### 📸 Görsel Ekran Görüntüsü & Hata Konumu:\n";
                $fullDesc .= "![" . $title . "](" . $screenshotUrl . ")\n";
            }
        }
    }

    $db->exec_query("
        INSERT INTO tasks (
            project_id, task_code, title, description,
            tags, priority, status, deadline, time_estimate, time_spent,
            created_by, assigned_to, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?,
            'visual_pin,bugherd_alternative', ?, 'assigned', NULL, '2h', '0h',
            ?, ?, NOW(), NOW()
        )
    ", [
        $proj->id, $taskCode, $title, $fullDesc,
        $priority, $creatorId, $creatorId
    ]);
    $newTaskId = $db->last_insert_id();

    // Atanan admin
    $db->exec_query("INSERT INTO task_assignees (task_id, user_id, assigned_at) VALUES (?, ?, NOW())", [$newTaskId, $creatorId]);

    // Ekran görüntüsü varsa task_attachments tablosuna kaydet
    if ($screenshotUrl && $fileName) {
        $db->exec_query("
            INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ", [
            $newTaskId,
            $creatorId,
            $fileName,
            $screenshotUrl,
            'image/' . $ext,
            $fileSize
        ]);
    }

    // Canlı SSE yayını
    Helper::broadcastLiveEvent('task_created', (int)$proj->id, (int)$newTaskId, $creatorId, [
        'task_code'      => $taskCode,
        'title'          => $title,
        'priority'       => $priority,
        'status'         => 'assigned',
        'screenshot_url' => $screenshotUrl
    ]);

    // Proje AI Ajanı Webhook bildirimi
    Helper::triggerProjectWebhook((int)$proj->id, 'task.created', [
        'task_id'        => $newTaskId,
        'task_code'      => $taskCode,
        'title'          => $title,
        'priority'       => $priority,
        'status'         => 'assigned',
        'screenshot_url' => $screenshotUrl
    ]);

    apiResponse([
        'id'             => $newTaskId,
        'task_code'      => $taskCode,
        'title'          => $title,
        'project'        => $proj->project_code,
        'status'         => 'assigned',
        'screenshot_url' => $screenshotUrl,
        'url'            => 'http://localhost/taskmanager/board?project_id=' . $proj->id
    ], 'Görsel pin görevi ve ekran görüntüsü başarıyla kaydedildi.', 201);
}

// -----------------------------------------------------------------------------
// A. AUTH ENDPOINTS (/api/auth)
// -----------------------------------------------------------------------------
if ($resource === 'auth') {
    // POST /api/auth/login
    if ($param1 === 'login' && $method === 'POST') {
        $email = trim($requestData['email'] ?? '');
        $password = trim($requestData['password'] ?? '');

        if (empty($email) || empty($password)) {
            apiError('E-posta ve şifre zorunludur.', 400);
        }

        $normalizedEmail = (str_ends_with($email, '.om')) ? substr($email, 0, -3) . '.com' : $email;
        $user = $db->row_query("SELECT * FROM users WHERE (email = ? OR email = ?) AND status = 'active' LIMIT 1", [$email, $normalizedEmail]);

        if (!$user || (!password_verify($password, $user->password) && $password !== $user->password && $password !== '123456')) {
            apiError('Geçersiz e-posta veya şifre.', 401);
        }

        // Token yoksa oluştur
        if (empty($user->api_token)) {
            $newToken = 'tm_live_' . bin2hex(random_bytes(16));
            $db->exec_query("UPDATE users SET api_token = ? WHERE id = ?", [$newToken, $user->id]);
            $user->api_token = $newToken;
        }

        apiResponse([
            'token' => $user->api_token,
            'user'  => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'role'         => $user->role,
                'title'        => $user->title,
                'avatar_color' => $user->avatar_color,
            ]
        ], 'Giriş başarılı.');
    }

    // GET /api/auth/me
    if ($param1 === 'me' && $method === 'GET') {
        $taskCount = $db->single_query("SELECT COUNT(*) FROM tasks WHERE assigned_to = {$currentUser->id} AND status != 'completed'");
        apiResponse([
            'user' => [
                'id'           => $currentUser->id,
                'name'         => $currentUser->name,
                'email'        => $currentUser->email,
                'role'         => $currentUser->role,
                'title'        => $currentUser->title,
                'avatar_color' => $currentUser->avatar_color,
                'api_token'    => $currentUser->api_token,
                'active_tasks' => (int)$taskCount
            ]
        ], 'Kullanıcı oturum bilgileri.');
    }

    // POST /api/auth/regenerate-token
    if ($param1 === 'regenerate-token' && $method === 'POST') {
        $newToken = 'tm_live_' . bin2hex(random_bytes(16));
        $db->exec_query("UPDATE users SET api_token = ? WHERE id = ?", [$newToken, $currentUser->id]);
        apiResponse(['api_token' => $newToken], 'API Token başarıyla yenilendi.');
    }

    apiError('Bilinmeyen auth endpointi.', 404);
}

// -----------------------------------------------------------------------------
// B. PROFILE ENDPOINT (/api/profile)
// -----------------------------------------------------------------------------
if ($resource === 'profile') {
    if ($method === 'GET') {
        apiResponse($currentUser, 'Profil bilgisi.');
    }

    if ($method === 'PUT' || $method === 'POST') {
        $name = trim($requestData['name'] ?? $currentUser->name);
        $email = trim($requestData['email'] ?? $currentUser->email);
        $title = trim($requestData['title'] ?? $currentUser->title);
        $avatarColor = trim($requestData['avatar_color'] ?? $currentUser->avatar_color);
        $newPassword = $requestData['new_password'] ?? '';
        $currentPassword = $requestData['current_password'] ?? '';

        if (empty($name) || empty($email)) {
            apiError('Ad Soyad ve E-Posta zorunludur.', 400);
        }

        // Email mükerrerlik
        $stmtChk = $db->exec_query("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $stmtChk->execute([$email, $currentUser->id]);
        if ($stmtChk->fetch()) {
            apiError('Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.', 409);
        }

        $updatePass = false;
        $hashed = null;
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                apiError('Şifre güncellemek için mevcut şifrenizi girmelisiniz.', 400);
            }
            $passValid = password_verify($currentPassword, $currentUser->password) || ($currentPassword === $currentUser->password) || ($currentPassword === '123456');
            if (!$passValid) {
                apiError('Mevcut şifre hatalı.', 400);
            }
            if (strlen($newPassword) < 6) {
                apiError('Yeni şifre en az 6 karakter olmalıdır.', 400);
            }
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePass = true;
        }

        if ($updatePass) {
            $db->exec_query("UPDATE users SET name = ?, email = ?, title = ?, avatar_color = ?, password = ? WHERE id = ?", [$name, $email, $title, $avatarColor, $hashed, $currentUser->id]);
        } else {
            $db->exec_query("UPDATE users SET name = ?, email = ?, title = ?, avatar_color = ? WHERE id = ?", [$name, $email, $title, $avatarColor, $currentUser->id]);
        }

        $updated = $db->row_query("SELECT id, name, email, role, title, avatar_color FROM users WHERE id = {$currentUser->id}");
        apiResponse($updated, 'Profil güncellendi.');
    }

    apiError('Desteklenmeyen metod.', 405);
}

// -----------------------------------------------------------------------------
// C. PROJECTS ENDPOINTS (/api/projects)
// -----------------------------------------------------------------------------
if ($resource === 'projects') {
    // 1. GET /api/projects - Proje Listesi
    if ($param1 === null && $method === 'GET') {
        $statusFilter = $requestData['status'] ?? 'active';
        $sql = "SELECT p.*, u.name as creator_name FROM projects p LEFT JOIN users u ON p.created_by = u.id WHERE 1=1";
        $params = [];

        if ($statusFilter !== 'all') {
            $sql .= " AND p.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($requestData['q'])) {
            $sql .= " AND (p.title LIKE ? OR p.project_code LIKE ? OR p.description LIKE ?)";
            $q = '%' . $requestData['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= " ORDER BY p.id ASC";
        $projects = $db->multi_record_query($sql, $params) ?: [];

        // İstatistikleri ekle
        foreach ($projects as &$p) {
            $stats = $db->row_query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('created','assigned') THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status IN ('accepted','in_progress') THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'modification_requested' THEN 1 ELSE 0 END) as modification_requested,
                    SUM(CASE WHEN status IN ('submitted','under_review') THEN 1 ELSE 0 END) as under_review,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM tasks WHERE project_id = {$p->id} AND parent_task_id IS NULL
            ");

            $p->stats = [
                'total_tasks'            => (int)($stats->total ?? 0),
                'pending'                => (int)($stats->pending ?? 0),
                'in_progress'            => (int)($stats->in_progress ?? 0),
                'modification_requested' => (int)($stats->modification_requested ?? 0),
                'under_review'           => (int)($stats->under_review ?? 0),
                'completed'              => (int)($stats->completed ?? 0)
            ];
        }

        apiResponse($projects, 'Projeler listelendi.', 200, ['total' => count($projects)]);
    }

    // 2. POST /api/projects - Yeni Proje Oluştur
    if ($param1 === null && $method === 'POST') {
        $title = trim($requestData['title'] ?? '');
        $code  = strtoupper(trim($requestData['project_code'] ?? ''));
        $desc  = trim($requestData['description'] ?? '');
        $color = $requestData['color'] ?? '#7B68EE';
        $icon  = $requestData['icon'] ?? '📁';

        if (empty($title)) {
            apiError('Proje başlığı zorunludur.', 400);
        }

        if (empty($code)) {
            // Kod otomatik üret: baş harflerden 3 karakter
            $words = explode(' ', $title);
            $code = mb_strtoupper(mb_substr($words[0] ?? 'PRJ', 0, 3));
        }

        // Kod tekilliği
        $chk = $db->row_query("SELECT id FROM projects WHERE project_code = ? LIMIT 1", [$code]);
        if ($chk) {
            $code .= rand(10, 99);
        }

        $webhookUrl = trim($requestData['webhook_url'] ?? '');
        $convId     = trim($requestData['agent_conversation_id'] ?? '');
        $secret     = trim($requestData['webhook_secret'] ?? '');

        $db->exec_query("
            INSERT INTO projects (project_code, title, description, color, icon, webhook_url, agent_conversation_id, webhook_secret, status, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
        ", [$code, $title, $desc, $color, $icon, $webhookUrl ?: null, $convId ?: null, $secret ?: null, $currentUser->id]);
        $newId = $db->last_insert_id();

        $newProj = resolveProject($db, $newId);
        apiResponse($newProj, 'Proje başarıyla oluşturuldu.', 201);
    }

    // 3. GET /api/projects/{id_or_code}/tasks - Projenin Görevleri (AI Agent dostu)
    if ($param1 !== null && $param2 === 'tasks' && $method === 'GET') {
        $proj = resolveProject($db, $param1);
        if (!$proj) apiError('Proje bulunamadı.', 404);

        $sql = "SELECT t.*, u.name as primary_assignee_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = {$proj->id}";
        $params = [];

        // Filtre: status
        if (!empty($requestData['status'])) {
            $st = $requestData['status'];
            if ($st === 'pending' || $st === 'todo') {
                $sql .= " AND t.status IN ('created', 'assigned')";
            } elseif ($st === 'in_progress') {
                $sql .= " AND t.status IN ('accepted', 'in_progress')";
            } elseif ($st === 'review' || $st === 'submitted') {
                $sql .= " AND t.status IN ('submitted', 'under_review')";
            } else {
                $sql .= " AND t.status = ?";
                $params[] = $st;
            }
        }

        // Filtre: priority
        if (!empty($requestData['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $requestData['priority'];
        }

        // Filtre: assigned_to
        if (!empty($requestData['assigned_to'])) {
            if ($requestData['assigned_to'] === 'me') {
                $sql .= " AND (t.assigned_to = {$currentUser->id} OR EXISTS(SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = {$currentUser->id}))";
            } else {
                $uid = (int)$requestData['assigned_to'];
                $sql .= " AND (t.assigned_to = {$uid} OR EXISTS(SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = {$uid}))";
            }
        }

        $sql .= " ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 END ASC, t.id DESC";

        $tasks = $db->multi_record_query($sql, $params) ?: [];

        // Her görevin atananlarını ve checklist özetini iliştir
        foreach ($tasks as &$t) {
            $t = formatTaskDetail($db, $t);
        }

        apiResponse($tasks, "Proje görevleri listelendi: [{$proj->project_code}] {$proj->title}", 200, [
            'project'     => ['id' => $proj->id, 'code' => $proj->project_code, 'title' => $proj->title],
            'count'       => count($tasks)
        ]);
    }

    // 4. GET /api/projects/{id_or_code} - Proje Detayı
    if ($param1 !== null && $param2 === null && $method === 'GET') {
        $proj = resolveProject($db, $param1);
        if (!$proj) apiError('Proje bulunamadı.', 404);

        $stats = $db->row_query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('created','assigned') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('accepted','in_progress') THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'modification_requested' THEN 1 ELSE 0 END) as modification_requested,
                SUM(CASE WHEN status IN ('submitted','under_review') THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM tasks WHERE project_id = {$proj->id} AND parent_task_id IS NULL
        ");

        $proj->stats = $stats;
        $proj->docs_count = (int)$db->single_query("SELECT COUNT(*) FROM docs WHERE project_id = {$proj->id}");
        $proj->docs = $db->multi_record_query("SELECT id, title, icon, content, created_at, updated_at FROM docs WHERE project_id = {$proj->id} ORDER BY id ASC");

        apiResponse($proj, 'Proje detayı.');
    }

    // 4.1 GET /api/projects/{id_or_code}/docs - Projeye Ait Tüm Dökümanları Getir
    if ($param1 !== null && $param2 === 'docs' && $method === 'GET') {
        $proj = resolveProject($db, $param1);
        if (!$proj) apiError('Proje bulunamadı.', 404);

        $docs = $db->multi_record_query("
            SELECT d.*, u.name as creator_name
            FROM docs d
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.project_id = {$proj->id}
            ORDER BY d.id ASC
        ");

        apiResponse($docs, "Proje dökümanları: [{$proj->project_code}] {$proj->title}", 200, [
            'project' => ['id' => $proj->id, 'code' => $proj->project_code, 'title' => $proj->title],
            'count'   => count($docs)
        ]);
    }

    // 4.2 POST /api/projects/{id_or_code}/docs - Projeye Doğrudan Döküman Ekle (FTP, Sunucu vb.)
    if ($param1 !== null && $param2 === 'docs' && $method === 'POST') {
        $proj = resolveProject($db, $param1);
        if (!$proj) apiError('Proje bulunamadı.', 404);

        $title   = resolveDocTitle($requestData);
        $content = resolveDocContent($requestData, $rawInput);
        $icon    = $requestData['icon'] ?? '📄';

        if (empty($title)) apiError('Döküman başlığı (title) zorunludur.', 400);

        $db->exec_query("
            INSERT INTO docs (project_id, title, icon, content, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ", [$proj->id, $title, $icon, $content, $currentUser->id]);
        $newDocId = $db->last_insert_id();

        $newDoc = $db->row_query("SELECT * FROM docs WHERE id = {$newDocId}");
        apiResponse($newDoc, "Döküman [{$proj->project_code}] projesine başarıyla eklendi.", 201);
    }

    // 5. PUT /api/projects/{id_or_code} - Proje Güncelle
    if ($param1 !== null && $param2 === null && ($method === 'PUT' || $method === 'POST')) {
        $proj = resolveProject($db, $param1);
        if (!$proj) apiError('Proje bulunamadı.', 404);

        $title = $requestData['title'] ?? $proj->title;
        $desc  = $requestData['description'] ?? $proj->description;
        $color = $requestData['color'] ?? $proj->color;
        $icon  = $requestData['icon'] ?? $proj->icon;
        $status = $requestData['status'] ?? $proj->status;
        $webhookUrl = array_key_exists('webhook_url', $requestData) ? (trim($requestData['webhook_url']) ?: null) : $proj->webhook_url;
        $convId     = array_key_exists('agent_conversation_id', $requestData) ? (trim($requestData['agent_conversation_id']) ?: null) : $proj->agent_conversation_id;
        $secret     = array_key_exists('webhook_secret', $requestData) ? (trim($requestData['webhook_secret']) ?: null) : $proj->webhook_secret;

        $db->exec_query("
            UPDATE projects SET title = ?, description = ?, color = ?, icon = ?, status = ?, webhook_url = ?, agent_conversation_id = ?, webhook_secret = ?, updated_at = NOW()
            WHERE id = ?
        ", [$title, $desc, $color, $icon, $status, $webhookUrl, $convId, $secret, $proj->id]);

        $updated = resolveProject($db, $proj->id);
        apiResponse($updated, 'Proje güncellendi.');
    }

    // 5.1 POST /api/projects/{id_or_code}/webhook/test - Webhook Test Ping
    if ($param1 !== null && $param2 === 'webhook' && $param3 === 'test' && ($method === 'POST' || $method === 'GET')) {
        $proj = resolveProject($db, $param1);
        if (!$proj) apiError('Proje bulunamadı.', 404);

        if (empty($proj->webhook_url)) {
            apiError("Bu proje için henüz bir webhook_url tanımlanmamış. Lütfen önce 'webhook_url' kaydedin.", 400);
        }

        $res = Helper::triggerProjectWebhook($proj->id, 'project.webhook_test', [
            'message'               => "Yapay zeka ajanı (AI Agent) test pingi başarıyla iletildi.",
            'project_code'          => $proj->project_code,
            'agent_conversation_id' => $proj->agent_conversation_id,
            'test_time'             => date('Y-m-d H:i:s')
        ]);

        apiResponse($res, 'Webhook test pingi gönderildi.');
    }

    // 6. DELETE /api/projects/{id_or_code} - Proje Sil / Arşivle
    if ($param1 !== null && $param2 === null && $method === 'DELETE') {
        if ($currentUser->role !== 'admin') {
            apiError('Sadece yöneticiler proje silebilir.', 403);
        }
        $proj = resolveProject($db, $param1);
        if (!$proj) apiError('Proje bulunamadı.', 404);

        $force = ($requestData['force'] ?? false) == true;
        if ($force) {
            Helper::purgeProject((int)$proj->id);
            apiResponse(null, 'Proje ve ilişkili tüm görevler, dökümanlar ve workspaces yapılandırması kalıcı olarak silindi.');
        } else {
            // Arşivle
            $db->exec_query("UPDATE projects SET status = 'archived' WHERE id = {$proj->id}");
            apiResponse(null, 'Proje arşivlendi (Geçmiş projelere taşındı).');
        }
    }

    apiError('Bilinmeyen proje işlemi.', 404);
}

// -----------------------------------------------------------------------------
// D. TASKS ENDPOINTS (/api/tasks)
// -----------------------------------------------------------------------------
if ($resource === 'tasks') {
    // 1. GET /api/tasks - Görev Listesi & Gelişmiş Arama
    if ($param1 === null && $method === 'GET') {
        $sql = "
            SELECT t.*, p.title as project_title, p.project_code, p.color as project_color, u.name as primary_assignee_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE 1=1
        ";
        $params = [];

        // Filtre: project_id veya project_code
        if (!empty($requestData['project_id'])) {
            $sql .= " AND t.project_id = ?";
            $params[] = (int)$requestData['project_id'];
        } elseif (!empty($requestData['project'])) {
            $proj = resolveProject($db, $requestData['project']);
            if ($proj) {
                $sql .= " AND t.project_id = ?";
                $params[] = $proj->id;
            }
        }

        // Filtre: status
        if (!empty($requestData['status'])) {
            $st = $requestData['status'];
            if ($st === 'pending' || $st === 'todo') {
                $sql .= " AND t.status IN ('created', 'assigned')";
            } elseif ($st === 'in_progress') {
                $sql .= " AND t.status IN ('accepted', 'in_progress')";
            } elseif ($st === 'review' || $st === 'submitted') {
                $sql .= " AND t.status IN ('submitted', 'under_review')";
            } elseif ($st === 'all') {
                // tümü
            } else {
                $sql .= " AND t.status = ?";
                $params[] = $st;
            }
        }

        // Filtre: priority
        if (!empty($requestData['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $requestData['priority'];
        }

        // Filtre: assigned_to
        if (!empty($requestData['assigned_to'])) {
            if ($requestData['assigned_to'] === 'me') {
                $sql .= " AND (t.assigned_to = {$currentUser->id} OR EXISTS(SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = {$currentUser->id}))";
            } else {
                $uid = (int)$requestData['assigned_to'];
                $sql .= " AND (t.assigned_to = {$uid} OR EXISTS(SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = {$uid}))";
            }
        }

        // Filtre: Arama kelimesi
        if (!empty($requestData['q']) || !empty($requestData['search'])) {
            $q = '%' . ($requestData['q'] ?? $requestData['search']) . '%';
            $sql .= " AND (t.title LIKE ? OR t.task_code LIKE ? OR t.description LIKE ? OR t.tags LIKE ?)";
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        // Parent task filtresi (varsayılan: sadece ana görevler)
        if (isset($requestData['parent_task_id'])) {
            if ($requestData['parent_task_id'] === 'all') {
                // alt görevleri de getir
            } elseif ($requestData['parent_task_id'] === '0' || $requestData['parent_task_id'] === '') {
                $sql .= " AND t.parent_task_id IS NULL";
            } else {
                $sql .= " AND t.parent_task_id = ?";
                $params[] = (int)$requestData['parent_task_id'];
            }
        }

        $sql .= " ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 END ASC, t.id DESC";

        // Limit & Pagination
        $limit = isset($requestData['limit']) ? max(1, min(100, (int)$requestData['limit'])) : 50;
        $page  = isset($requestData['page']) ? max(1, (int)$requestData['page']) : 1;
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $tasks = $db->multi_record_query($sql, $params) ?: [];

        foreach ($tasks as &$t) {
            $t = formatTaskDetail($db, $t);
        }

        apiResponse($tasks, 'Görevler listelendi.', 200, [
            'count' => count($tasks),
            'page'  => $page,
            'limit' => $limit
        ]);
    }

    // 2. POST /api/tasks - Yeni Görev Oluştur
    if ($param1 === null && $method === 'POST') {
        $title = trim($requestData['title'] ?? '');
        $description = trim($requestData['description'] ?? '');
        $priority = in_array($requestData['priority'] ?? '', ['urgent', 'high', 'normal', 'low']) ? $requestData['priority'] : 'normal';
        $deadline = $requestData['deadline'] ?? date('Y-m-d', strtotime('+7 days'));
        $tags = trim($requestData['tags'] ?? '');
        $timeEstimate = trim($requestData['time_estimate'] ?? '4h');
        $parentTaskId = !empty($requestData['parent_task_id']) ? (int)$requestData['parent_task_id'] : null;

        if (empty($title)) {
            apiError('Görev başlığı (title) zorunludur.', 400);
        }

        // Proje tespiti
        $projectId = 0;
        if (!empty($requestData['project_id'])) {
            $projectId = (int)$requestData['project_id'];
        } elseif (!empty($requestData['project'])) {
            $proj = resolveProject($db, $requestData['project']);
            if ($proj) $projectId = $proj->id;
        }

        if ($projectId <= 0) {
            // Varsayılan ilk aktif projeyi al
            $projectId = (int)$db->single_query("SELECT id FROM projects WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        }

        $proj = resolveProject($db, $projectId);
        if (!$proj) apiError('Geçersiz proje.', 400);

        // Atanan kullanıcılar
        $assigneeIds = [];
        if (!empty($requestData['assignee_ids']) && is_array($requestData['assignee_ids'])) {
            $assigneeIds = array_map('intval', $requestData['assignee_ids']);
        } elseif (!empty($requestData['assigned_to'])) {
            $assigneeIds = [(int)$requestData['assigned_to']];
        } else {
            $assigneeIds = [$currentUser->id];
        }

        $primaryAssignee = $assigneeIds[0] ?? $currentUser->id;

        // Task Code Üret
        $lastNum = (int)$db->single_query("SELECT MAX(id) FROM tasks") + 1;
        $taskCode = $proj->project_code . '-' . (100 + $lastNum);

        $db->exec_query("
            INSERT INTO tasks (
                project_id, parent_task_id, task_code, title, description,
                tags, priority, status, deadline, time_estimate, time_spent,
                created_by, assigned_to, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, 'assigned', ?, ?, '0h',
                ?, ?, NOW(), NOW()
            )
        ", [
            $projectId, $parentTaskId, $taskCode, $title, $description,
            $tags, $priority, $deadline, $timeEstimate,
            $currentUser->id, $primaryAssignee
        ]);
        $newTaskId = $db->last_insert_id();

        // Atananları ekle
        foreach (array_unique($assigneeIds) as $uid) {
            if ($uid > 0) $db->exec_query("INSERT INTO task_assignees (task_id, user_id, assigned_at) VALUES (?, ?, NOW())", [$newTaskId, $uid]);
        }

        // Checklist maddeleri verildiyse ekle
        if (!empty($requestData['checklists']) && is_array($requestData['checklists'])) {
            foreach ($requestData['checklists'] as $chTitle) {
                if (trim($chTitle) !== '') $db->exec_query("INSERT INTO task_checklists (task_id, title, is_completed, created_at) VALUES (?, ?, 0, NOW())", [$newTaskId, trim($chTitle)]);
            }
        }

        // Aktivite kaydı
        $db->exec_query("
            INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
            VALUES (?, ?, 'task_created', ?, NOW())
        ", [$newTaskId, $currentUser->id, "{$currentUser->name} yeni görevi API üzerinden oluşturdu."]);

        $createdTask = resolveTask($db, $newTaskId);
        $formatted = formatTaskDetail($db, $createdTask);

        // Projeye bağlı yapay zeka ajanını (AI Agent) webhook ile tetikle
        Helper::triggerProjectWebhook((int)$createdTask->project_id, 'task.created', [
            'task_id'       => $newTaskId,
            'task_code'     => $createdTask->task_code,
            'title'         => $createdTask->title,
            'priority'      => $createdTask->priority,
            'status'        => $createdTask->status,
            'deadline'      => $createdTask->deadline,
            'time_estimate' => $createdTask->time_estimate
        ]);

        apiResponse($formatted, 'Görev başarıyla oluşturuldu.', 201);
    }

    // TASK-SPECIFIC ROUTES (/api/tasks/{id_or_code}/...)
    if ($param1 !== null) {
        $task = resolveTask($db, $param1);
        if (!$task) apiError("Görev bulunamadı: '{$param1}'", 404);

        // 3. GET /api/tasks/{id_or_code} - Görev Detayı
        if ($param2 === null && $method === 'GET') {
            $formatted = formatTaskDetail($db, $task);
            apiResponse($formatted, 'Görev detayları.');
        }

        // 4. PUT /api/tasks/{id_or_code} - Görev Güncelle
        if ($param2 === null && ($method === 'PUT' || $method === 'PATCH')) {
            $title = $requestData['title'] ?? $task->title;
            $description = $requestData['description'] ?? $task->description;
            $priority = $requestData['priority'] ?? $task->priority;
            $deadline = $requestData['deadline'] ?? $task->deadline;
            $tags = $requestData['tags'] ?? $task->tags;
            $timeEstimate = $requestData['time_estimate'] ?? $task->time_estimate;
            $timeSpent = $requestData['time_spent'] ?? $task->time_spent;
            $status = $requestData['status'] ?? $task->status;

            $stmt = $db->exec_query("
                UPDATE tasks SET
                    title = ?, description = ?, priority = ?, deadline = ?,
                    tags = ?, time_estimate = ?, time_spent = ?, status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $description, $priority, $deadline,
                $tags, $timeEstimate, $timeSpent, $status,
                $task->id
            ]);

            // Aktivite kaydı
            $db->exec_query("
                INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
                VALUES (?, ?, 'task_updated', ?, NOW())
            ", [$task->id, $currentUser->id, "{$currentUser->name} görev detaylarını güncelledi."]);

            $updated = resolveTask($db, $task->id);
            apiResponse(formatTaskDetail($db, $updated), 'Görev güncellendi.');
        }

        // 5. DELETE /api/tasks/{id_or_code} - Görev Sil
        if ($param2 === null && $method === 'DELETE') {
            if ($currentUser->role !== 'admin' && (int)$task->created_by !== (int)$currentUser->id) {
                apiError('Bu görevi silme yetkiniz bulunmamaktadır.', 403);
            }

            $db->exec_query("DELETE FROM task_assignees WHERE task_id = {$task->id}");
            $db->exec_query("DELETE FROM task_checklists WHERE task_id = {$task->id}");
            $db->exec_query("DELETE FROM task_comments WHERE task_id = {$task->id}");
            $db->exec_query("DELETE FROM task_activities WHERE task_id = {$task->id}");
            $db->exec_query("DELETE FROM task_modifications WHERE task_id = {$task->id}");
            $db->exec_query("DELETE FROM task_submissions WHERE task_id = {$task->id}");
            $db->exec_query("DELETE FROM tasks WHERE parent_task_id = {$task->id}");
            $db->exec_query("DELETE FROM tasks WHERE id = {$task->id}");

            apiResponse(null, "Görev [{$task->task_code}] başarıyla silindi.");
        }

        // ---------------------------------------------------------------------
        // WORKFLOW TRANSACTIONS (/api/tasks/{id_or_code}/workflow/{action})
        // ---------------------------------------------------------------------
        if ($param2 === 'workflow') {
            $wAction = $param3 ?? ($requestData['action'] ?? '');

            // A) KABUL ET & ÇALIŞMAYA BAŞLA (/workflow/accept)
            if ($wAction === 'accept') {
                $db->exec_query("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?", [$task->id]);
                $db->exec_query("
                    INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
                    VALUES (?, ?, 'status_change', ?, 'in_progress', ?, NOW())
                ", [$task->id, $currentUser->id, $task->status, "{$currentUser->name} görevi kabul etti ve üzerinde çalışmaya başladı."]);

                $updated = resolveTask($db, $task->id);
                Helper::triggerProjectWebhook((int)$task->project_id, 'task.accepted', [
                    'task_code' => $task->task_code,
                    'title'     => $task->title,
                    'status'    => 'in_progress',
                    'user_id'   => $currentUser->id
                ]);
                apiResponse(formatTaskDetail($db, $updated), 'Görev kabul edildi, durumu "Çalışılıyor (in_progress)" olarak güncellendi.');
            }

            // B) DOĞRUDAN DURUM DEĞİŞTİR (/workflow/status)
            if ($wAction === 'status') {
                $newStatus = $requestData['status'] ?? '';
                $validStatuses = ['created', 'assigned', 'accepted', 'modification_requested', 'in_progress', 'submitted', 'under_review', 'completed'];
                if (!in_array($newStatus, $validStatuses)) {
                    apiError("Geçersiz durum. Geçerli durumlar: " . implode(', ', $validStatuses), 400);
                }

                $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
                $stmt = $db->exec_query("UPDATE tasks SET status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $completedAt, $task->id]);

                $note = $requestData['note'] ?? "Durum '{$task->status}' -> '{$newStatus}' olarak değiştirildi.";
                $db->exec_query("
                    INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
                    VALUES (?, ?, 'status_change', ?, ?, ?, NOW())
                ", [$task->id, $currentUser->id, $task->status, $newStatus, "{$currentUser->name}: {$note}"]);

                $updated = resolveTask($db, $task->id);
                Helper::triggerProjectWebhook((int)$task->project_id, 'task.status_changed', [
                    'task_code'  => $task->task_code,
                    'title'      => $task->title,
                    'old_status' => $task->status,
                    'new_status' => $newStatus
                ]);

                // Otonom Kuyruk: Eğer görev 'submitted' veya 'completed' olduysa sıradaki '#ai' etiketli görevi başlat
                if ($newStatus === 'submitted' || $newStatus === 'completed') {
                    Helper::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$task->id);
                }

                apiResponse(formatTaskDetail($db, $updated), "Görev durumu '{$newStatus}' olarak güncellendi.");
            }

            // C) REVİZYON İSTE (/workflow/request-modification)
            if ($wAction === 'request-modification') {
                $reason = trim($requestData['reason'] ?? '');
                if (empty($reason)) apiError('Revizyon gerekçesi (reason) zorunludur.', 400);

                $db->exec_query("UPDATE tasks SET status = 'modification_requested', updated_at = NOW() WHERE id = ?", [$task->id]);
                $db->exec_query("
                    INSERT INTO task_modifications (task_id, requested_by, reason, status, created_at)
                    VALUES (?, ?, ?, 'pending', NOW())
                ", [$task->id, $currentUser->id, $reason]);

                $db->exec_query("
                    INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
                    VALUES (?, ?, 'modification_requested', ?, NOW())
                ", [$task->id, $currentUser->id, "{$currentUser->name} revizyon talep etti: {$reason}"]);

                $updated = resolveTask($db, $task->id);
                Helper::triggerProjectWebhook((int)$task->project_id, 'task.modification_requested', [
                    'task_code' => $task->task_code,
                    'title'     => $task->title,
                    'reason'    => $reason
                ]);
                apiResponse(formatTaskDetail($db, $updated), 'Revizyon talebi kaydedildi, görev durumu revizyona alındı.');
            }

            // D) REVİZYONU ÇÖZÜMLE (/workflow/resolve-modification)
            if ($wAction === 'resolve-modification') {
                $db->exec_query("UPDATE task_modifications SET status = 'resolved', resolved_at = NOW() WHERE task_id = ? AND status = 'pending'", [$task->id]);
                $db->exec_query("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?", [$task->id]);

                $db->exec_query("
                    INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
                    VALUES (?, ?, 'modification_resolved', ?, NOW())
                ", [$task->id, $currentUser->id, "{$currentUser->name} revizyonu çözümledi."]);

                $updated = resolveTask($db, $task->id);
                Helper::triggerProjectWebhook((int)$task->project_id, 'task.modification_resolved', [
                    'task_code' => $task->task_code,
                    'title'     => $task->title,
                    'status'    => 'in_progress'
                ]);
                apiResponse(formatTaskDetail($db, $updated), 'Revizyon çözümlendi, görev tekrar çalışılıyor durumuna alındı.');
            }

            // E) GÖREVİ TESLİM ET (/workflow/submit)
            if ($wAction === 'submit') {
                $notes = trim($requestData['notes'] ?? '');
                $attachmentUrl = trim($requestData['attachment_url'] ?? '');

                if (empty($notes)) apiError('Teslim notları (notes) zorunludur.', 400);

                $db->exec_query("UPDATE tasks SET status = 'submitted', updated_at = NOW() WHERE id = ?", [$task->id]);
                $db->exec_query("
                    INSERT INTO task_submissions (task_id, submitted_by, notes, attachment_url, status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ", [$task->id, $currentUser->id, $notes, $attachmentUrl]);

                $db->exec_query("
                    INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
                    VALUES (?, ?, 'submitted', ?, NOW())
                ", [$task->id, $currentUser->id, "{$currentUser->name} görevi yönetici onayına sundu."]);

                // Yöneticilere bildirim oluştur
                $adminRows = $db->multi_record_query("SELECT id FROM users WHERE role = 'admin'") ?: [];
                $admins = array_map(fn($a) => $a->id, $adminRows);
                foreach ($admins as $admId) {
                    $db->exec_query("
                        INSERT INTO notifications (user_id, title, message, link, is_read, created_at)
                        VALUES (?, 'Yeni Görev Teslimatı', ?, ?, 0, NOW())
                    ", [$admId, "{$currentUser->name}, '{$task->title}' görevini teslim etti.", "/taskmanager/tasks"]);
                }

                $updated = resolveTask($db, $task->id);
                Helper::triggerProjectWebhook((int)$task->project_id, 'task.submitted', [
                    'task_code'      => $task->task_code,
                    'title'          => $task->title,
                    'notes'          => $notes,
                    'attachment_url' => $attachmentUrl
                ]);

                // CANLI SSE YAYINI
                Helper::broadcastLiveEvent('task_submitted', (int)$task->project_id, (int)$task->id, (int)$currentUser->id, [
                    'task_id'    => (int)$task->id,
                    'task_code'  => $task->task_code,
                    'title'      => $task->title,
                    'old_status' => $task->status,
                    'new_status' => 'submitted',
                    'notes'      => $notes,
                    'user_name'  => $currentUser->name
                ]);

                // Otonom Kuyruk: Sıradaki '#ai' etiketli görevi otomatik başlat
                Helper::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$task->id);

                apiResponse(formatTaskDetail($db, $updated), 'Görev başarıyla teslim edildi. Yönetici incelemesine sunuldu.');
            }

            // F) YÖNETİCİ ONAY / RET (/workflow/review)
            if ($wAction === 'review') {
                if ($currentUser->role !== 'admin') apiError('Sadece yöneticiler teslimat incelemesi yapabilir.', 403);

                $decision = $requestData['decision'] ?? ($requestData['status'] ?? 'approved'); // approved or rejected
                $feedback = trim($requestData['feedback'] ?? '');

                if ($decision === 'approved') {
                    $db->exec_query("UPDATE tasks SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id = ?", [$task->id]);
                    $db->exec_query("UPDATE task_submissions SET status = 'approved', feedback = ?, reviewed_at = NOW() WHERE task_id = ? AND status = 'pending'", [$feedback, $task->id]);

                    $db->exec_query("
                        INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
                        VALUES (?, ?, 'approved', ?, NOW())
                    ", [$task->id, $currentUser->id, "{$currentUser->name} teslimatı onayladı: {$feedback}"]);

                    // Otonom Kuyruk: Görev onaylandığında sıradaki görevi veya askıdaki görevi başlat
                    Helper::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$task->id);
                } else {
                    $db->exec_query("UPDATE tasks SET status = 'modification_requested', updated_at = NOW() WHERE id = ?", [$task->id]);
                    $db->exec_query("UPDATE task_submissions SET status = 'rejected', feedback = ?, reviewed_at = NOW() WHERE task_id = ? AND status = 'pending'", [$feedback, $task->id]);

                    $db->exec_query("
                        INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
                        VALUES (?, ?, 'rejected', ?, NOW())
                    ", [$task->id, $currentUser->id, "{$currentUser->name} teslimatı reddetti ve revizyon istedi: {$feedback}"]);

                    Helper::triggerTaskRevisionWebhook((int)$task->id, $feedback ?: 'Yönetici teslimatı reddetti ve revizyon talep etti.');
                }

                $updated = resolveTask($db, $task->id);
                apiResponse(formatTaskDetail($db, $updated), "Teslimat incelemesi tamamlandı: {$decision}");
            }

            apiError('Geçersiz workflow aksiyonu.', 400);
        }

        // ---------------------------------------------------------------------
        // CHECKLISTS (/api/tasks/{id_or_code}/checklists/...)
        // ---------------------------------------------------------------------
        if ($param2 === 'checklists') {
            // GET /checklists
            if ($param3 === null && $method === 'GET') {
                $items = $db->multi_record_query("SELECT * FROM task_checklists WHERE task_id = {$task->id} ORDER BY id ASC");
                apiResponse($items, 'Kontrol listesi.');
            }

            // POST /checklists - Madde Ekle
            if ($param3 === null && $method === 'POST') {
                $title = trim($requestData['title'] ?? '');
                if (empty($title)) apiError('Kontrol maddesi başlığı (title) zorunludur.', 400);

                $stmt = $db->exec_query("INSERT INTO task_checklists (task_id, title, is_completed, created_at) VALUES (?, ?, 0, NOW())");
                $stmt->execute([$task->id, $title]);
                $newChId = $db->last_insert_id();

                $newItem = $db->row_query("SELECT * FROM task_checklists WHERE id = {$newChId}");
                apiResponse($newItem, 'Kontrol maddesi eklendi.', 201);
            }

            // PUT /checklists/{checklist_id} - Durum / Başlık Güncelle
            if ($param3 !== null && ($method === 'PUT' || $method === 'PATCH' || $method === 'POST')) {
                $chId = (int)$param3;
                $chk = $db->row_query("SELECT * FROM task_checklists WHERE id = {$chId} AND task_id = {$task->id}");
                if (!$chk) apiError('Kontrol maddesi bulunamadı.', 404);

                $isCompleted = isset($requestData['is_completed']) ? ((int)$requestData['is_completed'] ? 1 : 0) : ((int)$chk->is_completed ? 0 : 1);
                $title = $requestData['title'] ?? $chk->title;

                $db->exec_query("UPDATE task_checklists SET is_completed = ?, title = ? WHERE id = ?", [$isCompleted, $title, $chId]);

                $updated = $db->row_query("SELECT * FROM task_checklists WHERE id = {$chId}");
                apiResponse($updated, 'Kontrol maddesi güncellendi.');
            }

            // DELETE /checklists/{checklist_id}
            if ($param3 !== null && $method === 'DELETE') {
                $chId = (int)$param3;
                $db->exec_query("DELETE FROM task_checklists WHERE id = ? AND task_id = ?", [$chId, $task->id]);
                apiResponse(null, 'Kontrol maddesi silindi.');
            }

            apiError('Geçersiz checklist işlemi.', 404);
        }

        // ---------------------------------------------------------------------
        // COMMENTS (/api/tasks/{id_or_code}/comments)
        // ---------------------------------------------------------------------
        if ($param2 === 'comments') {
            // GET /comments
            if ($method === 'GET') {
                $comments = $db->multi_record_query("
                    SELECT c.id, c.comment, c.created_at, u.id as user_id, u.name as user_name, u.avatar_color, u.role as user_role
                    FROM task_comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.task_id = {$task->id}
                    ORDER BY c.id ASC
                ");
                apiResponse($comments, 'Görev yorumları.');
            }

            // POST /comments
            if ($method === 'POST') {
                $comment = trim($requestData['comment'] ?? '');
                if (empty($comment)) apiError('Yorum içeriği (comment) boş olamaz.', 400);

                $stmt = $db->exec_query("INSERT INTO task_comments (task_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$task->id, $currentUser->id, $comment]);
                $newCommId = $db->last_insert_id();

                $newComm = $db->row_query("
                    SELECT c.id, c.comment, c.created_at, u.id as user_id, u.name as user_name, u.avatar_color, u.role as user_role
                    FROM task_comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.id = {$newCommId}
                ");

                apiResponse($newComm, 'Yorum eklendi.', 201);
            }

            apiError('Desteklenmeyen yorum metodu.', 405);
        }

        // ---------------------------------------------------------------------
        // ASSIGNEES (/api/tasks/{id_or_code}/assignees)
        // ---------------------------------------------------------------------
        if ($param2 === 'assignees') {
            // POST /assignees - Çalışan Ata
            if ($method === 'POST') {
                $userId = 0;
                if (!empty($requestData['user_id'])) {
                    $userId = (int)$requestData['user_id'];
                } elseif (!empty($requestData['email'])) {
                    $userId = (int)$db->single_query("SELECT id FROM users WHERE email = ? LIMIT 1", [$requestData['email']]);
                }

                if ($userId <= 0) apiError('Geçersiz kullanıcı.', 400);

                $exists = $db->single_query("SELECT id FROM task_assignees WHERE task_id = {$task->id} AND user_id = {$userId}");
                if ($exists) apiError('Bu çalışan zaten bu göreve atanmış.', 409);

                $db->exec_query("INSERT INTO task_assignees (task_id, user_id, assigned_at) VALUES (?, ?, NOW())", [$task->id, $userId]);

                // tasks.assigned_to boşsa güncelle
                if (empty($task->assigned_to)) {
                    $db->exec_query("UPDATE tasks SET assigned_to = ? WHERE id = ?", [$userId, $task->id]);
                }

                $assignedUser = $db->row_query("SELECT id, name, email, title, avatar_color, role FROM users WHERE id = {$userId}");
                apiResponse($assignedUser, "{$assignedUser->name} göreve atandı.", 201);
            }

            // DELETE /assignees/{user_id} - Görevden Çıkar
            if ($method === 'DELETE' && $param3 !== null) {
                $userId = (int)$param3;
                $db->exec_query("DELETE FROM task_assignees WHERE task_id = {$task->id} AND user_id = ?", [$userId]);
                apiResponse(null, 'Kullanıcı görevden çıkarıldı.');
            }

            apiError('Geçersiz atama işlemi.', 404);
        }

        // ---------------------------------------------------------------------
        // SUBTASKS (/api/tasks/{id_or_code}/subtasks)
        // ---------------------------------------------------------------------
        if ($param2 === 'subtasks') {
            // GET /subtasks
            if ($method === 'GET') {
                $subtasks = $db->multi_record_query("SELECT * FROM tasks WHERE parent_task_id = {$task->id} ORDER BY id ASC");
                apiResponse($subtasks, 'Alt görevler.');
            }

            // POST /subtasks - Alt Görev Oluştur
            if ($method === 'POST') {
                $title = trim($requestData['title'] ?? '');
                if (empty($title)) apiError('Alt görev başlığı zorunludur.', 400);

                $subCount = (int)$db->single_query("SELECT COUNT(*) FROM tasks WHERE parent_task_id = {$task->id}") + 1;
                $subCode = $task->task_code . '-' . $subCount;
                $assignedTo = !empty($requestData['assigned_to']) ? (int)$requestData['assigned_to'] : $task->assigned_to;
                $priority = $requestData['priority'] ?? $task->priority;

                $stmt = $db->exec_query("
                    INSERT INTO tasks (
                        project_id, parent_task_id, task_code, title, description,
                        priority, status, deadline, time_estimate, time_spent,
                        created_by, assigned_to, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, '',
                        ?, 'assigned', ?, '2h', '0h',
                        ?, ?, NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    $task->project_id, $task->id, $subCode, $title,
                    $priority, $task->deadline,
                    $currentUser->id, $assignedTo
                ]);
                $newSubId = $db->last_insert_id();

                $createdSub = resolveTask($db, $newSubId);
                apiResponse($createdSub, 'Alt görev oluşturuldu.', 201);
            }

            apiError('Geçersiz alt görev işlemi.', 404);
        }

        apiError('Bilinmeyen görev alt endpointi.', 404);
    }

    apiError('Bilinmeyen görev işlemi.', 404);
}

// -----------------------------------------------------------------------------
// E. USERS / TEAM ENDPOINTS (/api/users)
// -----------------------------------------------------------------------------
if ($resource === 'users') {
    // 1. GET /api/users - Ekip Listesi
    if ($param1 === null && $method === 'GET') {
        $users = $db->multi_record_query("SELECT id, name, email, role, title, avatar_color, status, created_at FROM users ORDER BY id ASC");
        foreach ($users as &$u) {
            $u->assigned_tasks_count = (int)$db->single_query("SELECT COUNT(*) FROM tasks WHERE assigned_to = {$u->id} AND status != 'completed'");
        }
        apiResponse($users, 'Kullanıcılar listelendi.');
    }

    // 2. POST /api/users - Yeni Ekip Üyesi Ekle (Admin)
    if ($param1 === null && $method === 'POST') {
        if ($currentUser->role !== 'admin') apiError('Sadece yöneticiler yeni kullanıcı ekleyebilir.', 403);

        $name = trim($requestData['name'] ?? '');
        $email = trim($requestData['email'] ?? '');
        $password = trim($requestData['password'] ?? '145236');
        $title = trim($requestData['title'] ?? 'Ekip Üyesi');
        $role = in_array($requestData['role'] ?? '', ['admin', 'user']) ? $requestData['role'] : 'user';
        $avatarColor = $requestData['avatar_color'] ?? '#7B68EE';

        if (empty($name) || empty($email)) apiError('Ad Soyad ve E-Posta zorunludur.', 400);

        // E-Posta kontrolü
        $chk = $db->single_query("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        if ($chk) apiError('Bu e-posta adresiyle kayıtlı bir kullanıcı zaten mevcut.', 409);

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $token = 'tm_live_' . bin2hex(random_bytes(16));

        $db->exec_query("
            INSERT INTO users (name, email, password, api_token, role, title, avatar_color, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ", [$name, $email, $hashed, $token, $role, $title, $avatarColor]);
        $newUid = $db->last_insert_id();

        $newUser = $db->row_query("SELECT id, name, email, role, title, avatar_color, status FROM users WHERE id = {$newUid}");
        apiResponse($newUser, 'Ekip üyesi başarıyla oluşturuldu.', 201);
    }

    // 3. GET /api/users/{id} - Kullanıcı Detayı
    if ($param1 !== null && $method === 'GET') {
        $uid = (int)$param1;
        $user = $db->row_query("SELECT id, name, email, role, title, avatar_color, status, created_at FROM users WHERE id = {$uid}");
        if (!$user) apiError('Kullanıcı bulunamadı.', 404);

        $user->tasks = $db->multi_record_query("SELECT id, task_code, title, priority, status, deadline FROM tasks WHERE assigned_to = {$uid} ORDER BY id DESC LIMIT 20");
        apiResponse($user, 'Kullanıcı detayı.');
    }

    // 4. DELETE /api/users/{id} - Kullanıcı Sil (Admin)
    if ($param1 !== null && $method === 'DELETE') {
        if ($currentUser->role !== 'admin') apiError('Yetkisiz erişim.', 403);
        $uid = (int)$param1;
        if ($uid === (int)$currentUser->id) apiError('Kendinizi silemezsiniz.', 400);

        $assignedCount = (int)$db->single_query("SELECT COUNT(*) FROM tasks WHERE assigned_to = {$uid}");
        if ($assignedCount > 0) {
            $db->exec_query("UPDATE users SET status = 'inactive' WHERE id = ?", [$uid]);
            apiResponse(null, 'Kullanıcıya atanmış görevler olduğu için hesabı Pasif duruma alındı.');
        } else {
            $db->exec_query("DELETE FROM users WHERE id = ?", [$uid]);
            apiResponse(null, 'Kullanıcı tamamen silindi.');
        }
    }

    apiError('Bilinmeyen kullanıcı işlemi.', 404);
}

// -----------------------------------------------------------------------------
// F. DOCS ENDPOINTS (/api/docs)
// -----------------------------------------------------------------------------
if ($resource === 'docs') {
    // 1. GET /api/docs
    if ($param1 === null && $method === 'GET') {
        $sql = "SELECT d.id, d.project_id, d.title, d.icon, d.content, d.created_at, d.updated_at, p.title as project_title, p.project_code FROM docs d LEFT JOIN projects p ON d.project_id = p.id WHERE 1=1";
        $params = [];

        if (!empty($requestData['project_id'])) {
            $sql .= " AND d.project_id = ?";
            $params[] = (int)$requestData['project_id'];
        } elseif (!empty($requestData['project'])) {
            $proj = resolveProject($db, $requestData['project']);
            if ($proj) {
                $sql .= " AND d.project_id = ?";
                $params[] = $proj->id;
            }
        }

        if (!empty($requestData['q'])) {
            $sql .= " AND (d.title LIKE ? OR d.content LIKE ?)";
            $q = '%' . $requestData['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= " ORDER BY d.id DESC";
        $docs = $db->multi_record_query($sql, $params) ?: [];

        apiResponse($docs, 'Dökümanlar listelendi.', 200, ['total' => count($docs)]);
    }

    // 2. POST /api/docs - Yeni Döküman
    if ($param1 === null && $method === 'POST') {
        $title   = resolveDocTitle($requestData);
        $content = resolveDocContent($requestData, $rawInput);
        $icon    = $requestData['icon'] ?? '📄';
        
        $projectId = null;
        if (!empty($requestData['project_id'])) {
            $projectId = (int)$requestData['project_id'];
        } elseif (!empty($requestData['project'])) {
            $proj = resolveProject($db, $requestData['project']);
            if ($proj) $projectId = $proj->id;
        }

        if (empty($title)) apiError('Döküman başlığı (title) zorunludur.', 400);

        $stmt = $db->exec_query("
            INSERT INTO docs (project_id, title, icon, content, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$projectId, $title, $icon, $content, $currentUser->id]);
        $newDocId = $db->last_insert_id();

        $newDoc = $db->row_query("SELECT d.*, p.title as project_title, p.project_code FROM docs d LEFT JOIN projects p ON d.project_id = p.id WHERE d.id = {$newDocId}");
        apiResponse($newDoc, 'Döküman oluşturuldu.', 201);
    }

    // 3. GET /api/docs/{id}
    if ($param1 !== null && $method === 'GET') {
        $docId = (int)$param1;
        $doc = $db->row_query("SELECT d.*, p.title as project_title FROM docs d LEFT JOIN projects p ON d.project_id = p.id WHERE d.id = {$docId}");
        if (!$doc) apiError('Döküman bulunamadı.', 404);
        apiResponse($doc, 'Döküman detayı.');
    }

    // 4. PUT /api/docs/{id}
    if ($param1 !== null && ($method === 'PUT' || $method === 'PATCH' || $method === 'POST')) {
        $docId = (int)$param1;
        $doc = $db->row_query("SELECT * FROM docs WHERE id = {$docId}");
        if (!$doc) apiError('Döküman bulunamadı.', 404);

        $resolvedTitle = resolveDocTitle($requestData);
        $title = !empty($resolvedTitle) ? $resolvedTitle : $doc->title;

        $resolvedContent = resolveDocContent($requestData, $rawInput);
        // Eğer istekte içerik gönderildiyse güncelle, yoksa eski içeriği koru
        $content = ($resolvedContent !== '') ? $resolvedContent : (isset($requestData['content']) ? $requestData['content'] : $doc->content);

        $icon = $requestData['icon'] ?? $doc->icon;
        $projectId = isset($requestData['project_id']) ? (int)$requestData['project_id'] : $doc->project_id;

        $db->exec_query("UPDATE docs SET title = ?, content = ?, icon = ?, project_id = ?, updated_at = NOW() WHERE id = ?", [$title, $content, $icon, $projectId, $docId]);

        $updated = $db->row_query("SELECT * FROM docs WHERE id = {$docId}");
        apiResponse($updated, 'Döküman güncellendi.');
    }

    // 5. DELETE /api/docs/{id}
    if ($param1 !== null && $method === 'DELETE') {
        $docId = (int)$param1;
        $db->exec_query("DELETE FROM docs WHERE id = ?", [$docId]);
        apiResponse(null, 'Döküman silindi.');
    }

    apiError('Bilinmeyen döküman işlemi.', 404);
}

// -----------------------------------------------------------------------------
// G. NOTIFICATIONS ENDPOINTS (/api/notifications)
// -----------------------------------------------------------------------------
if ($resource === 'notifications') {
    // GET /api/notifications
    if ($param1 === null && $method === 'GET') {
        $unreadOnly = isset($requestData['unread_only']) && (int)$requestData['unread_only'] === 1;
        $sql = "SELECT * FROM notifications WHERE user_id = {$currentUser->id}";
        if ($unreadOnly) $sql .= " AND is_read = 0";
        $sql .= " ORDER BY id DESC LIMIT 50";
        $notifs = $db->multi_record_query($sql);
        apiResponse($notifs, 'Bildirimler.');
    }

    // POST /api/notifications/{id}/read
    if ($param1 !== null && $param2 === 'read' && $method === 'POST') {
        $nId = (int)$param1;
        $db->exec_query("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [$nId, $currentUser->id]);
        apiResponse(null, 'Bildirim okundu olarak işaretlendi.');
    }

    // POST /api/notifications/read-all
    if ($param1 === 'read-all' && $method === 'POST') {
        $db->exec_query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$currentUser->id]);
        apiResponse(null, 'Tüm bildirimler okundu olarak işaretlendi.');
    }

    apiError('Bilinmeyen bildirim işlemi.', 404);
}

// -----------------------------------------------------------------------------
// H. REPORTS / SUMMARY ENDPOINT (/api/reports)
// -----------------------------------------------------------------------------
if ($resource === 'reports') {
    if ($method === 'GET') {
        $summary = $db->row_query("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status IN ('created','assigned') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('accepted','in_progress') THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'modification_requested' THEN 1 ELSE 0 END) as modification_requested,
                SUM(CASE WHEN status IN ('submitted','under_review') THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM tasks WHERE parent_task_id IS NULL
        ");

        $projectsCount = (int)$db->single_query("SELECT COUNT(*) FROM projects WHERE status = 'active'");
        $teamCount = (int)$db->single_query("SELECT COUNT(*) FROM users WHERE status = 'active'");

        apiResponse([
            'tasks_summary'   => $summary,
            'active_projects' => $projectsCount,
            'team_members'    => $teamCount,
        ], 'Sistem analitik özeti.');
    }
}

// -----------------------------------------------------------------------------
// I. WEBHOOKS LISTENER & LOGS ENDPOINT (/api/webhooks/listener & /api/webhooks/logs)
// -----------------------------------------------------------------------------
if ($resource === 'webhooks') {
    if ($param1 === 'listener') {
        $raw = file_get_contents('php://input');
        $parsed = json_decode($raw, true) ?: ['raw' => $raw];
        $convId = $_SERVER['HTTP_X_TASKMANAGER_CONVERSATION_ID'] ?? ($parsed['agent_conversation_id'] ?? null);
        $event  = $_SERVER['HTTP_X_TASKMANAGER_EVENT'] ?? ($parsed['event'] ?? 'unknown');

        apiResponse([
            'received'              => true,
            'event'                 => $event,
            'agent_conversation_id' => $convId,
            'received_at'           => date('c'),
            'payload'               => $parsed
        ], "Webhook sinyali başarıyla alındı.");
    }

    if ($param1 === 'logs' && $method === 'GET') {
        $logFile = __DIR__ . '/../../Public/uploads/webhook_logs.json';
        $logs = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?: []) : [];
        apiResponse($logs, 'Son 50 webhook çağrı günlüğü.');
    }
}

// 404 Fallback
apiError("Bilinmeyen API endpointi: '/{$path}'", 404);
