<?php
/**
 * Deutsche Sprachdatei - Dispatcher Modul
 */
return [
    'module_name'          => 'Autonome KI-Brücke',
    'module_desc'          => 'Antigravity Language Server und Aufgabenverteilungs-Engine.',
    'nav_menu_title'       => 'Dispatcher',
    'dispatcher_running'   => 'Dispatcher läuft (Port 4000)',
    'dispatcher_stopped'   => 'Dispatcher gestoppt',
    'sync_workspaces'      => 'Arbeitsbereiche synchronisieren',
    'workspaces_synced'    => 'Arbeitsbereiche erfolgreich synchronisiert.',
    'sync_error'           => 'Synchronisationsfehler.',
    'queue_dispatched'     => 'Aufgabe an die autonome KI-Warteschlange gesendet.',
    'active_lock_cleared'  => 'Aktive Aufgabensperre aufgehoben.',

    // Dashboard UI
    'page_title'           => 'Dispatcher — KI-Kommandozentrale',
    'badge_live'           => 'Live-Überwachung',
    'status_bridge_title'  => 'Bridge-Port-Status',
    'status_bridge_desc'   => '127.0.0.1:4000 Socket-Verbindung und HTTP-Brücke',
    'status_queue_title'   => 'KI-Aufgaben in Warteschlange',
    'status_queue_desc'    => 'Autonome Aufgaben im Status zugewiesen (assigned)',
    'status_active_title'  => 'Aktive Bearbeitungen',
    'status_active_desc'   => 'Aufgaben, an denen autonome Agenten derzeit arbeiten',
    'status_total_title'   => 'Konfigurierte Projekte',
    'status_total_desc'    => 'Mit lokalen Arbeitsbereichen verknüpfte Projekte',

    'tab_queue'            => 'Warteschlange (Zugewiesene Aufgaben)',
    'tab_active'           => 'Aktive Aufgaben',
    'tab_logs'             => 'Live-Dispatcher-Protokolle',

    'th_task_code'         => 'Aufgabencode',
    'th_project'           => 'Projekt',
    'th_title'             => 'Aufgabentitel',
    'th_priority'          => 'Priorität',
    'th_status'            => 'Status',
    'th_actions'           => 'Aktionen',
    'th_started_at'        => 'Gestartet am',

    'btn_dispatch_now'     => 'Jetzt auslösen',
    'btn_cancel_task'      => 'Aus Warteschlange entfernen',
    'btn_abort_task'       => 'Abbrechen (Zurücksetzen)',
    'btn_view_task'        => 'Aufgabe ansehen',
    'btn_refresh'          => 'Aktualisieren',
    'btn_clear_logs'       => 'Löschen',
    'btn_sync_workspaces'  => 'Arbeitsbereiche synchronisieren',

    'empty_queue'          => 'Keine autonomen KI-Aufgaben in der Warteschlange.',
    'empty_active'         => 'Derzeit werden keine aktiven KI-Aufgaben ausgeführt.',
    'empty_logs'           => 'Noch keine Protokolleinträge aufgezeichnet.',

    'confirm_dispatch'     => 'Möchten Sie diese Aufgabe sofort an den autonomen Agenten senden?',
    'confirm_abort'        => 'Möchten Sie diese Aufgabe abbrechen und in die Aufgabenliste zurücksetzen?',

    'msg_dispatch_success' => 'Aufgabe erfolgreich an den KI-Agenten gesendet.',
    'msg_dispatch_failed'  => 'Aufgabe konnte nicht gesendet werden oder Brücke antwortete nicht.',
    'msg_abort_success'    => 'Aufgabe erfolgreich abgebrochen und zurückgesetzt.',
    'msg_abort_failed'     => 'Beim Abbrechen der Aufgabe ist ein Fehler aufgetreten.',
    'msg_unauthorized'     => 'Sie haben keine Berechtigung für diese Aktion.',
    'msg_not_found'        => 'Der angeforderte Datensatz wurde nicht gefunden.'
];
