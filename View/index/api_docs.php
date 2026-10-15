<?php
// TaskManager Interactive REST API & AI Agent Documentation
require_once __DIR__ . '/../../System/System.php';
require_once __DIR__ . '/../../System/Language.php';

Language::init();
$currentLang = Language::current(); // 'tr', 'en', 'de'

$translations = [
    'tr' => [
        'page_title' => 'REST API & Yapay Zeka Entegrasyon Kılavuzu - TaskManager',
        'api_version' => 'TaskManager Enterprise REST API v1.0',
        'badge_active' => 'Aktif & Yayında',
        'header_desc' => 'Arayüzdeki tüm özellikleri (Projeler, Görevler, Kanban akışları, Teslimat, Revizyon, Yorumlar, Dökümanlar) yapay zekalar veya harici yazılımlar için uçtan uca sunan REST API servisi.',
        'btn_markdown' => 'Markdown Olarak İndir',
        'btn_health' => 'API Sağlık Kontrolü',
        'token_label' => 'Giriş Yapmış Kullanıcı Tokenı',
        'token_desc' => 'Tüm HTTP isteklerinizde aşağıdaki başlığı kullanarak tam yetkiyle işlem yapabilirsiniz.',
        'btn_copy_token' => 'Token Kopyala',
        'token_copied' => '✅ API Token panoya kopyalandı!',
        'i18n_title' => 'Çok Dilli REST API Desteği (Internationalization / I18n)',
        'i18n_subtitle' => 'API yanıt mesajlarını ve hata açıklamalarını dilediğiniz dilde (Türkçe, İngilizce, Almanca) alın. API istemcileri ve yapay zekalar istedikleri dilde işlem yapabilir.',
        'i18n_m1_title' => '1. URL Sorgu Parametresi:',
        'i18n_m1_desc' => '?lang=en veya ?lang=de veya ?lang=tr',
        'i18n_m2_title' => '2. HTTP Başlığı (Header):',
        'i18n_m2_desc' => 'Accept-Language: de veya X-Language: en',
        'i18n_m3_title' => '3. JSON İstek Gövdesi (Body):',
        'i18n_m3_desc' => '{"lang": "en", ...}',
        'i18n_m4_title' => '4. Kullanıcı Profil Tercihi:',
        'i18n_m4_desc' => 'Token sahibinin profilindeki dil tercihi otomatik uygulanır',
        'ai_scenario_title' => 'Yapay Zeka (Antigravity Agent) İçin Uçtan Uca Görev İşleme Senaryosu',
        'ai_scenario_subtitle' => 'Bir yapay zekaya veya insan geliştiriciye görev atayıp otonom teslim almanın tam API akışı',
        'ai_prompt_label' => 'Örnek Yapay Zeka / Agent İstemi (Prompt):',
        'ai_prompt_text' => '"Sen bu sistemde görev alan bir yapay zeka geliştiricisisin. Proje: ETC (E-Ticaret Platformu Yenileme) senin çalışma alanındır. API üzerinden bu projedeki yapılacak (pending/assigned) durumundaki görevleri sorgula, sırayla kabul et (in_progress yap), işleri tamamla, kontrol listelerini işaretle, açıklayıcı yorumlar yaz ve görevi GitHub linkiyle birlikte yönetici onayına teslim et."',
        'step_1_title' => 'Görevleri Listele',
        'step_1_desc' => 'Projedeki bekleyen tüm işleri öncelik sırasıyla çeker.',
        'step_2_title' => 'İşi Kabul Et & Başla',
        'step_2_desc' => 'Görevi "Çalışılıyor (in_progress)" durumuna geçirir.',
        'step_3_title' => 'Gelişmeleri Bildir',
        'step_3_desc' => 'Yaptığı testleri ve ara notları yorum olarak yazar.',
        'step_4_title' => 'Yöneticiye Teslim Et',
        'step_4_desc' => 'Test sonuçları ve GitHub linki ile teslimatı kapatır.',
        'step_label' => 'ADIM',
        'endpoints_title' => 'Tüm REST API Endpointleri ve Örnek İstekler',
        'endpoints_note' => 'Not: Tüm GET, POST, PUT, DELETE isteklerinde ?lang=en, ?lang=tr veya ?lang=de parametresi kullanılabilir.',
        'sec1_title' => '1. Kimlik Doğrulama & Kullanıcı Hesabı',
        'sec1_ep1' => 'E-Posta ve şifre ile token üretir. Body: {"email":"...", "password":"..."}',
        'sec1_ep2' => 'Giriş yapmış kullanıcının profilini ve aktif görev sayısını getirir.',
        'sec1_ep3' => 'Kendi profilini (Ad, Unvan, Avatar rengi, Şifre) günceller.',
        'sec2_title' => '2. Projeler (Spaces)',
        'sec2_ep1' => 'Tüm projeleri görev istatistikleriyle birlikte listeler. Filtreler: ?status=active|completed|all&lang=tr',
        'sec2_ep2' => 'Yeni proje oluşturur. Body: title, project_code, description, color, icon',
        'sec2_ep3' => 'Proje detaylarını ve durum istatistiklerini getirir. (Örn: /projects/ETC)',
        'sec2_ep4' => '[AI Favori] Projeye ait görevleri getirir. Filtreler: ?status=pending|in_progress|completed&priority=urgent&assigned_to=me&lang=tr',
        'sec2_ep5' => '[AI / Döküman] Projenin tüm dökümanlarını (FTP, sunucu bilgileri, rehberler) ve tam içeriklerini döner.',
        'sec2_ep6' => 'Projeye doğrudan yeni döküman ekler. Body: title, content, icon',
        'sec2_ep7' => 'Proje bilgilerini, webhook_url ve agent_conversation_id alanlarını günceller.',
        'sec2_ep8' => '[AI Webhook] Projenin tanımlı webhook adresine test pingi fırlatır ve HTTP yanıtını döner.',
        'sec2_ep9' => 'Projeyi geçmiş projelere taşır veya kalıcı olarak siler (?force=1).',
        'sec3_title' => '3. Görevler & Kanban İş Akışları',
        'sec3_ep1' => 'Genel görev listesi. Filtreler: ?project=ETC&status=in_progress&priority=high&assigned_to=me&q=ödeme&lang=tr',
        'sec3_ep2' => 'Yeni görev oluşturur. Body: title, project_id, description, priority, deadline, assignee_ids, checklists[]',
        'sec3_ep3' => 'Görevin tüm detaylarını (Atananlar, Alt Görevler, Checklistler, Yorumlar, Teslimat) getirir.',
        'sec3_ep4' => 'Görev alanlarını günceller (başlık, açıklama, öncelik, deadline, tahmini süre).',
        'sec3_workflow_label' => 'Kanban Durum Geçişleri (Workflow Transitions)',
        'sec3_wf1' => 'Görevi kabul eder ve durumunu in_progress (Çalışılıyor) yapar.',
        'sec3_wf2' => 'Doğrudan durum değiştirir. Body: status: "completed"|"in_progress"|"assigned"',
        'sec3_wf3' => 'Revizyon talep eder. Body: reason: "Mobil taşma düzeltilmeli"',
        'sec3_wf4' => 'Görevi yöneticiye teslim eder. Body: notes: "Tamamlandı", attachment_url: "https://..."',
        'sec3_wf5' => '(Admin) Teslimatı onaylar (approved) veya reddeder (rejected). Body: decision, feedback',
        'sec4_title' => '4. Kontrol Listeleri, Yorumlar & Çalışan Atama',
        'sec4_ep1' => 'Kontrol listesine yeni madde ekler. Body: title: "Testler koşuldu"',
        'sec4_ep2' => 'Maddenin tamamlanma durumunu değiştirir. Body: is_completed: 1',
        'sec4_ep3' => 'Göreve yorum / soru-cevap ekler. Body: comment: "..."',
        'sec4_ep4' => 'Göreve yeni çalışan atar. Body: user_id: 2',
        'sec4_ep5' => 'Çalışanı görevden çıkarır.',
        'sec5_title' => '5. Dökümanlar, Ekip & Raporlar',
        'sec5_ep1' => 'Dökümanları listeler. Filtreler: ?project=ETC veya ?project_id=1&lang=tr',
        'sec5_ep2' => 'Belirli dökümanın tüm içeriğini döner.',
        'sec5_ep3' => 'Yeni doküman oluşturur. Body: title, content, project (veya project_id), icon',
        'sec5_ep4' => 'Döküman başlığı, içeriği veya ikonunu günceller.',
        'sec5_ep5' => 'Dökümanı siler.',
        'sec5_ep6' => 'Tüm ekip üyelerini ve aktif görev yüklerini getirir.',
        'sec5_ep7' => 'Sistem genelindeki tamamlanma oranları ve görev dağılım analitiği.',
        'sec6_title' => '6. Webhooks & Yapay Zeka (AI Agent) Entegrasyonu',
        'sec6_ep1' => 'Projeye bağlı ajana test pingi fırlatır (CSRF & Port doğrulama).',
        'sec6_ep2' => 'Son 50 webhook gönderim günlüğünü ve HTTP durum kodlarını listeler.',
        'sec6_ep3' => 'Projeye özel webhook_url, workspace_path ve agent_conversation_id (\'auto\') tanımlar.',
        'sec6_ep4' => 'Dış sistemlerden gelen webhook sinyallerini kabul eder ve günlüğe yazar.'
    ],
    'en' => [
        'page_title' => 'REST API & AI Agent Integration Guide - TaskManager',
        'api_version' => 'TaskManager Enterprise REST API v1.0',
        'badge_active' => 'Active & Live',
        'header_desc' => 'Comprehensive enterprise REST API providing full programmatic access to Projects, Tasks, Kanban workflows, Submissions, Revisions, Checklists, Comments, and Documents for AI agents and external systems.',
        'btn_markdown' => 'Download as Markdown',
        'btn_health' => 'API Health Check',
        'token_label' => 'Authenticated User Token',
        'token_desc' => 'Include the following Bearer token in your HTTP headers to perform fully authorized operations.',
        'btn_copy_token' => 'Copy Token',
        'token_copied' => '✅ API Token copied to clipboard!',
        'i18n_title' => 'Multi-Language REST API Support (Internationalization / I18n)',
        'i18n_subtitle' => 'Receive API response messages and error explanations in your preferred language (Turkish, English, German). Clients and AI agents can choose any language on-the-fly.',
        'i18n_m1_title' => '1. URL Query Parameter:',
        'i18n_m1_desc' => '?lang=en or ?lang=de or ?lang=tr',
        'i18n_m2_title' => '2. HTTP Request Header:',
        'i18n_m2_desc' => 'Accept-Language: de or X-Language: en',
        'i18n_m3_title' => '3. JSON Request Body:',
        'i18n_m3_desc' => '{"lang": "en", ...}',
        'i18n_m4_title' => '4. User Profile Preference:',
        'i18n_m4_desc' => 'Token owner\'s profile language preference is automatically applied',
        'ai_scenario_title' => 'End-to-End Task Execution Scenario for AI (Antigravity Agent)',
        'ai_scenario_subtitle' => 'Complete API lifecycle for delegating tasks to an autonomous AI agent and accepting verified deliverables',
        'ai_prompt_label' => 'Example AI / Agent Directive (Prompt):',
        'ai_prompt_text' => '"You are an autonomous AI developer on this platform. Project: ETC (E-Commerce Platform Redesign) is your workspace. Query pending/assigned tasks via API, accept them sequentially (transition to in_progress), complete all implementations, check off checklist items, post descriptive progress comments, and submit the deliverables with test evidence and GitHub link for admin review."',
        'step_1_title' => 'List Project Tasks',
        'step_1_desc' => 'Queries pending project tasks ordered by priority.',
        'step_2_title' => 'Accept & Begin Work',
        'step_2_desc' => 'Transitions task status to "in_progress".',
        'step_3_title' => 'Report Progress',
        'step_3_desc' => 'Posts test results and development notes as task comments.',
        'step_4_title' => 'Submit to Manager',
        'step_4_desc' => 'Submits completed task with test evidence and GitHub links.',
        'step_label' => 'STEP',
        'endpoints_title' => 'All REST API Endpoints & Request Examples',
        'endpoints_note' => 'Note: All GET, POST, PUT, DELETE requests support ?lang=en, ?lang=tr, or ?lang=de query parameter.',
        'sec1_title' => '1. Authentication & User Profile',
        'sec1_ep1' => 'Generates API token using email and password. Body: {"email":"...", "password":"..."}',
        'sec1_ep2' => 'Returns authenticated user profile and active task statistics.',
        'sec1_ep3' => 'Updates own user profile (Name, Title, Avatar color, Password).',
        'sec2_title' => '2. Projects (Spaces)',
        'sec2_ep1' => 'Lists all projects with task statistics. Filters: ?status=active|completed|all&lang=en',
        'sec2_ep2' => 'Creates a new project. Body: title, project_code, description, color, icon',
        'sec2_ep3' => 'Retrieves project details and status metrics. (e.g. /projects/ETC)',
        'sec2_ep4' => '[AI Favorite] Retrieves project tasks. Filters: ?status=pending|in_progress|completed&priority=urgent&assigned_to=me&lang=en',
        'sec2_ep5' => '[AI / Docs] Returns all project documents (FTP, server credentials, guides) with full content.',
        'sec2_ep6' => 'Adds new document directly to project. Body: title, content, icon',
        'sec2_ep7' => 'Updates project details, webhook_url, and agent_conversation_id fields.',
        'sec2_ep8' => '[AI Webhook] Sends test ping to configured project webhook URL and returns HTTP response.',
        'sec2_ep9' => 'Archives project or permanently deletes it (?force=1).',
        'sec3_title' => '3. Tasks & Kanban Workflows',
        'sec3_ep1' => 'General task listing. Filters: ?project=ETC&status=in_progress&priority=high&assigned_to=me&q=checkout&lang=en',
        'sec3_ep2' => 'Creates a new task. Body: title, project_id, description, priority, deadline, assignee_ids, checklists[]',
        'sec3_ep3' => 'Returns complete task details (Assignees, Subtasks, Checklists, Comments, Submission).',
        'sec3_ep4' => 'Updates task fields (title, description, priority, deadline, estimated hours).',
        'sec3_workflow_label' => 'Kanban Workflow Transitions',
        'sec3_wf1' => 'Accepts task and changes status to in_progress.',
        'sec3_wf2' => 'Changes status directly. Body: status: "completed"|"in_progress"|"assigned"',
        'sec3_wf3' => 'Requests revision / modification. Body: reason: "Fix mobile viewport overflow"',
        'sec3_wf4' => 'Submits completed task for manager review. Body: notes: "Completed", attachment_url: "https://..."',
        'sec3_wf5' => '(Admin) Reviews submission: approves or rejects. Body: decision, feedback',
        'sec4_title' => '4. Checklists, Comments & Assignees',
        'sec4_ep1' => 'Adds a new item to task checklist. Body: title: "Automated tests passed"',
        'sec4_ep2' => 'Toggles checklist item completion state. Body: is_completed: 1',
        'sec4_ep3' => 'Adds comment / QA note to task. Body: comment: "..."',
        'sec4_ep4' => 'Assigns a user to the task. Body: user_id: 2',
        'sec4_ep5' => 'Removes user from the task.',
        'sec5_title' => '5. Documents, Team & Reports',
        'sec5_ep1' => 'Lists documents. Filters: ?project=ETC or ?project_id=1&lang=en',
        'sec5_ep2' => 'Returns full content of specific document.',
        'sec5_ep3' => 'Creates new document. Body: title, content, project (or project_id), icon',
        'sec5_ep4' => 'Updates document title, content, or icon.',
        'sec5_ep5' => 'Deletes document.',
        'sec5_ep6' => 'Lists all team members and their active workloads.',
        'sec5_ep7' => 'Returns system-wide task completion rates and workload analytics.',
        'sec6_title' => '6. Webhooks & AI Agent Integration Bridge',
        'sec6_ep1' => 'Dispatches test ping to project AI agent listener (CSRF & Port verification).',
        'sec6_ep2' => 'Lists last 50 webhook dispatch logs and HTTP response status codes.',
        'sec6_ep3' => 'Configures project webhook_url, workspace_path, and agent_conversation_id (\'auto\').',
        'sec6_ep4' => 'Receives and logs incoming webhook signals from external systems.'
    ],
    'de' => [
        'page_title' => 'REST-API & KI-Agenten-Integrationsleitfaden - TaskManager',
        'api_version' => 'TaskManager Enterprise REST-API v1.0',
        'badge_active' => 'Aktiv & Online',
        'header_desc' => 'Umfassende Enterprise-REST-API für KI-Agenten und externe Software zur vollständigen Steuerung von Projekten, Aufgaben, Kanban-Workflows, Einreichungen, Revisionen, Checklisten, Kommentaren und Dokumenten.',
        'btn_markdown' => 'Als Markdown herunterladen',
        'btn_health' => 'API-Statusprüfung',
        'token_label' => 'Authentifizierter Benutzertoken',
        'token_desc' => 'Fügen Sie den folgenden Bearer-Token in Ihre HTTP-Header ein, um voll autorisierte Anfragen auszuführen.',
        'btn_copy_token' => 'Token kopieren',
        'token_copied' => '✅ API-Token in die Zwischenablage kopiert!',
        'i18n_title' => 'Mehrsprachige REST-API-Unterstützung (Internationalisierung / I18n)',
        'i18n_subtitle' => 'Erhalten Sie alle API-Statusmeldungen und Fehlererklärungen in Ihrer bevorzugten Sprache (Türkisch, Englisch, Deutsch). Clients und KI-Agenten können die Sprache dynamisch wählen.',
        'i18n_m1_title' => '1. URL-Abfrageparameter:',
        'i18n_m1_desc' => '?lang=en oder ?lang=de oder ?lang=tr',
        'i18n_m2_title' => '2. HTTP-Anforderungsheader:',
        'i18n_m2_desc' => 'Accept-Language: de oder X-Language: en',
        'i18n_m3_title' => '3. JSON-Anforderungstext (Body):',
        'i18n_m3_desc' => '{"lang": "de", ...}',
        'i18n_m4_title' => '4. Benutzerprofil-Einstellung:',
        'i18n_m4_desc' => 'Die Spracheinstellung des Token-Benutzers wird automatisch angewendet',
        'ai_scenario_title' => 'End-to-End-Aufgabenausführungsszenario für KI (Antigravity Agent)',
        'ai_scenario_subtitle' => 'Vollständiger API-Lebenszyklus für die autonome Aufgabenbearbeitung durch KI-Agenten und formelle Abnahme',
        'ai_prompt_label' => 'Beispielhafte KI- / Agenten-Anweisung (Prompt):',
        'ai_prompt_text' => '"Sie sind ein autonomer KI-Entwickler auf dieser Plattform. Projekt: ETC (E-Commerce-Plattform-Relaunch) ist Ihr Arbeitsbereich. Rufen Sie ausstehende Aufgaben per API ab, nehmen Sie diese sequenziell an (Status auf in_progress), setzen Sie die Anforderungen um, haken Sie Checklisten ab, verfassen Sie Fortschrittskommentare und reichen Sie die Abnahme mit Testnachweisen und GitHub-Link ein."',
        'step_1_title' => 'Aufgaben auflisten',
        'step_1_desc' => 'Ruft alle ausstehenden Aufgaben des Projekts nach Priorität sortiert ab.',
        'step_2_title' => 'Aufgabe annehmen & starten',
        'step_2_desc' => 'Versetzt den Aufgabenstatus in "in_progress".',
        'step_3_title' => 'Fortschritt melden',
        'step_3_desc' => 'Fügt Testergebnisse und Entwicklungsnotizen als Kommentare hinzu.',
        'step_4_title' => 'Zur Überprüfung einreichen',
        'step_4_desc' => 'Reicht die fertige Aufgabe mit Testnachweisen und Links zur Freigabe ein.',
        'step_label' => 'SCHRITT',
        'endpoints_title' => 'Alle REST-API-Endpunkte & Anforderungsbeispiele',
        'endpoints_note' => 'Hinweis: Alle GET-, POST-, PUT-, DELETE-Anfragen unterstützen den Parameter ?lang=en, ?lang=de oder ?lang=tr.',
        'sec1_title' => '1. Authentifizierung & Benutzerkonto',
        'sec1_ep1' => 'Erzeugt API-Token über E-Mail und Passwort. Body: {"email":"...", "password":"..."}',
        'sec1_ep2' => 'Liefert das authentifizierte Benutzerprofil und aktive Aufgabenstatistiken.',
        'sec1_ep3' => 'Aktualisiert das eigene Benutzerprofil (Name, Titel, Avatarfarbe, Passwort).',
        'sec2_title' => '2. Projekte (Spaces)',
        'sec2_ep1' => 'Listet alle Projekte mit Aufgabenstatistiken auf. Filter: ?status=active|completed|all&lang=de',
        'sec2_ep2' => 'Erstellt ein neues Projekt. Body: title, project_code, description, color, icon',
        'sec2_ep3' => 'Ruft Projektdetails und Kennzahlen ab. (z. B. /projects/ETC)',
        'sec2_ep4' => '[KI-Favorit] Ruft Projektaufgaben ab. Filter: ?status=pending|in_progress|completed&priority=urgent&assigned_to=me&lang=de',
        'sec2_ep5' => '[KI / Dokumente] Liefert alle Projektdokumente (FTP, Serverzugänge, Leitfäden) mit vollständigem Inhalt.',
        'sec2_ep6' => 'Fügt neues Dokument direkt zum Projekt hinzu. Body: title, content, icon',
        'sec2_ep7' => 'Aktualisiert Projektdaten, webhook_url und agent_conversation_id.',
        'sec2_ep8' => '[KI-Webhook] Sendet Test-Ping an die konfigurierte Webhook-Adresse und liefert HTTP-Antwort.',
        'sec2_ep9' => 'Archiviert das Projekt oder löscht es dauerhaft (?force=1).',
        'sec3_title' => '3. Aufgaben & Kanban-Workflows',
        'sec3_ep1' => 'Allgemeine Aufgabenliste. Filter: ?project=ETC&status=in_progress&priority=high&assigned_to=me&q=zahlung&lang=de',
        'sec3_ep2' => 'Erstellt neue Aufgabe. Body: title, project_id, description, priority, deadline, assignee_ids, checklists[]',
        'sec3_ep3' => 'Liefert alle Aufgabendetails (Zugewiesene, Teilaufgaben, Checklisten, Kommentare, Einreichung).',
        'sec3_ep4' => 'Aktualisiert Aufgabenfelder (Titel, Beschreibung, Priorität, Frist, geschätzte Stunden).',
        'sec3_workflow_label' => 'Kanban-Workflow-Statusübergänge',
        'sec3_wf1' => 'Nimmt Aufgabe an und ändert Status auf in_progress.',
        'sec3_wf2' => 'Ändert Status direkt. Body: status: "completed"|"in_progress"|"assigned"',
        'sec3_wf3' => 'Fordert Revision / Nachbesserung an. Body: reason: "Mobil-Layout anpassen"',
        'sec3_wf4' => 'Reicht fertige Aufgabe zur Freigabe ein. Body: notes: "Fertiggestellt", attachment_url: "https://..."',
        'sec3_wf5' => '(Admin) Überprüft Einreichung: genehmigt (approved) oder lehnt ab (rejected). Body: decision, feedback',
        'sec4_title' => '4. Checklisten, Kommentare & Zuweisungen',
        'sec4_ep1' => 'Fügt neuen Punkt zur Checkliste hinzu. Body: title: "Automatisierte Tests bestanden"',
        'sec4_ep2' => 'Schaltet Erledigungsstatus eines Checklist-Punkts um. Body: is_completed: 1',
        'sec4_ep3' => 'Fügt Kommentar zur Aufgabe hinzu. Body: comment: "..."',
        'sec4_ep4' => 'Weist der Aufgabe einen Benutzer zu. Body: user_id: 2',
        'sec4_ep5' => 'Entfernt Benutzer von der Aufgabe.',
        'sec5_title' => '5. Dokumente, Team & Berichte',
        'sec5_ep1' => 'Listet Dokumente auf. Filter: ?project=ETC oder ?project_id=1&lang=de',
        'sec5_ep2' => 'Liefert den vollständigen Inhalt eines bestimmten Dokuments.',
        'sec5_ep3' => 'Erstellt neues Dokument. Body: title, content, project (oder project_id), icon',
        'sec5_ep4' => 'Aktualisiert Titel, Inhalt oder Icon des Dokuments.',
        'sec5_ep5' => 'Löscht Dokument.',
        'sec5_ep6' => 'Listet alle Teammitglieder und deren aktuelle Auslastung auf.',
        'sec5_ep7' => 'Liefert plattformweite Abschlussquoten und Aufgabenverteilungsanalysen.',
        'sec6_title' => '6. Webhooks & KI-Agenten-Brücke',
        'sec6_ep1' => 'Sendet Test-Ping an den angebundenen KI-Agenten (CSRF- & Port-Prüfung).',
        'sec6_ep2' => 'Listet die letzten 50 Webhook-Sendeberichte und HTTP-Statuscodes auf.',
        'sec6_ep3' => 'Konfiguriert webhook_url, workspace_path und agent_conversation_id (\'auto\') für das Projekt.',
        'sec6_ep4' => 'Empfängt und protokolliert eingehende Webhook-Signale von Drittsystemen.'
    ]
];

