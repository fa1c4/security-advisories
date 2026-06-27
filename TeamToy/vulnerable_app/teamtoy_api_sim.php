<?php
/**
 * Minimal local simulation of the TeamToy vulnerable data flow:
 *
 *   index.php?c=api&a=user_update_settings
 *     -> apiController::__construct()
 *     -> check_token()
 *     -> apiController::user_update_settings()
 *     -> v('value') / $_REQUEST['value']
 *     -> unserialize(v('value'))
 *
 * This file intentionally keeps only the security-relevant logic required to
 * reproduce CAND-13e7a27a275b locally. It does not contact a database or any
 * external service.
 */

function teamtoy_poc_marker_path(): string
{
    return $GLOBALS['TEAMTOY_POC_MARKER'] ?? (sys_get_temp_dir() . '/teamtoy_poc_wakeup_marker.log');
}

class TeamToyPoCProbe
{
    public function __wakeup(): void
    {
        file_put_contents(
            teamtoy_poc_marker_path(),
            json_encode([
                'event' => '__wakeup invoked',
                'class' => __CLASS__,
                'time' => gmdate('c'),
            ], JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
}

function tt_v(string $key)
{
    return $_REQUEST[$key] ?? false;
}

function tt_t($value): string
{
    return trim((string) $value);
}

function tt_z($value): string
{
    return strip_tags((string) $value);
}

function tt_not_empty($value): bool
{
    return is_string($value) ? strlen($value) > 0 : !empty($value);
}

final class TeamToyApiSim
{
    private static array $sessions = [];
    private static array $settings = [];
    private static ?int $currentUid = null;

    public static function resetState(): void
    {
        self::$sessions = [];
        self::$settings = [];
        self::$currentUid = null;
        $_REQUEST = [];
    }

    public static function seedToken(string $token, int $uid): void
    {
        self::$sessions[$token] = [
            'token' => $token,
            'uid' => $uid,
            'level' => 1,
        ];
        self::$settings[$uid] = [
            'language' => 'zh_CN',
        ];
    }

    public static function getSettings(int $uid): array
    {
        return self::$settings[$uid] ?? [];
    }

    public static function handle(array $request, bool $patched = false): array
    {
        $_REQUEST = $request;
        self::$currentUid = null;

        $auth = self::checkToken();
        if ($auth !== true) {
            return $auth;
        }

        return $patched
            ? self::user_update_settings_patched()
            : self::user_update_settings_vulnerable();
    }

    private static function checkToken()
    {
        $token = tt_z(tt_t(tt_v('token')));

        if (strlen($token) < 2) {
            return self::sendError(401, 'NO TOKEN');
        }

        if (!isset(self::$sessions[$token]) || self::$sessions[$token]['token'] !== $token) {
            return self::sendError(401, 'BAD TOKEN');
        }

        self::$currentUid = self::$sessions[$token]['uid'];
        return true;
    }

    /**
     * Vulnerable TeamToy logic, adapted from controller/api.class.php:
     *
     *   if (!$value = unserialize(v('value'))) { ... }
     *   else { if (!is_array($value)) return error; }
     *
     * The is_array() check is too late: object instantiation and __wakeup()
     * have already happened inside unserialize().
     */
    private static function user_update_settings_vulnerable(): array
    {
        $key = tt_z(tt_t(tt_v('key')));

        if (!tt_not_empty($key)) {
            return self::sendError(400, 'INPUT_CHECK_BAD_ARGS: KEY');
        }

        $rawValue = tt_v('value');
        $value = @unserialize($rawValue);

        if (!$value) {
            $value = tt_z(tt_t($rawValue));

            if (!tt_not_empty($value)) {
                return self::sendError(400, 'INPUT_CHECK_BAD_ARGS: VALUE');
            }
        } else {
            if (!is_array($value)) {
                return self::sendError(400, 'INPUT_CHECK_BAD_ARGS: VALUE');
            }
        }

        $uid = self::$currentUid;
        self::$settings[$uid][$key] = $value;

        return self::sendResult([
            'updated' => true,
            'settings' => self::$settings[$uid],
        ]);
    }

    /**
     * One minimal patched control: keep backward compatibility with serialized
     * arrays, but prevent class instantiation with allowed_classes=false.
     */
    private static function user_update_settings_patched(): array
    {
        $key = tt_z(tt_t(tt_v('key')));

        if (!tt_not_empty($key)) {
            return self::sendError(400, 'INPUT_CHECK_BAD_ARGS: KEY');
        }

        $rawValue = tt_v('value');
        $value = @unserialize($rawValue, ['allowed_classes' => false]);

        if ($value !== false || $rawValue === 'b:0;') {
            if (!is_array($value)) {
                return self::sendError(400, 'INPUT_CHECK_BAD_ARGS: VALUE');
            }
        } else {
            $value = tt_z(tt_t($rawValue));

            if (!tt_not_empty($value)) {
                return self::sendError(400, 'INPUT_CHECK_BAD_ARGS: VALUE');
            }
        }

        $uid = self::$currentUid;
        self::$settings[$uid][$key] = $value;

        return self::sendResult([
            'updated' => true,
            'settings' => self::$settings[$uid],
        ]);
    }

    private static function sendError(int $status, string $message): array
    {
        return [
            'http_status' => $status,
            'body' => [
                'ok' => false,
                'err_msg' => $message,
            ],
        ];
    }

    private static function sendResult(array $data): array
    {
        return [
            'http_status' => 200,
            'body' => [
                'ok' => true,
                'data' => $data,
            ],
        ];
    }
}
