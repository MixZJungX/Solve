// public/app.js — Frontend Logic for HIGHSPEC Direct API Runner

let cachedAccounts = [];
let activeJobId = null;
let pollTimer = null;

// ===================== TOAST NOTIFICATION =====================
function showToast(message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  
  const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');
  toast.innerHTML = `<span>${icon}</span><div>${message}</div>`;
  
  container.appendChild(toast);
  
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(40px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// ===================== UTILS =====================
async function apiCall(endpoint, method = 'GET', body = null) {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' }
  };
  if (body) {
    options.body = JSON.stringify(body);
  }
  const res = await fetch(endpoint, options);
  const data = await res.json();
  return { status: res.status, ok: res.ok, data };
}

function switchTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabId);
  });
  document.querySelectorAll('.tab-pane').forEach(pane => {
    pane.classList.toggle('active', pane.id === tabId);
  });

  if (tabId === 'tab-accounts') {
    loadAccounts();
  } else if (tabId === 'tab-history') {
    loadJobHistory();
  } else if (tabId === 'tab-settings') {
    loadSettings();
  }
}

function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.add('active');
}

function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.remove('active');
}

// ===================== BALANCE & STATUS =====================
async function refreshBalance() {
  const el = document.getElementById('navBalance');
  const dot = document.getElementById('apiDot');
  if (el) el.innerHTML = '<span style="color:var(--text-muted);font-weight:normal;font-size:12px;">กำลังเช็ค...</span>';

  try {
    const { ok, data } = await apiCall('/api.php?action=get_balance');
    if (ok && data.success) {
      if (el) el.innerHTML = `<span>${data.data.thb} ฿</span> <span style="font-size:12px;color:var(--text-muted);font-weight:normal;">(${Number(data.data.points).toLocaleString()} สตางค์)</span>`;
      if (dot) {
        dot.className = 'pulse-dot online';
        dot.title = 'เชื่อมต่อ API ปกติ';
      }
    } else {
      if (el) el.innerHTML = '<span style="color:var(--text-dim);font-size:12px;">ยังไม่เชื่อมต่อ</span>';
      if (dot) {
        dot.className = 'pulse-dot';
        dot.title = data.error || 'ตรวจสอบ API Key';
      }
    }
  } catch (e) {
    if (el) el.innerHTML = '<span style="color:var(--red);font-size:12px;">เชื่อมต่อไม่สำเร็จ</span>';
    if (dot) dot.className = 'pulse-dot';
  }
}

// ===================== SETTINGS =====================
async function loadSettings() {
  try {
    const { ok, data } = await apiCall('/api.php?action=get_settings');
    if (ok && data.success) {
      const cfg = data.data;
      const maskEl = document.getElementById('currentMaskedKeyDisplay');
      const alertEl = document.getElementById('missingKeyAlert');

      if (cfg.has_key) {
        if (maskEl) maskEl.innerHTML = `🔑 คีย์ปัจจุบัน: <b style="color:var(--orange);">${cfg.masked_key}</b>`;
        if (alertEl) alertEl.style.display = 'none';
      } else {
        if (maskEl) maskEl.textContent = 'ยังไม่มีการบันทึก API Key ในระบบ';
        if (alertEl) alertEl.style.display = 'flex';
      }

      const priorityCheck = document.getElementById('settingDefaultPriority');
      if (priorityCheck) priorityCheck.checked = !!cfg.default_priority;
      
      const checkPriority = document.getElementById('checkPriority');
      if (checkPriority && !checkPriority.dataset.touched) {
        checkPriority.checked = !!cfg.default_priority;
        document.getElementById('optPriorityCard')?.classList.toggle('selected', !!cfg.default_priority);
      }
    }
  } catch (e) {
    console.error(e);
  }
}

document.getElementById('formSettings')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const apiKey = document.getElementById('settingApiKey').value.trim();
  const defaultPriority = document.getElementById('settingDefaultPriority').checked;

  const payload = { default_priority: defaultPriority };
  if (apiKey) payload.api_key = apiKey;

  const { ok, data } = await apiCall('/api.php?action=save_settings', 'POST', payload);
  if (ok && data.success) {
    showToast('บันทึกการตั้งค่าเรียบร้อยแล้ว', 'success');
    document.getElementById('settingApiKey').value = '';
    loadSettings();
    refreshBalance();
  } else {
    showToast(data.error || 'ไม่สามารถบันทึกได้', 'error');
  }
});

