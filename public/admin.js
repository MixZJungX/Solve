// public/admin.js — Admin Dashboard Logic for Lemon shop Solve | Helper

let adminAccounts = [];

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
  }, 4500);
}

async function apiCall(endpoint, method = 'GET', body = null) {
  const token = localStorage.getItem('lemon_admin_token');
  const headers = { 'Content-Type': 'application/json' };
  if (token) {
    headers['X-Admin-Token'] = token;
  }
  const options = {
    method,
    headers
  };
  if (body) {
    options.body = JSON.stringify(body);
  }
  const res = await fetch(endpoint, options);
  const data = await res.json();
  return { status: res.status, ok: res.ok, data };
}

function openModal(id) {
  const m = document.getElementById(id);
  if (m) {
    m.style.display = 'flex';
    m.classList.add('active');
  }
}

function closeModal(id) {
  const m = document.getElementById(id);
  if (m) {
    m.style.display = 'none';
    m.classList.remove('active');
  }
}

// Tab Switching
function switchAdminTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabId);
  });
  document.querySelectorAll('.tab-pane').forEach(pane => {
    pane.classList.toggle('active', pane.id === tabId);
  });

  if (tabId === 'tab-accounts') loadAdminAccounts();
  if (tabId === 'tab-history') loadAdminHistory();
  if (tabId === 'tab-settings') loadAdminSettings();
}

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => switchAdminTab(btn.dataset.tab));
});

// Auth Check
async function checkAuth() {
  try {
    const { ok, data } = await apiCall('/api.php?action=admin_check_auth');
    if (ok && data.is_admin) {
      loadAdminSettings();
      refreshAdminBalance();
      loadAdminAccounts();
    } else {
      window.location.href = '/login';
    }
  } catch (e) {
    window.location.href = '/login';
  }
}

// Logout
document.getElementById('btnAdminLogout')?.addEventListener('click', async () => {
  await apiCall('/api.php?action=admin_logout', 'POST');
  localStorage.removeItem('lemon_admin_token');
  document.cookie = "lemon_admin_auth=; path=/; max-age=0";
  showToast('ออกจากระบบเรียบร้อย', 'info');
  setTimeout(() => {
    window.location.href = '/login';
  }, 300);
});

// Balance
async function refreshAdminBalance() {
  const el = document.getElementById('adminBalance');
  const dot = document.getElementById('adminApiDot');
  if (el) el.innerHTML = '<span style="color:var(--text-muted);font-size:12px;">กำลังเช็ค...</span>';

  try {
    const { ok, data } = await apiCall('/api.php?action=get_balance');
    if (ok && data.success) {
      el.innerHTML = `<span>${data.data.thb} ฿</span> <span style="font-size:12px;color:var(--text-muted);font-weight:normal;">(${Number(data.data.points).toLocaleString()} สตางค์)</span>`;
      dot.className = 'pulse-dot online';
    } else {
      el.innerHTML = '<span style="color:var(--text-dim);font-size:12px;">ยังไม่เชื่อมต่อ</span>';
      dot.className = 'pulse-dot';
    }
  } catch (e) {
    el.innerHTML = '<span style="color:var(--red);font-size:12px;">ข้อผิดพลาด</span>';
    dot.className = 'pulse-dot';
  }
}

document.getElementById('btnAdminRefreshBal')?.addEventListener('click', refreshAdminBalance);
document.getElementById('btnTestApiKey')?.addEventListener('click', async () => {
  showToast('กำลังทดสอบ API...', 'info');
  await refreshAdminBalance();
});

// Settings & Queue Mode
async function loadAdminSettings() {
  try {
    const { ok, data } = await apiCall('/api.php?action=get_settings');
    if (ok && data.success) {
      const cfg = data.data;
      const maskEl = document.getElementById('adminMaskedKey');
      if (cfg.has_key) {
        maskEl.innerHTML = `🔑 คีย์ปัจจุบัน: <b style="color:var(--lemon);">${cfg.masked_key}</b>`;
      } else {
        maskEl.textContent = 'ยังไม่ได้ใส่ API Key';
      }

      // Queue mode radio
      const mode = cfg.queue_mode || 'normal';
      const radios = document.querySelectorAll('input[name="queueMode"]');
      radios.forEach(r => {
        r.checked = (r.value === mode);
      });
      updateQueueModeCards(mode);
    }
  } catch (e) {
    console.error(e);
  }
}

function updateQueueModeCards(selectedMode) {
  document.getElementById('cardQueueNormal')?.classList.toggle('selected', selectedMode === 'normal');
  document.getElementById('cardQueuePriority')?.classList.toggle('selected', selectedMode === 'priority');
}

