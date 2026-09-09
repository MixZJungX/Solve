<?php
$c = file_get_contents("api.php");
$c = preg_replace("/\\s+\]\);\n\\s+\}\n\\s+\}\n\n\n\\s+\\\$res = callHighspec\(\"\/external\/job\/{\\\$id}\"\);/s", "\n                    ]);\n                } else {\n                    jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'Failed to fetch status from ZeroSolver API'], \$httpCode ?: 500);\n                }\n            }\n\n\n            \$res = callHighspec(\"/external/job/{\$id}\");", $c, 1);

$c = preg_replace("/\\s+\]\);\n\\s+\}\n\\s+\}\n\n\\s+\\\$res = callHighspec\(\"\/external\/job\/{\\\$id}\"\);/s", "\n                    ]);\n                } else {\n                    jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'Failed to fetch status from ZeroSolver API'], \$httpCode ?: 500);\n                }\n            }\n\n            \$res = callHighspec(\"/external/job/{\$id}\");", $c, 1);

file_put_contents("api.php", $c);

