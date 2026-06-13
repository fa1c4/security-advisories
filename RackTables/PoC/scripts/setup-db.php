<?php
// Intentionally unused in the current Docker entrypoint.
// The lab now initializes RackTables through its normal web installer module
// to keep the include/bootstrap order identical to RackTables runtime.
echo "setup-db.php is unused; docker-entrypoint.sh uses the web installer.\n";