$loc = in_array($currentLang, ['tr', 'en', 'de'], true) ? $currentLang : 'en';
$t = $translations[$loc] ?? $translations['en'];

$system->title = $t['page_title'];
include __DIR__ . '/../../Components/header.php';

$currentUser = $_SESSION['user'] ?? null;
$dynCfg = Helper::getDynamicApiConfig();
$userToken = (!empty($currentUser->api_token)) ? $currentUser->api_token : ($dynCfg['api_token'] ?: 'tm_live_demo_token');
?>

<div class="cu-view-header" style="border-bottom:1px solid var(--border-subtle); padding:16px 24px; background:var(--bg-card); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:20px;">⚡</span>
            <h1 style="font-size:18px; font-weight:800; color:var(--text-white); margin:0;"><?= htmlspecialchars($t['api_version']) ?></h1>
            <span style="font-size:11px; padding:2px 8px; border-radius:4px; font-weight:700; background:rgba(0,184,148,0.15); color:#00b894; text-transform:uppercase;"><?= htmlspecialchars($t['badge_active']) ?></span>
        </div>
        <p style="font-size:12.5px; color:var(--text-secondary); margin:4px 0 0 0;">
            <?= htmlspecialchars($t['header_desc']) ?>
        </p>
    </div>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <!-- Dil Değiştirici Hızlı Düğmeler -->
        <div style="display:flex; gap:4px; background:rgba(255,255,255,0.04); padding:3px; border-radius:6px; border:1px solid var(--border-subtle);">
            <a href="?lang=tr" class="cu-btn-sm" style="text-decoration:none; padding:4px 8px; font-size:11px; font-weight:700; border-radius:4px; <?= $loc === 'tr' ? 'background:var(--brand-primary); color:#fff;' : 'color:var(--text-muted);' ?>">🇹🇷 TR</a>
            <a href="?lang=en" class="cu-btn-sm" style="text-decoration:none; padding:4px 8px; font-size:11px; font-weight:700; border-radius:4px; <?= $loc === 'en' ? 'background:var(--brand-primary); color:#fff;' : 'color:var(--text-muted);' ?>">🇬🇧 EN</a>
            <a href="?lang=de" class="cu-btn-sm" style="text-decoration:none; padding:4px 8px; font-size:11px; font-weight:700; border-radius:4px; <?= $loc === 'de' ? 'background:var(--brand-primary); color:#fff;' : 'color:var(--text-muted);' ?>">🇩🇪 DE</a>
        </div>
        <a href="/taskmanager/api-test" class="cu-btn-sm" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; text-decoration:none; font-weight:700; display:flex; align-items:center; gap:6px; box-shadow: 0 2px 10px rgba(16,185,129,0.35);">
            <span>🧪</span>
            <span>Canlı API Test Konsolu</span>
        </a>
        <a href="/taskmanager/API_DOCUMENTATION.md" target="_blank" class="cu-btn-sm" style="background:rgba(255,255,255,0.06); color:var(--text-white); text-decoration:none; display:flex; align-items:center; gap:6px;">
            <span>📄</span>
            <span><?= htmlspecialchars($t['btn_markdown']) ?></span>
        </a>
        <a href="/taskmanager/api/health" target="_blank" class="cu-btn-sm" style="background:var(--brand-primary); color:#fff; text-decoration:none; font-weight:600; display:flex; align-items:center; gap:6px;">
            <span>🟢</span>
            <span><?= htmlspecialchars($t['btn_health']) ?></span>
        </a>
    </div>
