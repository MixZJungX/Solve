import re

with open('api.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

new_lines = []
for idx, line in enumerate(lines):
    if "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/submit\"" in line:
        new_lines.append(line)
        new_lines.append("                    CURLOPT_SSL_VERIFYPEER => false,\n")
        new_lines.append("                    CURLOPT_SSL_VERIFYHOST => 0,\n")
        continue
    
    if "CURLOPT_URL => \"https://zerosolver.to/api/zerosolver-api/status/{$id}\"" in line:
        new_lines.append(line)
        new_lines.append("                    CURLOPT_SSL_VERIFYPEER => false,\n")
        new_lines.append("                    CURLOPT_SSL_VERIFYHOST => 0,\n")
        continue
        
    if "$zpKey = DB::getSetting('zp_api_key', '');" in line and "if (empty($zpKey)) {" in lines[idx+1] and "ZeroPoint API Key" in lines[idx+2]:
        line = line.replace("zp_api_key", "zerosolver_api_key")
        lines[idx+2] = lines[idx+2].replace("ZeroPoint API Key", "ZeroSolver API Key")
    
    if "$res = callHighspec(\"/external/job/{$id}\");" in line:
        if "}" in lines[idx-1].strip() and "}" in lines[idx-2].strip():
            new_lines.append("                } else {\n")
            new_lines.append("                    jsonResponse(['success' => false, 'error' => $zpData['error'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่อ ZeroPoint'], $httpCode ?: 500);\n")
            new_lines.append("                }\n")
    
    new_lines.append(line)

with open('api.php', 'w', encoding='utf-8') as f:
    f.writelines(new_lines)

print('Success')
