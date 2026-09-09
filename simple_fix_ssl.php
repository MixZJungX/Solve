<?php
$c = file_get_contents("api.php");

$c = str_replace(
    "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/submit\",\n                    CURLOPT_RETURNTRANSFER => true,",
    "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/submit\",\n                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,",
    $c
);

$c = str_replace(
    "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/status/{\$id}\",\n                    CURLOPT_RETURNTRANSFER => true,",
    "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/status/{\$id}\",\n                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,",
    $c
);

// We also need to add the else block for jsonResponse when httpCode != 200 in status!
// Let's check if it exists
if (!str_contains($c, "jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'Failed to connect")) {
    $c = str_replace(
        "                    ]);\n                }\n            }\n\n\n            \$res = callHighspec(\"/external/job/{\$id}\");",
        "                    ]);\n                } else {\n                    jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่อ ZeroPoint'], \$httpCode ?: 500);\n                }\n            }\n\n\n            \$res = callHighspec(\"/external/job/{\$id}\");",
        $c
    );
    
    // For admin status get_job_status
    $c = str_replace(
        "                    ]);\n                }\n            }\n\n            \$res = callHighspec(\"/external/job/{\$id}\");",
        "                    ]);\n                } else {\n                    jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่อ ZeroPoint'], \$httpCode ?: 500);\n                }\n            }\n\n            \$res = callHighspec(\"/external/job/{\$id}\");",
        $c
    );
}


file_put_contents("api.php", $c);