document.getElementById('btnTestApi')?.addEventListener('click', async () => {
  showToast('กำลังทดสอบการเชื่อมต่อ API...', 'info');
  await refreshBalance();
});

// ===================== ACCOUNTS =====================
async function loadAccounts(query = '') {
  const tbody = document.getElementById('accountsTableBody');
  const badge = document.getElementById('accountCountBadge');

  try {
    const { ok, data } = await apiCall(`/api.php?action=get_accounts&q=${encodeURIComponent(query)}`);
    if (ok && data.success) {
      cachedAccounts = data.data || [];
      if (badge) badge.textContent = cachedAccounts.length;

      renderQuickChips();

      if (cachedAccounts.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align:center;color:var(--text-muted);padding:36px;">
              ไม่พบบัญชีในระบบ กรุณากด "นำเข้าแบบ Combo" หรือ "เพิ่มทีละบัญชี"
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = cachedAccounts.map((acc, i) => `
        <tr>
          <td style="color:var(--text-dim);font-family:var(--font-mono);font-size:12px;">${i + 1}</td>
          <td>
            <div style="font-weight:600;display:flex;align-items:center;gap:8px;">
              <span>${acc.username}</span>
              <button class="btn btn-secondary btn-sm" style="padding:2px 6px;font-size:10px;" onclick="copyText('${acc.username}')" title="คัดลอกชื่อ">📋 คัดลอก</button>
            </div>
            ${acc.note ? `<div style="font-size:11px;color:var(--text-muted);">${acc.note}</div>` : ''}
          </td>
          <td>
            <span class="mono" style="color:var(--text-muted);font-size:12px;">${acc.password ? '••••••••' : '-'}</span>
          </td>
          <td>
            <span class="mono" style="font-size:11px;color:var(--text-muted);">${acc.cookie_preview || '-'}</span>
          </td>
          <td style="font-size:12px;color:var(--text-muted);">${acc.last_used_at || 'ยังไม่เคย'}</td>
          <td>
            ${renderStatusTag(acc.last_status)}
          </td>
          <td style="text-align:right;">
            <button class="btn btn-primary btn-sm" onclick="quickSelectAccount('${acc.username}')" title="นำไปส่งงาน">🚀 ส่งงาน</button>
            <button class="btn btn-danger btn-sm" onclick="deleteAccount(${acc.id}, '${acc.username}')" title="ลบ">🗑️</button>
          </td>
        </tr>
      `).join('');
    }
  } catch (e) {
    console.error(e);
  }
}

function renderStatusTag(status) {
  if (!status) return '<span style="color:var(--text-dim);">-</span>';
  let cls = 'status-PENDING';
  if (status === 'COMPLETED' || status === 'SUCCESS') cls = 'status-COMPLETED';
  if (status === 'PROCESSING') cls = 'status-PROCESSING';
  if (status === 'FAILED' || status === 'COOKIE_BROKEN') cls = 'status-FAILED';
  return `<span class="job-status-badge ${cls}" style="font-size:11px;padding:3px 8px;">${status}</span>`;
}

function renderQuickChips() {
  const container = document.getElementById('quickChips');
  if (!container) return;

  if (cachedAccounts.length === 0) {
    container.innerHTML = '<span style="font-size:12px;color:var(--text-dim);padding:4px;">ยังไม่มีบัญชีในฐานข้อมูล กรุณากดปุ่ม "นำเข้าแบบ Combo" ที่แท็บบัญชี</span>';
    return;
  }

  container.innerHTML = cachedAccounts.map(acc => `
    <span class="chip-btn" onclick="appendUsername('${acc.username}')">
      <span style="color:var(--orange);">+</span> ${acc.username}
    </span>
  `).join('');
}

function appendUsername(name) {
  const textarea = document.getElementById('inputUsernames');
  const current = textarea.value.trim();
  const list = current ? current.split(/[\r\n]+/).map(s => s.trim()) : [];
  if (!list.includes(name)) {
    list.push(name);
    textarea.value = list.join('\n');
    updateSelectedCount();
    showToast(`เพิ่ม "${name}" ลงในรายการแล้ว`, 'info');
  }
}

function quickSelectAccount(name) {
  switchTab('tab-submit');
  const textarea = document.getElementById('inputUsernames');
  textarea.value = name;
  updateSelectedCount();
  showToast(`เลือกบัญชี "${name}" พร้อมส่งงาน`, 'info');
}

function updateSelectedCount() {
  const textarea = document.getElementById('inputUsernames');
  const countEl = document.getElementById('countSelectedDisplay');
  const val = textarea ? textarea.value.trim() : '';
  const lines = val ? val.split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean) : [];
  const unique = [...new Set(lines)];
  if (countEl) countEl.textContent = unique.length;
}

