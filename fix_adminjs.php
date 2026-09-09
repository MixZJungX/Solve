<?php
$c = file_get_contents("public/admin.js");

// 1. load masking
$oldLoad = "      const inwMaskEl = document.getElementById('adminMaskedInwKey');";
$newLoad = "      const zsMaskEl = document.getElementById('adminMaskedZsKey');\n      if (zsMaskEl) {\n        if (cfg.has_zs_key) zsMaskEl.textContent = 'มี ZeroSolver Key ในระบบแล้ว (ถูกซ่อนไว้)';\n        else zsMaskEl.textContent = 'ยังไม่ได้ใส่ ZeroSolver API Key';\n      }\n      const inwMaskEl = document.getElementById('adminMaskedInwKey');";
$c = str_replace($oldLoad, $newLoad, $c);

// 2. save variables
$oldSaveVars = "  const zpKey = document.getElementById('settingZpKey')?.value.trim() || '';";
$newSaveVars = "  const zpKey = document.getElementById('settingZpKey')?.value.trim() || '';\n  const zsKey = document.getElementById('settingZsKey')?.value.trim() || '';";
$c = str_replace($oldSaveVars, $newSaveVars, $c);

// 3. payload
$oldPayload = "  if (inwKey) payload.inw_api_key = inwKey;";
$newPayload = "  if (inwKey) payload.inw_api_key = inwKey;\n  if (zsKey) payload.zerosolver_api_key = zsKey;";
$c = str_replace($oldPayload, $newPayload, $c);

file_put_contents("public/admin.js", $c);

