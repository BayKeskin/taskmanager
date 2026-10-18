<?php
namespace Modules\GithubDevops\Services;

use Helper;

/**
 * GitService - Projelerin yerel ve uzak Git/GitHub operasyonlarını yönetir.
 * Atomic commit, origin push, diff analizi ve rollback göçlerini yürütür.
 */
class GitService
{
    /**
     * Aktif branch adını tespit eder (main, master vs.)
     */
    public static function detectActiveBranch(string $workspacePath): string
    {
        $escaped = escapeshellarg($workspacePath);
        $branch = trim((string)@shell_exec("cd /d {$escaped} && git branch --show-current 2>nul"));
        if (!empty($branch)) {
            return $branch;
        }

        $headRef = trim((string)@shell_exec("cd /d {$escaped} && git symbolic-ref --short HEAD 2>nul"));
        if (!empty($headRef)) {
            return $headRef;
        }

        return 'main';
    }

    /**
     * Görev tesliminde otomatik atomic commit ve push yapar.
     */
    public static function autoCommitAndPushTask(object $project, object $task): array
    {
        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws)) {
            return ['success' => false, 'error' => 'Workspace dizini yok.'];
        }

        $escapedWs = escapeshellarg($ws);

        if (!is_dir($ws . '/.git')) {
            return ['success' => false, 'error' => 'Git deposu (.git) bulunamadı.'];
        }

        $commitMsg = "feat({$task->task_code}): " . str_replace(['"', '`', '$'], '', $task->title);

        // Değişiklikleri ekle ve commit at
        $commitCmd = "cd /d {$escapedWs} && git add -A && git commit -m " . escapeshellarg($commitMsg) . " 2>&1";
        @shell_exec($commitCmd);

        // Son commit hash'ini al
        $hash = trim((string)@shell_exec("cd /d {$escapedWs} && git rev-parse HEAD 2>nul"));
        if (empty($hash) || strlen($hash) < 7) {
            return ['success' => false, 'error' => 'Commit oluşturulamadı veya dosya değişikliği yok.'];
        }

        $branch = self::detectActiveBranch($ws);

        // Projede github_repo tanımlı mı?
        $repoSlug = trim((string)($project->github_repo ?? ''));
        if (empty($repoSlug)) {
            $existingRemote = trim((string)@shell_exec("cd /d {$escapedWs} && git remote get-url origin 2>nul"));
            if (!empty($existingRemote) && preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#i', $existingRemote, $m)) {
                $repoSlug = trim($m[1]) . '/' . trim($m[2]);
            }
        }

        $commitUrl = '';
        $creds = Helper::getGithubCredentials();

        if (!empty($repoSlug)) {
            $commitUrl = "https://github.com/{$repoSlug}/commit/{$hash}";

            if (!empty($creds['token']) && !empty($creds['username'])) {
                $authHeader = base64_encode("{$creds['username']}:{$creds['token']}");
                $pushCmd = "cd /d {$escapedWs} && git -c http.extraheader=\"AUTHORIZATION: basic {$authHeader}\" push origin {$branch} 2>&1";
                @shell_exec($pushCmd);
            } else {
                @shell_exec("cd /d {$escapedWs} && git push origin {$branch} 2>&1");
            }
        }

        return [
            'success'     => true,
            'commit_hash' => $hash,
            'commit_url'  => $commitUrl,
            'branch'      => $branch
        ];
    }

    /**
     * Git diff analizi yapar (İki commit arasındaki değişen dosyaları döndürür)
     */
    public static function getDiffFiles(string $workspacePath, ?string $fromCommit = null, string $toCommit = 'HEAD'): array
    {
        $escapedWs = escapeshellarg($workspacePath);
        if (empty($fromCommit)) {
            // İlk dağıtım: git ls-files
            $output = @shell_exec("cd /d {$escapedWs} && git ls-files 2>nul");
        } else {
            // Delta diff: git diff --name-status from..to
            $output = @shell_exec("cd /d {$escapedWs} && git diff --name-status " . escapeshellarg($fromCommit) . " " . escapeshellarg($toCommit) . " 2>nul");
        }

        if (empty($output)) {
            return [];
        }

        $lines = explode("\n", trim($output));
        $files = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (empty($fromCommit)) {
                $files[] = ['status' => 'A', 'file' => $line];
            } else {
                $parts = preg_split('/\s+/', $line, 2);
                if (count($parts) === 2) {
                    $files[] = ['status' => $parts[0], 'file' => $parts[1]];
                }
            }
        }

        return $files;
    }
}
