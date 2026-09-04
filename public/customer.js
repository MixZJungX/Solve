// public/customer.js — Customer Portal Logic for Lemon shop Solve | Helper

let activeJobId = null;
let pollTimer = null;
let progressTimer = null;
let currentProgress = 0;
let progressStartTime = null;

const ADHD_STAGES = [
  { at: 0,  text: "🚀 [1/5] กำลังส่งคำขอและเชื่อมต่อเซิร์ฟเวอร์ AI...", hint: "เตรียมพร้อมเริ่มระบบแก้แคปช่า Roblox" },
  { at: 15, text: "🔍 [2/5] ตรวจสอบ Token และวิเคราะห์โจทย์ Funcaptcha...", hint: "ระบบกำลังตรวจจับด่านป้องกันของแมพ" },
  { at: 35, text: "🤖 [3/5] AI กำลังประมวลผลหมุนรูปภาพและแก้โจทย์...", hint: "กำลังแก้แคปช่าอัตโนมัติ ห้ามปิดหน้านี้!" },
  { at: 65, text: "🛡️ [4/5] ยืนยันคำตอบกับเซิร์ฟเวอร์ Roblox ป้องกันหลุด...", hint: "ใกล้เสร็จแล้ว! AI ทำงานสำเร็จไปกว่า 70%" },
  { at: 85, text: "⚡ [5/5] รอ Roblox บันทึกเซสชัน... เกือบเสร็จแล้ว!", hint: "อีกนิดเดียวเท่านั้น กำลังจะถึงเส้นชัยแล้ว..." },
  { at: 94, text: "⏳ รอเซิร์ฟเวอร์ตอบรับและยืนยันการเข้าเกม...", hint: "กำลังตรวจสอบผลลัพธ์รอบสุดท้าย..." },
  { at: 100, text: "🎉 เสร็จสมบูรณ์แล้ว! สามารถเข้าเล่นแมพได้ทันที", hint: "ปลดล็อคสำเร็จ 100%!" }
];

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

// Dynamic counter for usernames in textarea
const custTextarea = document.getElementById('custUsernames');
custTextarea?.addEventListener('input', () => {
  const text = custTextarea.value.trim();
  if (!text) {
    document.getElementById('custCountBadge').textContent = '0';
    return;
  }
  const lines = text.split(/\r\n|\n|\r/).map(l => l.trim()).filter(l => l.length > 0);
  document.getElementById('custCountBadge').textContent = lines.length;
});

// Submit Captcha Form
document.getElementById('formCustomerSubmit')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const text = document.getElementById('custUsernames').value.trim();
  if (!text) return showToast('กรุณาระบุชื่อตัวละครอย่างน้อย 1 ชื่อ', 'error');

  const usernames = text.split(/\r\n|\n|\r/).map(l => l.trim()).filter(l => l.length > 0);
  if (usernames.length === 0) return showToast('กรุณาระบุชื่อตัวละครอย่างน้อย 1 ชื่อ', 'error');

  const btn = document.getElementById('btnCustSubmit');
  btn.disabled = true;
  btn.innerHTML = '<span>⏳</span><span>กำลังส่งงานแก้แคปช่า...</span>';

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
    const modeBadge = document.getElementById('custQueueModeBadge');
    if (modeBadge) {
      modeBadge.textContent = initialData.queue_mode === 'priority' ? '⚡ เร่งด่วน x2' : '⏳ คิวปกติ';
    }
    const qEl = document.getElementById('custQueue');
    if (qEl) {
      qEl.textContent = initialData.queue_position > 0 ? `คิวที่ ${initialData.queue_position}` : 'รอเริ่มคิว';
    }
    setJobBadge(initialData.status || 'PENDING');
  }

  // Start the ADHD live animated progress bar
  startFakeProgressBar();

  pollJobStatus();
  pollTimer = setInterval(pollJobStatus, 3000);
}

