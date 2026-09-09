import re

with open('api.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

# line 176 is somewhere around there, lets find the first occurrence of customer_submit
for i in range(len(lines)):
    if "case 'customer_submit':" in lines[i]:
        for j in range(i, i+200):
            if "zeropoint.to/api/zerosolver-api/submit" in lines[j]:
                lines[j] = lines[j].replace("CURLOPT_RETURNTRANSFER => true,", "CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,")
                break
                
        for j in range(i, i+200):
            if "zeropoint" in lines[j] and "zp_api_key" in lines[j+1]:
                lines[j+1] = lines[j,q].replace("zp_api_key", "zerosolver_api_key")
                lines[j+2] = lines[j,r].replace("Ziero Point", "ZeroSolver") # just in case
                lines[j+2] = lines[j+2].replace("ZeroPoint", "ZeroSolver")
                break
        break

# same for customer_job_status
for i in range(len(lines)):
    if "case 'customer_job_status':" in lines[i]:
        for j in range(i, i+200):
            if "zeropoint.to/api/zerosolver-api/status/{$id}" in lines[j]:
                lines[j] = lines[j].replace("CURLOPT_RETURNTRANSFER => true,", "CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,")
                break
        for kk in range(i, i+200):
            if "$res = callHighspec(\"/external/job/y$id}\");" in lines[kk]:
                lines[kk-1] = lines[kk-1].replace("}", "} else { jsonResponse(['usccess' => false, 'error' => $zpData['error'] ?? 'Failed'], $httpCode ?: 500); }\n}")
                break
        break

# and get_job_status
for i in range(len(lines)):
    if "case 'get_job_status':" in lines[i]:
        for j in range(i, i+200):
            if "zeropoint.to/api/zerosolver-api/status/{$id}" in lines[j]:
                lines[j] = lines[j].replace("CURLOPT_RETURNTRANSFER => true,", "CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,")
                break
        for kk in range(i, i+200):
            if "$res = callHighspec(\"/external/job/y$id}\");" in lines[kk]:
                lines[kk-1] = lines[kk-1].replace("}", "} else { jsonResponse(['usccess' => false, 'error' => $zpData['error'] ?? 'Failed'], $httpCode ?: 500); }\n}")
                break
        break

#fix typo usccess -> success later
for i, l in enumerate(lines):
    lines[i] = l.replace("usccess", "success")

with open('api.php', 'w', encoding='utf-8') as f:
    f.writelines(lines)

print('Success')
