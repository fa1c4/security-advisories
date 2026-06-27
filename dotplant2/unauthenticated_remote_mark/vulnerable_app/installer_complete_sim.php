<?php
/**
 * Local-only simulation of dotplant2 installer complete flow.
 *
 * Source pattern reproduced:
 * - application/config/installer.php disables Yii CSRF validation for installer requests.
 * - application/modules/installer/controllers/InstallerController.php::actionComplete()
 *   writes @app/installed.mark without checking request method, CSRF token, or installer step/session state.
 */

final class InstallerState
{
    /** @var array<string,string> */
    public array $files = ['@app/installed.mark' => '0'];
    /** @var array<int,array<string,mixed>> */
    public array $auditLog = [];
    public int $csrfChecks = 0;
    public int $csrfFailures = 0;

    public function reset(): void
    {
        $this->files = ['@app/installed.mark' => '0'];
        $this->auditLog = [];
        $this->csrfChecks = 0;
        $this->csrfFailures = 0;
    }
}

final class RequestSim
{
    public function __construct(
        public string $method,
        public string $route,
        public ?string $csrfToken = null,
        public bool $installerFinalStepValidated = false
    ) {
        $this->method = strtoupper($method);
    }
}

final class ResponseSim
{
    public function __construct(public int $status, public mixed $body) {}
    /** @return array<string,mixed> */
    public function toArray(): array { return ['http_status' => $this->status, 'body' => $this->body]; }
}

final class VulnerableInstallerApp
{
    public function __construct(private InstallerState $state) {}

    public function dispatch(RequestSim $request): ResponseSim
    {
        // InstallerFilter only checks whether installation is already completed.
        if (($this->state->files['@app/installed.mark'] ?? '0') === '1') {
            return new ResponseSim(403, ['ok' => false, 'error' => 'DotPlant2 is already installed']);
        }

        if ($request->route !== '/installer.php?r=installer/installer/complete') {
            return new ResponseSim(404, ['ok' => false, 'error' => 'Not found']);
        }

        // Vulnerable actionComplete(): no method guard, no CSRF, no final-step/session guard.
        $this->state->files['@app/installed.mark'] = '1';
        $this->state->auditLog[] = [
            'action' => 'write_file',
            'path' => '@app/installed.mark',
            'value' => '1',
            'source_method' => $request->method,
            'auth' => 'none',
        ];

        return new ResponseSim(200, ['ok' => true, 'view' => 'complete']);
    }
}

final class PatchedInstallerApp
{
    public function __construct(private InstallerState $state) {}

    public function dispatch(RequestSim $request): ResponseSim
    {
        if (($this->state->files['@app/installed.mark'] ?? '0') === '1') {
            return new ResponseSim(403, ['ok' => false, 'error' => 'DotPlant2 is already installed']);
        }

        if ($request->route !== '/installer.php?r=installer/installer/complete') {
            return new ResponseSim(404, ['ok' => false, 'error' => 'Not found']);
        }

        if ($request->method !== 'POST') {
            return new ResponseSim(405, ['ok' => false, 'error' => 'Installer complete requires POST']);
        }

        if (!$this->validateCsrf($request)) {
            return new ResponseSim(403, ['ok' => false, 'error' => 'Invalid or missing installer CSRF token']);
        }

        if (!$request->installerFinalStepValidated) {
            return new ResponseSim(403, ['ok' => false, 'error' => 'Installer final step/session was not validated']);
        }

        $this->state->files['@app/installed.mark'] = '1';
        $this->state->auditLog[] = [
            'action' => 'write_file',
            'path' => '@app/installed.mark',
            'value' => '1',
            'source_method' => $request->method,
            'auth' => 'installer-final-step + csrf',
        ];

        return new ResponseSim(200, ['ok' => true, 'view' => 'complete']);
    }

    private function validateCsrf(RequestSim $request): bool
    {
        $this->state->csrfChecks++;
        if ($request->csrfToken !== 'valid-yii-csrf-token') {
            $this->state->csrfFailures++;
            return false;
        }
        return true;
    }
}

/** @return array<string,mixed> */
function installer_snapshot(InstallerState $state): array
{
    return [
        'installed_mark' => $state->files['@app/installed.mark'] ?? null,
        'csrf_checks' => $state->csrfChecks,
        'csrf_failures' => $state->csrfFailures,
        'audit_log' => $state->auditLog,
    ];
}