// ADHD Progress Bar Controller
function startFakeProgressBar() {
  if (progressTimer) clearInterval(progressTimer);
  currentProgress = 6;
  progressStartTime = Date.now();
  updateProgressBarUI(currentProgress);

  progressTimer = setInterval(() => {
    // Elapsed time calculation
    const elapsed = Math.floor((Date.now() - progressStartTime) / 1000);
    const mm = String(Math.floor(elapsed / 60)).padStart(2, '0');
    const ss = String(elapsed % 60).padStart(2, '0');
    const timeEl = document.getElementById('custProgressTimer');
    if (timeEl) timeEl.textContent = `⏱️ ${mm}:${ss}`;

    // ADHD satisfaction curve: fast early dopamine, steady middle, slow crawling near goal
    if (currentProgress < 30) {
      currentProgress += Math.random() * 4.5 + 2.5;
    } else if (currentProgress < 65) {
      currentProgress += Math.random() * 2.8 + 1.2;
    } else if (currentProgress < 85) {
      currentProgress += Math.random() * 1.4 + 0.6;
    } else if (currentProgress < 94) {
      currentProgress += Math.random() * 0.4 + 0.1;
    }

    if (currentProgress > 95) currentProgress = 95;
    updateProgressBarUI(currentProgress);
  }, 450);
}

function completeFakeProgressBar() {
  if (progressTimer) clearInterval(progressTimer);
  currentProgress = 100;
  updateProgressBarUI(100, true);
}