</div>

<div class="cu-content-scroll" style="padding:24px; max-width:1400px; margin:0 auto;">
    
    <!-- API ERİŞİM & TOKEN KARTI -->
    <div style="background:linear-gradient(135deg, rgba(123, 104, 238, 0.12), rgba(15, 17, 26, 0.6)); border:1px solid rgba(123, 104, 238, 0.3); border-radius:var(--radius-md); padding:20px; margin-bottom:20px;">
        <div style="display:grid; grid-template-columns:1.2fr 2fr; gap:20px; align-items:center;">
            <div>
                <span style="font-size:11px; font-weight:700; color:var(--brand-primary); text-transform:uppercase; letter-spacing:0.5px;"><?= htmlspecialchars($t['token_label']) ?></span>
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:4px 0 6px 0;"><?= htmlspecialchars($currentUser->name ?? 'Talha Keskin') ?> (<?= htmlspecialchars($currentUser->role ?? 'admin') ?>)</h3>
                <p style="font-size:12px; color:var(--text-secondary); margin:0;">
                    <?= htmlspecialchars($t['token_desc']) ?>
                </p>
            </div>
            <div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <div style="flex:1; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:10px 14px; font-family:monospace; font-size:12.5px; color:#00b894; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        Authorization: Bearer <span id="tokenText"><?= htmlspecialchars($userToken) ?></span>
                    </div>
                    <button type="button" class="cu-btn cu-btn-primary" onclick="copyToken()" style="padding:10px 16px; font-size:12px; font-weight:600; white-space:nowrap;">
                        <span>📋</span> <span><?= htmlspecialchars($t['btn_copy_token']) ?></span>
                    </button>
                </div>
                <div style="margin-top:8px; font-size:11.5px; color:var(--text-muted); display:flex; gap:16px; flex-wrap:wrap;">
                    <span>🌐 <b>Base URL:</b> <code style="color:var(--text-white);">http://localhost/taskmanager/api/v1</code></span>
                    <span>🔑 <b>Header:</b> <code style="color:var(--text-white);">Authorization: Bearer &lt;token&gt;</code> <?= $loc === 'de' ? 'oder' : ($loc === 'tr' ? 'veya' : 'or') ?> <code style="color:var(--text-white);">X-API-Key</code></span>
                    <span>🌍 <b>Lang:</b> <code style="color:#00b894;">?lang=<?= htmlspecialchars($loc) ?></code></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 🧪 CANLI API TEST LABORATUVARI TANITIM BANNERI -->
    <div style="background:linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(15, 23, 42, 0.7)); border:1px solid rgba(16, 185, 129, 0.35); border-radius:var(--radius-md); padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;">
        <div style="display:flex; align-items:center; gap:14px;">
            <div style="width:42px; height:42px; border-radius:8px; background:rgba(16, 185, 129, 0.2); display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0;">
                🧪
            </div>
            <div>
                <h4 style="font-size:14.5px; font-weight:800; color:var(--text-white); margin:0;">Canlı REST API Test & Explorer Laboratuvarı Yayında!</h4>
                <p style="font-size:12px; color:var(--text-secondary); margin:3px 0 0 0;">
                    Postman veya Swagger arayüzü gibi tüm uç noktaları canlı parametrelerle test edebilir, JSON yanıtlarını izleyebilir ve anında cURL / PowerShell komutları üretebilirsiniz.
                </p>
            </div>
        </div>
        <a href="/taskmanager/api-test" class="cu-btn-sm" style="background:#10b981; color:#000; text-decoration:none; font-weight:800; padding:8px 16px; border-radius:6px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(16, 185, 129, 0.3);">
            <span>Konsolu Başlat</span>
            <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i>
        </a>
    </div>

    <!-- 🌐 ÇOK DİLLİ API DESTEĞİ KARTI -->
    <div style="background:linear-gradient(135deg, rgba(0, 184, 148, 0.1), rgba(15, 17, 26, 0.6)); border:1px solid rgba(0, 184, 148, 0.35); border-radius:var(--radius-md); padding:18px 24px; margin-bottom:24px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:22px;">🌐</span>
                <div>
                    <h3 style="font-size:15px; font-weight:800; color:var(--text-white); margin:0;"><?= htmlspecialchars($t['i18n_title']) ?></h3>
                    <span style="font-size:11.5px; color:var(--text-secondary);"><?= htmlspecialchars($t['i18n_subtitle']) ?></span>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="?lang=tr" style="text-decoration:none; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:700; <?= $loc === 'tr' ? 'background:rgba(239,68,68,0.25); color:#ef4444; border:1px solid rgba(239,68,68,0.5);' : 'background:rgba(255,255,255,0.06); color:#fff;' ?>">🇹🇷 tr (Türkçe)</a>
                <a href="?lang=en" style="text-decoration:none; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:700; <?= $loc === 'en' ? 'background:rgba(0,184,148,0.25); color:#00b894; border:1px solid rgba(0,184,148,0.5);' : 'background:rgba(255,255,255,0.06); color:#fff;' ?>">🇬🇧 en (English)</a>
                <a href="?lang=de" style="text-decoration:none; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:700; <?= $loc === 'de' ? 'background:rgba(245,158,11,0.25); color:#f59e0b; border:1px solid rgba(245,158,11,0.5);' : 'background:rgba(255,255,255,0.06); color:#fff;' ?>">🇩🇪 de (Deutsch)</a>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:12px; font-size:12px;">
            <div style="background:var(--bg-base); padding:10px 14px; border-radius:6px; border:1px solid var(--border-subtle);">
                <div style="font-weight:700; color:var(--text-white); margin-bottom:4px;"><?= htmlspecialchars($t['i18n_m1_title']) ?></div>
                <code style="color:#00b894; font-size:11.5px;"><?= htmlspecialchars($t['i18n_m1_desc']) ?></code>
            </div>
            <div style="background:var(--bg-base); padding:10px 14px; border-radius:6px; border:1px solid var(--border-subtle);">
                <div style="font-weight:700; color:var(--text-white); margin-bottom:4px;"><?= htmlspecialchars($t['i18n_m2_title']) ?></div>
                <code style="color:#00b894; font-size:11.5px;"><?= htmlspecialchars($t['i18n_m2_desc']) ?></code>
            </div>
            <div style="background:var(--bg-base); padding:10px 14px; border-radius:6px; border:1px solid var(--border-subtle);">
                <div style="font-weight:700; color:var(--text-white); margin-bottom:4px;"><?= htmlspecialchars($t['i18n_m3_title']) ?></div>
                <code style="color:#00b894; font-size:11.5px;"><?= htmlspecialchars($t['i18n_m3_desc']) ?></code>
            </div>
            <div style="background:var(--bg-base); padding:10px 14px; border-radius:6px; border:1px solid var(--border-subtle);">
                <div style="font-weight:700; color:var(--text-white); margin-bottom:4px;"><?= htmlspecialchars($t['i18n_m4_title']) ?></div>
                <span style="color:var(--text-muted); font-size:11.5px;"><?= htmlspecialchars($t['i18n_m4_desc']) ?></span>
            </div>
        </div>
    </div>

    <!-- 🤖 YAPAY ZEKA / ANTIGRAVITY AGENT REHBERİ (ÖZEL VURGU KUTUSU) -->
    <div style="background:var(--bg-card); border:1px solid rgba(0, 184, 148, 0.4); border-radius:var(--radius-md); padding:24px; margin-bottom:28px; box-shadow:0 8px 30px rgba(0,0,0,0.25);">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
            <div style="width:36px; height:36px; border-radius:8px; background:rgba(0,184,148,0.15); display:flex; align-items:center; justify-content:center; font-size:18px;">
                🤖
            </div>
            <div>
                <h2 style="font-size:16px; font-weight:800; color:var(--text-white); margin:0;"><?= htmlspecialchars($t['ai_scenario_title']) ?></h2>
                <span style="font-size:11.5px; color:var(--text-secondary);"><?= htmlspecialchars($t['ai_scenario_subtitle']) ?></span>
            </div>
        </div>

        <div style="background:rgba(0,0,0,0.3); border-radius:8px; padding:16px; border:1px solid var(--border-subtle); margin-bottom:18px;">
            <div style="font-size:12px; font-weight:700; color:#f59e0b; margin-bottom:6px;"><?= htmlspecialchars($t['ai_prompt_label']) ?></div>
            <blockquote style="margin:0; font-style:italic; font-size:13px; color:var(--text-white); line-height:1.5;">
                <?= htmlspecialchars($t['ai_prompt_text']) ?>
            </blockquote>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px; margin-bottom:16px;">
            <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-subtle); border-radius:6px; padding:12px;">
                <span style="display:inline-block; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; background:#7B68EE; color:#fff; margin-bottom:6px;">1. <?= htmlspecialchars($t['step_label']) ?></span>
                <div style="font-weight:700; font-size:13px; color:var(--text-white);"><?= htmlspecialchars($t['step_1_title']) ?></div>
                <div style="font-family:monospace; font-size:11px; color:#00b894; margin-top:4px;">GET /projects/ETC/tasks?status=pending&lang=<?= htmlspecialchars($loc) ?></div>
                <p style="font-size:11.5px; color:var(--text-muted); margin:4px 0 0 0;"><?= htmlspecialchars($t['step_1_desc']) ?></p>
            </div>

            <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-subtle); border-radius:6px; padding:12px;">
                <span style="display:inline-block; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; background:#3b82f6; color:#fff; margin-bottom:6px;">2. <?= htmlspecialchars($t['step_label']) ?></span>
                <div style="font-weight:700; font-size:13px; color:var(--text-white);"><?= htmlspecialchars($t['step_2_title']) ?></div>
                <div style="font-family:monospace; font-size:11px; color:#00b894; margin-top:4px;">POST /tasks/ETC-101/workflow/accept?lang=<?= htmlspecialchars($loc) ?></div>
                <p style="font-size:11.5px; color:var(--text-muted); margin:4px 0 0 0;"><?= htmlspecialchars($t['step_2_desc']) ?></p>
            </div>

            <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-subtle); border-radius:6px; padding:12px;">
                <span style="display:inline-block; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; background:#f59e0b; color:#fff; margin-bottom:6px;">3. <?= htmlspecialchars($t['step_label']) ?></span>
                <div style="font-weight:700; font-size:13px; color:var(--text-white);"><?= htmlspecialchars($t['step_3_title']) ?></div>
                <div style="font-family:monospace; font-size:11px; color:#00b894; margin-top:4px;">POST /tasks/ETC-101/comments?lang=<?= htmlspecialchars($loc) ?></div>
                <p style="font-size:11.5px; color:var(--text-muted); margin:4px 0 0 0;"><?= htmlspecialchars($t['step_3_desc']) ?></p>
            </div>

            <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-subtle); border-radius:6px; padding:12px;">
                <span style="display:inline-block; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; background:#00b894; color:#fff; margin-bottom:6px;">4. <?= htmlspecialchars($t['step_label']) ?></span>
                <div style="font-weight:700; font-size:13px; color:var(--text-white);"><?= htmlspecialchars($t['step_4_title']) ?></div>
                <div style="font-family:monospace; font-size:11px; color:#00b894; margin-top:4px;">POST /tasks/ETC-101/workflow/submit?lang=<?= htmlspecialchars($loc) ?></div>
                <p style="font-size:11.5px; color:var(--text-muted); margin:4px 0 0 0;"><?= htmlspecialchars($t['step_4_desc']) ?></p>
            </div>
        </div>
    </div>

    <!-- DETAYLI API ENDPOINTLERİ -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <h2 style="font-size:16px; font-weight:800; color:var(--text-white); margin:0; display:flex; align-items:center; gap:8px;">
            <span>📚</span> <?= htmlspecialchars($t['endpoints_title']) ?>
        </h2>
        <span style="font-size:11.5px; color:var(--brand-primary); font-weight:600;"><?= htmlspecialchars($t['endpoints_note']) ?></span>
    </div>

    <div style="display:grid; grid-template-columns:1fr; gap:16px;">

        <!-- 1. AUTH & HESAP -->
        <div class="cu-endpoint-card" style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); overflow:hidden;">
            <div style="padding:14px 18px; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between;">
                <span style="font-weight:700; color:var(--text-white); font-size:14px;"><?= htmlspecialchars($t['sec1_title']) ?></span>
                <span style="font-size:11px; color:var(--text-muted);">/api/v1/auth & /api/v1/profile</span>
            </div>
            <div style="padding:16px 18px; display:flex; flex-direction:column; gap:12px;">
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/auth/login</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec1_ep1']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/auth/me</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec1_ep2']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#f59e0b; font-weight:700; font-family:monospace;">PUT / POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/profile</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec1_ep3']) ?></span>
                </div>
            </div>
        </div>

        <!-- 2. PROJELER -->
        <div class="cu-endpoint-card" style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); overflow:hidden;">
            <div style="padding:14px 18px; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between;">
                <span style="font-weight:700; color:var(--text-white); font-size:14px;"><?= htmlspecialchars($t['sec2_title']) ?></span>
                <span style="font-size:11px; color:var(--text-muted);">/api/v1/projects</span>
            </div>
            <div style="padding:16px 18px; display:flex; flex-direction:column; gap:12px;">
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/projects</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep1']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/projects</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep2']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/projects/{id_or_code}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep3']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:#7B68EE; font-weight:700;">/projects/{id_or_code}/tasks</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep4']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:#06D6A0; font-weight:700;">/projects/{id_or_code}/docs</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep5']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/projects/{id_or_code}/docs</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep6']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#f59e0b; font-weight:700; font-family:monospace;">PUT</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/projects/{id_or_code}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep7']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:#06D6A0; font-weight:700;">/projects/{id_or_code}/webhook/test</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep8']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#ff4757; font-weight:700; font-family:monospace;">DELETE</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/projects/{id_or_code}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec2_ep9']) ?></span>
                </div>
            </div>
        </div>

        <!-- 3. GÖREVLER & KANBAN -->
        <div class="cu-endpoint-card" style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); overflow:hidden;">
            <div style="padding:14px 18px; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between;">
                <span style="font-weight:700; color:var(--text-white); font-size:14px;"><?= htmlspecialchars($t['sec3_title']) ?></span>
                <span style="font-size:11px; color:var(--text-muted);">/api/v1/tasks</span>
            </div>
            <div style="padding:16px 18px; display:flex; flex-direction:column; gap:12px;">
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_ep1']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_ep2']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks/{id_or_code}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_ep3']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#f59e0b; font-weight:700; font-family:monospace;">PUT</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks/{id_or_code}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_ep4']) ?></span>
                </div>
                
                <div style="border-top:1px dashed var(--border-subtle); margin:4px 0;"></div>
                <div style="font-size:11px; font-weight:700; color:var(--brand-primary); text-transform:uppercase;"><?= htmlspecialchars($t['sec3_workflow_label']) ?></div>

                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:#06D6A0;">/tasks/{code}/workflow/accept</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_wf1']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks/{code}/workflow/status</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_wf2']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:#f59e0b;">/tasks/{code}/workflow/request-modification</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_wf3']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:#00b894;">/tasks/{code}/workflow/submit</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_wf4']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:#7B68EE;">/tasks/{code}/workflow/review</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec3_wf5']) ?></span>
                </div>
            </div>
        </div>

        <!-- 4. CHECKLISTS, COMMENTS, ASSIGNEES -->
        <div class="cu-endpoint-card" style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); overflow:hidden;">
            <div style="padding:14px 18px; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between;">
                <span style="font-weight:700; color:var(--text-white); font-size:14px;"><?= htmlspecialchars($t['sec4_title']) ?></span>
                <span style="font-size:11px; color:var(--text-muted);">/api/v1/tasks/{code}/[checklists|comments|assignees]</span>
            </div>
            <div style="padding:16px 18px; display:flex; flex-direction:column; gap:12px;">
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks/{code}/checklists</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec4_ep1']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#f59e0b; font-weight:700; font-family:monospace;">PUT</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks/{code}/checklists/{id}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec4_ep2']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks/{code}/comments</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec4_ep3']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks/{code}/assignees</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec4_ep4']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#ff4757; font-weight:700; font-family:monospace;">DELETE</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/tasks/{code}/assignees/{user_id}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec4_ep5']) ?></span>
                </div>
            </div>
        </div>

        <!-- 5. DOKÜMANLAR, EKİP & RAPORLAR -->
        <div class="cu-endpoint-card" style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); overflow:hidden;">
            <div style="padding:14px 18px; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between;">
                <span style="font-weight:700; color:var(--text-white); font-size:14px;"><?= htmlspecialchars($t['sec5_title']) ?></span>
                <span style="font-size:11px; color:var(--text-muted);">/api/v1/[docs|users|reports]</span>
            </div>
            <div style="padding:16px 18px; display:flex; flex-direction:column; gap:12px;">
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/docs</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec5_ep1']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/docs/{id}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec5_ep2']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/docs</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec5_ep3']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#f59e0b; font-weight:700; font-family:monospace;">PUT</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/docs/{id}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec5_ep4']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#ff4757; font-weight:700; font-family:monospace;">DELETE</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/docs/{id}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec5_ep5']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/users</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec5_ep6']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/reports</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec5_ep7']) ?></span>
                </div>
            </div>
        </div>

        <!-- 6. WEBHOOKS & AI AGENT KÖPRÜSÜ -->
        <div class="cu-endpoint-card" style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); overflow:hidden;">
            <div style="padding:14px 18px; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between;">
                <span style="font-weight:700; color:var(--text-white); font-size:14px;"><?= htmlspecialchars($t['sec6_title']) ?></span>
                <span style="font-size:11px; color:var(--text-muted);">/api/v1/[projects/{code}/webhook/test | webhooks/logs]</span>
            </div>
            <div style="padding:16px 18px; display:flex; flex-direction:column; gap:12px;">
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:#00b894;">/projects/{code}/webhook/test</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec6_ep1']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#00b894; font-weight:700; font-family:monospace;">GET</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/webhooks/logs</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec6_ep2']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#f59e0b; font-weight:700; font-family:monospace;">PUT</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/projects/{code}</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec6_ep3']) ?></span>
                </div>
                <div class="api-row" style="display:grid; grid-template-columns:120px 300px 1fr; gap:12px; align-items:center; font-size:13px;">
                    <span style="color:#3b82f6; font-weight:700; font-family:monospace;">POST</span>
                    <span style="font-family:monospace; color:var(--text-primary);">/webhooks/listener</span>
                    <span style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars($t['sec6_ep4']) ?></span>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
function copyToken() {
    const text = document.getElementById('tokenText').innerText;
    const msg = <?= json_encode($t['token_copied']) ?>;
    navigator.clipboard.writeText(text).then(() => {
        if (typeof showToast === 'function') {
            showToast(msg, 'success');
        } else {
            alert(msg);
        }
    });
}
</script>

<?php include __DIR__ . '/../../Components/footer.php'; ?>