document.querySelectorAll('input[name="queueMode"]').forEach(radio => {
  radio.addEventListener('change', (e) => {
    updateQueueModeCards(e.target.value);
  });
});

document.getElementById('formAdminSettings')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const queueMode = document.querySelector('input[name="queueMode"]:checked')?.value || 'normal';
  const apiKey = document.getElementById('settingApiKey').value.trim();
  const adminPass = document.getElementById('settingAdminPass').value.trim();

  const payload = { queue_mode: queueMode };
  if (apiKey) payload.api_key = apiKey;
  if (adminPass) payload.admin_password = adminPass;

  const { ok, data } = await apiCall('/api.php?action=save_settings', 'POST', payload);
  if (ok && data.success) {
    showToast('บันทึกการตั้งค่าร้านค้าเรียบร้อยแล้ว!', 'success');
    document.getElementById('settingApiKey').value = '';
    document.getElementById('settingAdminPass').value = '';
    loadAdminSettings();
    refreshAdminBalance();
  } else {
    showToast(data.error || 'บันทึกไม่สำเร็จ', 'error');
  }
});

// Accounts Management
async function loadAdminAccounts(query = '') {
  const tbody = document.getElementById('adminAccTableBody');
  const badge = document.getElementById('adminAccCount');

  try {
    const { ok, data } = await apiCall(`/api.php?action=get_accounts&q=${encodeURIComponent(query)}`);
    if (ok && data.success) {
      adminAccounts = data.data || [];
      if (badge) badge.textContent = adminAccounts.length;

      if (adminAccounts.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align:center;color:var(--text-muted);padding:36px;">
              ยังไม่มีบัญชีในระบบ กด "นำเข้าแบบ Combo" เพื่อเพิ่มบัญชี
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = adminAccounts.map((acc, i) => `
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
            <button class="btn btn-danger btn-sm" onclick="deleteAdminAccount(${acc.id}, '${acc.username}')" title="ลบ">🗑️ ลบ</button>
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

function copyText(text) {
  navigator.clipboard.writeText(text);
  showToast(`คัดลอก "${text}" แล้ว`, 'success');
}

async function deleteAdminAccount(id, username) {
  if (!confirm(`คุณแน่ใจว่าต้องการลบบัญชี "${username}"?`)) return;
  const { ok, data } = await apiCall(`/api.php?action=delete_account&id=${id}`, 'DELETE');
  if (ok && data.success) {
    showToast(`ลบ "${username}" แล้ว`, 'info');
    loadAdminAccounts();
  } else {
    showToast(data.error || 'ลบไม่สำเร็จ', 'error');
  }
}

// Clear All Accounts
document.getElementById('btnAdminClearAll')?.addEventListener('click', async () => {
  const count = adminAccounts.length || 0;
  if (count === 0) {
    return showToast('ไม่มีบัญชีในระบบให้ลบ', 'info');
  }

  const confirmed = confirm(`⚠️ คำเตือนความปลอดภัย:\nคุณต้องการล้างบัญชีทั้งหมดจำนวน ${count} บัญชี ออกจากระบบใช่หรือไม่?\n\n(การกระทำนี้จะลบข้อมูลบัญชีและคุกกี้ทั้งหมด และไม่สามารถกู้คืนได้)`);
  if (!confirmed) return;

  const btn = document.getElementById('btnAdminClearAll');
  btn.disabled = true;
  btn.innerHTML = '<span>⏳</span> กำลังล้าง...';

  const { ok, data } = await apiCall('/api.php?action=clear_all_accounts', 'POST');
  btn.disabled = false;
  btn.innerHTML = '<span>🗑️</span> <span>ล้างบัญชีทั้งหมด</span>';

  if (ok && data.success) {
    showToast(data.message, 'success');
    loadAdminAccounts();
  } else {
    showToast(data.error || 'เกิดข้อผิดพลาดในการล้างบัญชี', 'error');
  }
});

// Bulk Import
document.getElementById('btnAdminBulkImport')?.addEventListener('click', () => {
  openModal('modalBulkImport');
});

document.getElementById('btnSubmitBulkImport')?.addEventListener('click', async () => {
  const text = document.getElementById('bulkImportText').value.trim();
  if (!text) return showToast('กรุณาวางข้อมูลบัญชีก่อน', 'error');

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
    loadAdminAccounts();
  } else {
    showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
  }
});

// Single Add Modal
document.getElementById('btnAdminAddSingle')?.addEventListener('click', () => {
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
    showToast(data.message || 'บันทึกบัญชีเรียบร้อย', 'success');
    document.getElementById('formAddAccount').reset();
    closeModal('modalAddAccount');
    loadAdminAccounts();
  } else {
    showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
  }
});

// Search
let adminSearchDebounce = null;
document.getElementById('adminSearchAcc')?.addEventListener('input', (e) => {
  clearTimeout(adminSearchDebounce);
  adminSearchDebounce = setTimeout(() => {
    loadAdminAccounts(e.target.value.trim());
  }, 300);
});

document.getElementById('btnAdminReloadAccs')?.addEventListener('click', () => {
  loadAdminAccounts(document.getElementById('adminSearchAcc').value.trim());
});

// Job History
async function loadAdminHistory() {
  const tbody = document.getElementById('adminHistoryTableBody');
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
        const queueModeText = j.priority ? '⚡ เร่งด่วน x2' : '⏳ ปกติ';
        const sCount = j.success_count ?? (j.status === 'COMPLETED' ? j.total_accounts : 0);
        const fCount = j.fail_count ?? 0;
        return `
          <tr>
            <td class="mono" style="font-size:12px;color:var(--lemon);font-weight:600;">${j.id.substring(0, 16)}...</td>
            <td>${renderStatusTag(j.status)}</td>
            <td><span style="font-size:11px;background:var(--input-bg);padding:3px 8px;border-radius:4px;border:1px solid var(--panel-border);">${queueModeText}</span></td>
            <td style="font-weight:600;font-family:var(--font-mono);">${j.total_accounts}</td>
            <td style="color:var(--lemon);font-weight:700;font-family:var(--font-mono);">${thb} ฿</td>
            <td>
              <span style="color:var(--green);font-weight:700;font-family:var(--font-mono);">${sCount}</span> /
              <span style="color:var(--red);font-family:var(--font-mono);">${fCount}</span>
            </td>
            <td style="color:var(--text-muted);font-family:var(--font-mono);">${refundThb > 0 ? refundThb + ' ฿' : '-'}</td>
            <td style="font-size:11px;color:var(--text-muted);">${j.created_at}</td>
            <td style="text-align:right;">
              <button class="btn btn-secondary btn-sm" onclick="viewJobDetail('${j.id}')">ดูรายละเอียด</button>
            </td>
          </tr>
        `;
      }).join('');
    }
  } catch (e) {
    console.error(e);
  }
}

async function viewJobDetail(id) {
  try {
    const { ok, data } = await apiCall(`/api.php?action=get_job_status&id=${id}`);
    if (ok && data.success) {
      const job = data.data;
      document.getElementById('modalJobDetailTitle').textContent = `รายละเอียดงาน: ${job.id.substring(0, 16)}...`;
      
      let accountsHtml = '';
      if (job.accounts_detail && job.accounts_detail.length > 0) {
        accountsHtml = `
          <div class="table-wrapper" style="max-height:220px;overflow-y:auto;margin-top:12px;">
            <table>
              <thead><tr><th>Account Combo / User</th><th>สถานะ</th></tr></thead>
              <tbody>
                ${job.accounts_detail.map(a => `
                  <tr>
                    <td class="mono" style="font-size:12px;">${a.combo || a.username || '-'}</td>
                    <td>${renderStatusTag(a.status)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      }

      const sCount = job.success_count ?? (job.status === 'COMPLETED' ? (job.accounts?.length || 1) : 0);
      const fCount = job.fail_count ?? 0;

      document.getElementById('modalJobDetailContent').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
          <div><b>สถานะ:</b> ${renderStatusTag(job.status)}</div>
          <div><b>โหมด:</b> ${job.priority ? '⚡ เร่งด่วน x2' : 'คิวปกติ'}</div>
          <div><b>ตัดเงินไป:</b> ${job.total_thb} ฿</div>
          <div><b>คืนเงิน:</b> ${job.refunded_thb} ฿</div>
          <div><b>สำเร็จ:</b> <span style="color:var(--green);font-weight:700;">${sCount} ไอดี</span></div>
          <div><b>ตก/ล้มเหลว:</b> <span style="color:var(--red);font-weight:700;">${fCount} ไอดี</span></div>
        </div>
        ${accountsHtml}
      `;
      openModal('modalJobDetail');
    }
  } catch (e) {
    showToast('ไม่สามารถดึงข้อมูลงานได้', 'error');
  }
}

document.getElementById('btnAdminReloadHistory')?.addEventListener('click', () => {
  showToast('รีเฟรชประวัติงานแล้ว', 'info');
  loadAdminHistory();
});

// Init
window.addEventListener('DOMContentLoaded', () => {
  checkAuth();
});