function updateProgressBarUI(pct, isFinished = false) {
  const rounded = Math.min(100, Math.floor(pct));
  const bar = document.getElementById('custProgressBar');
  const percentEl = document.getElementById('custProgressPercent');
  const stageEl = document.getElementById('custProgressStage');
  const hintEl = document.getElementById('custProgressHint');
  const dot = document.getElementById('custProgressDot');

  if (bar) {
    bar.style.width = `${rounded}%`;
    if (isFinished) {
      bar.classList.add('completed');
    } else {
      bar.classList.remove('completed');
    }
  }

  if (percentEl) {
    percentEl.textContent = `${rounded}%`;
    if (isFinished) {
      percentEl.style.color = 'var(--green)';
    } else {
      percentEl.style.color = 'var(--lemon)';
    }
  }

  // Find matching stage
  let matchedStage = ADHD_STAGES[0];
  for (const s of ADHD_STAGES) {
    if (rounded >= s.at) {
      matchedStage = s;
    }
  }

  if (stageEl) {
    stageEl.textContent = isFinished ? '🎉 ดำเนินการเสร็จสิ้นเรียบร้อยแล้ว!' : matchedStage.text;
  }
  if (hintEl) {
    hintEl.textContent = isFinished ? 'สำเร็จ 100%! บัญชีพร้อมเข้าเล่นเกมได้ทันที' : matchedStage.hint;
  }
  if (dot && isFinished) {
    dot.className = 'pulse-dot completed';
  }
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

      // Queue Position rendering
      const qEl = document.getElementById('custQueue');
      if (qEl) {
        if (job.status === 'PENDING') {
          const pos = job.queue_position ?? 0;
          qEl.textContent = pos > 0 ? `คิวที่ ${pos}` : 'รอเริ่มคิว';
          qEl.style.color = 'var(--lemon)';
        } else if (job.status === 'PROCESSING') {
          qEl.textContent = '⚡ กำลังแก้';
          qEl.style.color = 'var(--lemon)';
        } else if (job.status === 'COMPLETED') {
          qEl.textContent = '✅ เสร็จสิ้น';
          qEl.style.color = 'var(--green)';
        } else if (job.status === 'FAILED') {
          qEl.textContent = '❌ ยกเลิก';
          qEl.style.color = 'var(--red)';
        } else {
          qEl.textContent = '-';
        }
      }

      const modeBadge = document.getElementById('custQueueModeBadge');
      if (modeBadge && (job.queue_mode || job.priority !== undefined)) {
        const isX2 = job.queue_mode === 'priority' || job.priority === true;
        modeBadge.textContent = isX2 ? '⚡ เร่งด่วน x2' : '⏳ คิวปกติ';
      }

      const sCount = job.success_count ?? 0;
      const skCount = job.skip_count ?? 0;
      const fCount = job.fail_count ?? 0;

      const successEl = document.getElementById('custSuccess');
      if (sCount === 0 && skCount > 0) {
        successEl.textContent = `${skCount} (พร้อมเล่น)`;
        successEl.style.color = '#2dd4bf';
      } else if (sCount > 0 && skCount > 0) {
        successEl.textContent = `${sCount} (+${skCount})`;
        successEl.style.color = 'var(--green)';
      } else {
        successEl.textContent = sCount;
        successEl.style.color = 'var(--green)';
      }
      document.getElementById('custFail').textContent = fCount;

      // Table breakdown with True Status & Helpful Subtitles
      const wrapper = document.getElementById('custAccountsWrapper');
      const tbody = document.getElementById('custAccountsTable');

      if (job.accounts_detail && job.accounts_detail.length > 0) {
        wrapper.style.display = 'block';
        tbody.innerHTML = job.accounts_detail.map(item => {
          const info = getAccountStatusInfo(item.status);
          return `
            <tr>
              <td>
                <div style="font-weight:600;font-family:var(--font-mono);font-size:14px;color:#fff;">${item.username}</div>
                ${info.desc ? `<div style="font-size:11px;color:var(--text-muted);margin-top:3px;line-height:1.4;">${info.desc}</div>` : ''}
              </td>
              <td style="text-align:right;vertical-align:middle;">
                <span class="job-status-badge ${info.cls}" style="font-size:11px;padding:4px 10px;white-space:nowrap;">
                  ${info.text}
                </span>
              </td>
            </tr>
          `;
        }).join('');
      } else if (job.accounts && job.accounts.length > 0) {
        wrapper.style.display = 'block';
        tbody.innerHTML = job.accounts.map(u => `
          <tr>
            <td>
              <div style="font-weight:600;font-family:var(--font-mono);font-size:14px;color:#fff;">${u}</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">กำลังรอระบบเริ่มทำงานตามคิว</div>
            </td>
            <td style="text-align:right;vertical-align:middle;">
              <span class="job-status-badge status-PENDING" style="font-size:11px;padding:4px 10px;">รอคิว... ⏳</span>
            </td>
          </tr>
        `).join('');
      }

      if (job.status === 'COMPLETED' || job.status === 'FAILED') {
        clearInterval(pollTimer);
        pollTimer = null;
        completeFakeProgressBar();
        const finishEl = document.getElementById('custFinishMsg');
        finishEl.style.display = 'block';

        const isAllSkipped = job.accounts_detail && job.accounts_detail.length > 0 && job.accounts_detail.every(a => a.status === 'SKIP');

        if (isAllSkipped) {
          finishEl.style.background = 'rgba(20, 184, 166, 0.12)';
          finishEl.style.borderColor = 'rgba(45, 212, 191, 0.4)';
          finishEl.style.color = '#2dd4bf';
          finishEl.innerHTML = `
            <div style="font-weight:700;font-size:14px;margin-bottom:4px;">✨ บัญชีนี้ไม่มีแคปช่าติดค้าง หรือผ่านการแก้ไขแล้ว!</div>
            <div style="font-size:12px;opacity:0.95;line-height:1.5;">ระบบตรวจพบว่าบัญชีนี้เข้าเล่นเกมได้ตามปกติ ไม่พบด่านแคปช่า Roblox ที่ต้องแก้ (ระบบไม่คิดเงินค่าบริการ) สามารถเข้าเล่นแมพได้ทันทีครับ</div>
            <button class="btn btn-secondary btn-sm" onclick="resetCustomerJobSection()" style="margin-top:10px;font-size:12px;cursor:pointer;">✨ ตรวจสอบไอดีอื่น / เริ่มรายการใหม่</button>
          `;
          showToast('บัญชีนี้ไม่มีแคปช่า พร้อมเข้าเล่นได้ทันที (ไม่คิดเงิน)', 'success');
        } else if (job.status === 'COMPLETED') {
          finishEl.style.background = 'rgba(52, 211, 153, 0.1)';
          finishEl.style.borderColor = 'rgba(52, 211, 153, 0.3)';
          finishEl.style.color = 'var(--green)';
          finishEl.innerHTML = `
            <div style="font-weight:700;margin-bottom:4px;">🎉 การแก้แคปช่าเสร็จสิ้นเรียบร้อยแล้ว!</div>
            <div style="font-size:12px;opacity:0.9;line-height:1.5;">สามารถเข้าเกม Roblox ได้ทันที หากในอนาคตหลุดหรือติดแคปช่าอีก สามารถนำมากดส่งแก้ใหม่ได้ตลอดเวลา</div>
            <button class="btn btn-secondary btn-sm" onclick="resetCustomerJobSection()" style="margin-top:10px;font-size:12px;cursor:pointer;">✨ ส่งแก้ไอดีอื่น / เริ่มรายการใหม่</button>
          `;
          showToast('แก้แคปช่าเสร็จสิ้นเรียบร้อยแล้ว!', 'success');
        } else {
          finishEl.style.background = 'rgba(248, 113, 113, 0.1)';
          finishEl.style.borderColor = 'rgba(248, 113, 113, 0.3)';
          finishEl.style.color = 'var(--red)';
          finishEl.innerHTML = `
            <div style="font-weight:700;margin-bottom:4px;">❌ การประมวลผลล้มเหลว หรือถูกยกเลิก (ระบบคืนเงินแล้ว)</div>
            <button class="btn btn-secondary btn-sm" onclick="resetCustomerJobSection()" style="margin-top:10px;font-size:12px;cursor:pointer;">🔄 ลองใหม่อีกครั้ง</button>
          `;
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

// True Account Status Explanations (Clear, transparent, customer-friendly)
function getAccountStatusInfo(status) {
  const st = String(status || 'PENDING').toUpperCase();

  if (st === 'COMPLETED' || st === 'SUCCESS') {
    return {
      cls: 'status-COMPLETED',
      text: 'สำเร็จ ✅',
      desc: 'แก้แคปช่าผ่านเรียบร้อย สามารถเข้าเล่นแมพได้ทันที'
    };
  }

  if (st === 'PROCESSING') {
    return {
      cls: 'status-PROCESSING',
      text: 'กำลังแก้... ⚡',
      desc: 'AI กำลังวิเคราะห์โจทย์รูปภาพและส่งคำตอบ'
    };
  }

  if (st === 'PENDING') {
    return {
      cls: 'status-PENDING',
      text: 'รอคิว... ⏳',
      desc: 'อยู่ในคิวรอระบบเริ่มประมวลผล'
    };
  }

  if (st === 'COOKIE_BROKEN' || st === 'INVALID' || st === 'WRONG_PASSWORD') {
    return {
      cls: 'status-COOKIE_BROKEN',
      text: 'Cookie แตก / เปลี่ยนรหัสผ่าน 🔑',
      desc: 'ลูกค้าเปลี่ยนรหัสผ่าน Roblox หรือ Cookie หลุด/หมดอายุ ต้องอัปเดต Cookie ใหม่ในระบบ'
    };
  }

  if (st === 'FACE_LOCK') {
    return {
      cls: 'status-FACE_LOCK',
      text: 'ติดสแกนหน้า (Face Lock) 👤',
      desc: 'บัญชีนี้ติดระบบยืนยันตัวตนสแกนใบหน้าของ Roblox กรุณาปลดสแกนหน้าในเกม'
    };
  }

  if (st === 'TWO_STEP' || st === '2STEP') {
    return {
      cls: 'status-TWO_STEP',
      text: 'ติดรหัส 2 ชั้น (2-Step) 📱',
      desc: 'บัญชีติดรหัสยืนยัน 2 ขั้นตอน (Authenticator หรือ Email)'
    };
  }

  if (st === 'BANNED') {
    return {
      cls: 'status-BANNED',
      text: 'บัญชีถูกแบน (Banned) ⛔',
      desc: 'บัญชีนี้ถูกระงับการใช้งานโดย Roblox'
    };
  }

  if (st === 'RATE_LIMIT') {
    return {
      cls: 'status-PENDING',
      text: 'ติด Rate Limit ชั่วคราว ⏳',
      desc: 'ส่งคำขอถี่เกินไป ระบบกำลังเว้นช่วงและจะลองใหม่อัตโนมัติ'
    };
  }

  if (st === 'SKIP') {
    return {
      cls: 'status-SKIP',
      text: 'ไม่ต้องแก้ (ไม่มีแคปช่า / ผ่านอยู่แล้ว) ✨',
      desc: 'ระบบตรวจพบว่าบัญชีนี้ไม่มีด่านแคปช่าติดค้าง หรือผ่านการแก้ไขแล้ว สามารถเข้าเล่นเกมได้ทันที (ระบบไม่คิดค่าบริการ)'
    };
  }

  if (st === 'FAILED') {
    return {
      cls: 'status-FAILED',
      text: 'แก้ไม่สำเร็จ (คืนเงินแล้ว) ❌',
      desc: 'AI ไม่สามารถแก้โจทย์แคปช่าได้ ระบบคืนเงินค่าบริการให้เรียบร้อยแล้ว'
    };
  }

  // Generic Fallback
  return {
    cls: 'status-FAILED',
    text: `${status} ❌`,
    desc: 'สถานะไม่ผ่านการทดสอบ'
  };
}

function resetCustomerJobSection() {
  activeJobId = null;
  if (pollTimer) clearInterval(pollTimer);
  if (progressTimer) clearInterval(progressTimer);
  const section = document.getElementById('customerJobSection');
  if (section) section.style.display = 'none';
  const finishEl = document.getElementById('custFinishMsg');
  if (finishEl) finishEl.style.display = 'none';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('btnCustRefresh')?.addEventListener('click', () => {
  showToast('กำลังรีเฟรชสถานะ...', 'info');
  pollJobStatus();
});