document.getElementById('inputUsernames')?.addEventListener('input', updateSelectedCount);

document.getElementById('btnSelectAllAccounts')?.addEventListener('click', () => {
  const textarea = document.getElementById('inputUsernames');
  if (cachedAccounts.length > 0) {
    textarea.value = cachedAccounts.map(a => a.username).join('\n');
    updateSelectedCount();
    showToast(`เลือกครบทั้งหมด ${cachedAccounts.length} บัญชีแล้ว`, 'success');
  }
});

document.getElementById('btnClearUsernames')?.addEventListener('click', () => {
  const textarea = document.getElementById('inputUsernames');
  textarea.value = '';
  updateSelectedCount();
});

async function deleteAccount(id, username) {
  if (!confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบบัญชี "${username}"?`)) return;
  const { ok, data } = await apiCall(`/api.php?action=delete_account&id=${id}`, 'DELETE');
  if (ok && data.success) {
    showToast(`ลบบัญชี "${username}" สำเร็จ`, 'info');
    loadAccounts();
  } else {
    showToast('ลบไม่สำเร็จ: ' + (data.error || ''), 'error');
  }
}

function copyText(text) {
  navigator.clipboard.writeText(text);
  showToast(`คัดลอก "${text}" เรียบร้อย`, 'success');
}

// Bulk Import
document.getElementById('btnOpenBulkModal')?.addEventListener('click', () => {
  openModal('modalBulkImport');
});

document.getElementById('btnSubmitBulkImport')?.addEventListener('click', async () => {
  const text = document.getElementById('bulkImportText').value.trim();
  if (!text) return showToast('กรุณาวางข้อมูลบัญชีก่อนกดบันทึก', 'error');

  const btn = document.getElementById('btnSubmitBulkImport');
  btn.disabled = true;
  btn.textContent = 'กำลังนำเข้า...';

  const { ok, data } = await apiCall('/api.php?action=import_accounts', 'POST', { text });
  btn.disabled = false;
  btn.textContent = '💾 บันทึกนำเข้า';

  if (ok && data.success) {
    showToast(data.message, 'success');
    document.getElementById('bulkImportText').value = '';
    closeModal('modalBulkImport');
    loadAccounts();
  } else {
    showToast('เกิดข้อผิดพลาด: ' + (data.error || ''), 'error');
  }
});

// Single Add Modal
document.getElementById('btnOpenAddModal')?.addEventListener('click', () => {
  openModal('modalAddAccount');
});

document.getElementById('formAddAccount')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const username = document.getElementById('addAccUsername').value.trim();
  const password = document.getElementById('addAccPassword').value.trim();
  const cookie = document.getElementById('addAccCookie').value.trim();
  const note = document.getElementById('addAccNote').value.trim();

  const { ok, data } = await apiCall('/api.php?action=add_account', 'POST', {
    username, password, cookie, note
  });

  if (ok && data.success) {
    showToast('เพิ่มบัญชีใหม่เรียบร้อยแล้ว', 'success');
    document.getElementById('formAddAccount').reset();
    closeModal('modalAddAccount');
    loadAccounts();
  } else {
    showToast('เกิดข้อผิดพลาด: ' + (data.error || ''), 'error');
  }
});

// Search accounts
let searchDebounce = null;
document.getElementById('searchAccountInput')?.addEventListener('input', (e) => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    loadAccounts(e.target.value.trim());
  }, 300);
});

document.getElementById('btnReloadAccounts')?.addEventListener('click', () => {
  loadAccounts(document.getElementById('searchAccountInput').value.trim());
});

// ===================== SUBMIT JOB =====================
document.getElementById('checkPriority')?.addEventListener('change', (e) => {
  e.target.dataset.touched = "true";
  document.getElementById('optPriorityCard')?.classList.toggle('selected', e.target.checked);
});

document.getElementById('formSubmitJob')?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const textarea = document.getElementById('inputUsernames');
  const val = textarea.value.trim();
  const usernames = val ? [...new Set(val.split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean))] : [];

  if (usernames.length === 0) {
    return showToast('กรุณากรอกหรือเลือกชื่อบัญชีอย่างน้อย 1 บัญชี', 'error');
  }

  const service = document.querySelector('input[name="serviceType"]')?.value || 'captcha';
  const priority = document.getElementById('checkPriority')?.checked || false;
  const note = document.getElementById('inputNote')?.value.trim() || '';

  const btn = document.getElementById('btnStartJob');
  btn.disabled = true;
  btn.innerHTML = '<span>⏳</span><span>กำลังส่งงานเข้าคิว...</span>';

  try {
    const { ok, status, data } = await apiCall('/api.php?action=submit_job', 'POST', {
      usernames, service, priority, note
    });

    btn.disabled = false;
    btn.innerHTML = '<span>🚀</span><span>เริ่มทำงานทันที (Submit Job)</span>';

    if (ok && data.success) {
      const job = data.data;
      showToast(`ส่งงานสำเร็จ! Job ID: ${job.job_id.substring(0, 8)}...`, 'success');
      startLiveTracking(job.job_id, job);
      refreshBalance();
    } else {
      if (status === 409) {
        showToast('⚠️ บัญชีทั้งหมดกำลังทำงานอยู่ในรอบก่อนหน้า กรุณารอให้รอบก่อนเสร็จสิ้น', 'error');
      } else if (status === 404) {
        showToast('❌ ไม่พบบัญชีในฐานข้อมูล: ' + data.error, 'error');
      } else {
        showToast('❌ ' + (data.error || 'ไม่สามารถส่งงานได้'), 'error');
      }
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<span>🚀</span><span>เริ่มทำงานทันที (Submit Job)</span>';
    showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + err.message, 'error');
  }
});

// ===================== LIVE JOB TRACKING =====================
function startLiveTracking(jobId, initialData = null) {
  activeJobId = jobId;
  clearInterval(pollTimer);

  const section = document.getElementById('liveJobSection');
  section.style.display = 'block';
  section.scrollIntoView({ behavior: 'smooth', block: 'start' });

  document.getElementById('liveJobId').textContent = jobId;

  if (initialData) {
    document.getElementById('liveJobServiceBadge').textContent = (initialData.service || 'captcha').toUpperCase();
    document.getElementById('statTotalAccs').textContent = initialData.total_accounts || initialData.usernames?.length || 0;
    document.getElementById('statQueuePos').textContent = initialData.queue_position ?? 0;
    document.getElementById('statChargedThb').textContent = `${initialData.total_thb || '0.00'} ฿`;
    setJobStatusBadge(initialData.status || 'PENDING');
  }

  // Poll immediately and then every 3.5 seconds
  pollJobStatus();
  pollTimer = setInterval(pollJobStatus, 3500);
}

async function pollJobStatus() {
  if (!activeJobId) return;

  try {
    const { ok, data } = await apiCall(`/api.php?action=get_job_status&id=${activeJobId}`);
    if (ok && data.success) {
      const job = data.data;
      updateLiveJobUI(job);

      if (job.status === 'COMPLETED' || job.status === 'FAILED') {
        clearInterval(pollTimer);
        pollTimer = null;
        refreshBalance();
        showToast(`งาน ${activeJobId.substring(0, 8)}... ประมวลผลเสร็จสิ้นแล้ว!`, 'success');
      }
    }
  } catch (e) {
    console.error('Poll error:', e);
  }
}

function updateLiveJobUI(job) {
  setJobStatusBadge(job.status);
  document.getElementById('statChargedThb').textContent = `${job.total_thb} ฿`;
  document.getElementById('statSuccessCount').textContent = job.success_amount ?? 0;
  document.getElementById('statFailCount').textContent = job.fail_amount ?? 0;
  document.getElementById('statRefundThb').textContent = `${job.refunded_thb || '0.00'} ฿`;
  if (job.accounts) {
    document.getElementById('statTotalAccs').textContent = job.accounts.length;
  }

  // Render per-account breakdown table
  const accContainer = document.getElementById('liveJobAccountsContainer');
  const tbody = document.getElementById('liveJobAccountsTableBody');

  if (job.accounts_detail && job.accounts_detail.length > 0) {
    accContainer.style.display = 'block';
    tbody.innerHTML = job.accounts_detail.map(item => {
      const u = (item.combo || '').split(':')[0] || 'Unknown';
      return `
        <tr>
          <td style="font-weight:600;font-family:var(--font-mono);">${u}</td>
          <td>${renderStatusTag(item.status)}</td>
        </tr>
      `;
    }).join('');
  } else if (job.accounts && job.accounts.length > 0) {
    accContainer.style.display = 'block';
    tbody.innerHTML = job.accounts.map(u => `
      <tr>
        <td style="font-weight:600;font-family:var(--font-mono);">${u}</td>
        <td><span class="job-status-badge status-PENDING">PENDING</span></td>
      </tr>
    `).join('');
  } else {
    accContainer.style.display = 'none';
  }
}

function setJobStatusBadge(status) {
  const badge = document.getElementById('liveJobStatusBadge');
  badge.className = `job-status-badge status-${status}`;
  badge.textContent = status;
}

document.getElementById('btnRefreshJobStatus')?.addEventListener('click', () => {
  showToast('กำลังอัปเดตสถานะงาน...', 'info');
  pollJobStatus();
});

document.getElementById('btnCloseLiveJob')?.addEventListener('click', () => {
  clearInterval(pollTimer);
  pollTimer = null;
  activeJobId = null;
  document.getElementById('liveJobSection').style.display = 'none';
});

// ===================== JOB HISTORY =====================
async function loadJobHistory() {
  const tbody = document.getElementById('historyTableBody');
  try {
    const { ok, data } = await apiCall('/api.php?action=get_jobs_history');
    if (ok && data.success) {
      const jobs = data.data || [];
      if (jobs.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9" style="text-align:center;color:var(--text-muted);padding:36px;">
              ยังไม่มีประวัติการส่งงาน
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = jobs.map(j => {
        const thb = (j.total_amount / 100).toFixed(2);
        const refundThb = (j.refunded_amount / 100).toFixed(2);
        return `
          <tr>
            <td class="mono" style="font-size:12px;color:var(--orange);font-weight:600;">${j.id.substring(0, 16)}...</td>
            <td><span style="font-size:11px;background:var(--input-bg);padding:3px 8px;border-radius:4px;border:1px solid var(--panel-border);">${j.service}</span></td>
            <td>${renderStatusTag(j.status)}</td>
            <td style="font-weight:600;font-family:var(--font-mono);">${j.total_accounts}</td>
            <td style="color:var(--orange);font-weight:700;font-family:var(--font-mono);">${thb} ฿</td>
            <td>
              <span style="color:var(--green);font-weight:700;font-family:var(--font-mono);">${j.success_amount}</span> /
              <span style="color:var(--red);font-family:var(--font-mono);">${j.fail_amount}</span>
            </td>
            <td style="color:var(--text-muted);font-family:var(--font-mono);">${refundThb > 0 ? refundThb + ' ฿' : '-'}</td>
            <td style="font-size:11px;color:var(--text-muted);">${j.created_at}</td>
            <td style="text-align:right;">
              <button class="btn btn-secondary btn-sm" onclick="viewJobLive('${j.id}')">ดูรายละเอียด</button>
            </td>
          </tr>
        `;
      }).join('');
    }
  } catch (e) {
    console.error(e);
  }
}

function viewJobLive(id) {
  switchTab('tab-submit');
  startLiveTracking(id);
}

document.getElementById('btnReloadHistory')?.addEventListener('click', () => {
  showToast('รีเฟรชประวัติงานแล้ว', 'info');
  loadJobHistory();
});

document.getElementById('btnRefreshBalance')?.addEventListener('click', refreshBalance);

// ===================== INIT =====================
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
  });

  loadSettings();
  refreshBalance();
  loadAccounts();
});
