// public/customer.js — Client-side logic for Lemon shop Customer Portal

let activeJobId = null;
let pollTimer = null;

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

// Update account count badge as customer types
const textarea = document.getElementById('custUsernames');
const countBadge = document.getElementById('custCountBadge');

function updateCount() {
  const val = textarea.value.trim();
  const list = val ? [...new Set(val.split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean))] : [];
  if (countBadge) countBadge.textContent = list.length;
}

textarea?.addEventListener('input', updateCount);

// Submit form
document.getElementById('formCustomerSubmit')?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const val = textarea.value.trim();
  const usernames = val ? [...new Set(val.split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean))] : [];

  if (usernames.length === 0) {
    return showToast('กรุณากรอกชื่อตัวละครอย่างน้อย 1 ชื่อ', 'error');
  }

  const btn = document.getElementById('btnCustSubmit');
  btn.disabled = true;
  btn.innerHTML = '<span>⏳</span><span>กำลังส่งข้อมูลแก้แคปช่า...</span>';

  try {
    const res = await fetch('/api.php?action=customer_submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ usernames })
    });

    const data = await res.json();
    btn.disabled = false;
    btn.innerHTML = '<span>🚀</span><span>เริ่มแก้แคปช่าทันที (Solve Captcha)</span>';

    if (res.ok && data.success) {
      showToast('ส่งงานสำเร็จ! กำลังเริ่มแก้แคปช่า...', 'success');
      startLiveTracking(data.data.job_id, data.data);
    } else {
      showToast(data.error || 'ไม่สามารถส่งงานได้ กรุณาติดต่อแอดมิน', 'error');
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<span>🚀</span><span>เริ่มแก้แคปช่าทันที (Solve Captcha)</span>';
    showToast('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
  }
});

function startLiveTracking(jobId, initialData = null) {
  activeJobId = jobId;
  clearInterval(pollTimer);

  const section = document.getElementById('customerJobSection');
  section.style.display = 'block';
  section.scrollIntoView({ behavior: 'smooth', block: 'start' });

  document.getElementById('custJobId').textContent = jobId.substring(0, 16) + '...';
  document.getElementById('custFinishMsg').style.display = 'none';

  if (initialData) {
    document.getElementById('custTotal').textContent = initialData.total_accounts || 0;
    setJobBadge(initialData.status || 'PENDING');
  }

  pollJobStatus();
  pollTimer = setInterval(pollJobStatus, 3000);
}

async function pollJobStatus() {
  if (!activeJobId) return;

  try {
    const res = await fetch(`/api.php?action=customer_job_status&id=${activeJobId}`);
    const result = await res.json();

    if (res.ok && result.success) {
      const job = result.data;
      setJobBadge(job.status);

      document.getElementById('custTotal').textContent = job.total_accounts || 0;
      document.getElementById('custSuccess').textContent = job.success_count ?? 0;
      document.getElementById('custFail').textContent = job.fail_count ?? 0;

      // Table breakdown
      const wrapper = document.getElementById('custAccountsWrapper');
      const tbody = document.getElementById('custAccountsTable');

      if (job.accounts_detail && job.accounts_detail.length > 0) {
        wrapper.style.display = 'block';
        tbody.innerHTML = job.accounts_detail.map(item => `
          <tr>
            <td style="font-weight:600;font-family:var(--font-mono);">${item.username}</td>
            <td style="text-align:right;">${renderStatusPill(item.status)}</td>
          </tr>
        `).join('');
      } else if (job.accounts && job.accounts.length > 0) {
        wrapper.style.display = 'block';
        tbody.innerHTML = job.accounts.map(u => `
          <tr>
            <td style="font-weight:600;font-family:var(--font-mono);">${u}</td>
            <td style="text-align:right;"><span class="job-status-badge status-PENDING">กำลังรอคิว...</span></td>
          </tr>
        `).join('');
      }

      if (job.status === 'COMPLETED' || job.status === 'FAILED') {
        clearInterval(pollTimer);
        pollTimer = null;
        document.getElementById('custFinishMsg').style.display = 'block';
        if (job.status === 'COMPLETED') {
          showToast('แก้แคปช่าเสร็จสิ้นเรียบร้อยแล้ว!', 'success');
        }
      }
    }
  } catch (err) {
    console.error('Customer poll error:', err);
  }
}

function setJobBadge(status) {
  const badge = document.getElementById('custJobStatusBadge');
  if (!badge) return;

  badge.className = `job-status-badge status-${status}`;
  if (status === 'PENDING') badge.textContent = 'กำลังรอคิว...';
  else if (status === 'PROCESSING') badge.textContent = 'กำลังแก้แคปช่า...';
  else if (status === 'COMPLETED') badge.textContent = 'เสร็จสิ้น';
  else if (status === 'FAILED') badge.textContent = 'ล้มเหลว';
  else badge.textContent = status;
}

function renderStatusPill(status) {
  let cls = 'status-PENDING';
  let text = status;
  if (status === 'COMPLETED' || status === 'SUCCESS') {
    cls = 'status-COMPLETED';
    text = 'สำเร็จ ✅';
  } else if (status === 'PROCESSING') {
    cls = 'status-PROCESSING';
    text = 'กำลังทำ...';
  } else if (status === 'FAILED' || status === 'COOKIE_BROKEN') {
    cls = 'status-FAILED';
    text = 'ไม่ผ่าน ❌';
  }
  return `<span class="job-status-badge ${cls}" style="font-size:11px;padding:2px 8px;">${text}</span>`;
}

document.getElementById('btnCustRefresh')?.addEventListener('click', () => {
  showToast('กำลังรีเฟรชสถานะ...', 'info');
  pollJobStatus();
});
