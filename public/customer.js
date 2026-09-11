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
    } else if (data.requires_login) {
      // External (paid) account requires member login
      showToast('บัญชีนี้เป็นประเภทชำระเงิน กรุณาเข้าสู่ระบบสมาชิกก่อน', 'error');
      // Open login modal if it exists
      const modalAuth = document.getElementById('modalMemberAuth');
      if (modalAuth) {
        modalAuth.style.display = 'flex';
        setTimeout(() => {
          const loginTab = modalAuth.querySelector('[data-tab="login"]') || modalAuth.querySelector('.modal-tab-btn');
          loginTab?.click();
        }, 100);
      }
    } else if (data.insufficient_credits) {
      // Not enough credits
      const paidCount = (data.paid_accounts || []).length;
      showToast(
        `เครดิตไม่พอ! ต้องการ ${data.credits_required} เครดิต (มีอยู่ ${data.credits_available} เครดิต) สำหรับ ${paidCount} บัญชีชำระเงิน`,
        'error'
      );
      // Show topup section if available
      const topupSection = document.getElementById('topupSection') || document.querySelector('[data-section="topup"]');
      if (topupSection) topupSection.scrollIntoView({ behavior: 'smooth' });
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

function completeFakeProgressBar(job = null) {
  if (progressTimer) clearInterval(progressTimer);
  currentProgress = 100;
  updateProgressBarUI(100, true, job);
}

function updateProgressBarUI(pct, isFinished = false, job = null) {
  const rounded = Math.min(100, Math.floor(pct));
  const bar = document.getElementById('custProgressBar');
  const percentEl = document.getElementById('custProgressPercent');
  const stageEl = document.getElementById('custProgressStage');
  const hintEl = document.getElementById('custProgressHint');
  const dot = document.getElementById('custProgressDot');

  let sCount = Number(job?.success_count ?? 0);
  let fCount = Number(job?.fail_count ?? 0);
  let skCount = Number(job?.skip_count ?? 0);

  if (job?.accounts_detail && Array.isArray(job.accounts_detail) && job.accounts_detail.length > 0) {
    let accS = 0, accF = 0, accSk = 0;
    for (const a of job.accounts_detail) {
      const st = String(a.status || '').trim().toUpperCase();
      if (['COMPLETED', 'SUCCESS'].includes(st)) accS++;
      else if (['SKIP', 'SKIPPED', 'NO_CAPTCHA'].includes(st)) accSk++;
      else if (['FAILED', 'FAIL', 'ERROR', 'COOKIE_BROKEN', 'FACE_LOCK', 'FACELOCK', 'WRONG_PASSWORD', 'INVALID', 'INV', 'TWO_STEP', '2STEP', '2FA', 'BANNED', 'BAN'].includes(st)) accF++;
      else if (st && !['PENDING', 'PROCESSING', 'QUEUED'].includes(st)) accF++;
    }
    if (accS > 0 || accF > 0 || accSk > 0) {
      sCount = accS;
      fCount = accF;
      skCount = accSk;
    }
  }

  const isAllFailed = isFinished && ((fCount > 0 && sCount === 0 && skCount === 0) || (job?.status === 'FAILED' && sCount === 0 && skCount === 0));
  const isAllSkipped = isFinished && skCount > 0 && sCount === 0 && fCount === 0;
  const isPartial = isFinished && sCount > 0 && fCount > 0;

  if (bar) {
    bar.style.width = `${rounded}%`;
    bar.classList.remove('completed', 'failed', 'skipped', 'partial');
    if (isFinished) {
      if (isAllFailed) bar.classList.add('failed');
      else if (isAllSkipped) bar.classList.add('skipped');
      else if (isPartial) bar.classList.add('partial');
      else bar.classList.add('completed');
    }
  }

  if (percentEl) {
    percentEl.textContent = `${rounded}%`;
    if (isFinished) {
      if (isAllFailed) percentEl.style.color = 'var(--red)';
      else if (isAllSkipped) percentEl.style.color = '#2dd4bf';
      else if (isPartial) percentEl.style.color = 'var(--yellow)';
      else percentEl.style.color = 'var(--green)';
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
    if (isFinished) {
      if (isAllFailed) stageEl.textContent = '❌ แก้แคปช่าไม่สำเร็จ (พบข้อผิดพลาดที่ตัวไอดี)';
      else if (isAllSkipped) stageEl.textContent = '✨ ตรวจสอบเสร็จสิ้น (ไม่พบด่านแคปช่า)';
      else if (isPartial) stageEl.textContent = `⚠️ ดำเนินการเสร็จบางส่วน (${sCount} ผ่าน / ${fCount} ไม่ผ่าน)`;
      else stageEl.textContent = '🎉 ดำเนินการเสร็จสิ้นเรียบร้อยแล้ว!';
    } else {
      stageEl.textContent = matchedStage.text;
    }
  }

  if (hintEl) {
    if (isFinished) {
      if (isAllFailed) hintEl.innerHTML = '<span style="color:#f87171;font-weight:600;">⚠️ ไม่สามารถเข้าเกมได้ ตรวจสอบสาเหตุและวิธีแก้ไขด้านล่าง</span>';
      else if (isAllSkipped) hintEl.innerHTML = '<span style="color:#2dd4bf;font-weight:600;">ไอดีนี้ไม่มีแคปช่า หรือผ่านการแก้ไขแล้ว สามารถเข้าเล่นเกมได้ทันที</span>';
      else if (isPartial) hintEl.innerHTML = '<span style="color:#fbbf24;font-weight:600;">ไอดีที่ผ่านสามารถเข้าเกมได้ ส่วนไอดีที่ไม่ผ่านกรุณาดูสาเหตุในกล่องด้านล่าง</span>';
      else hintEl.innerHTML = '<span style="color:var(--green);font-weight:600;">สำเร็จ 100%! บัญชีพร้อมเข้าเล่นเกมได้ทันที</span>';
    } else {
      hintEl.textContent = matchedStage.hint;
    }
  }

  if (dot && isFinished) {
    if (isAllFailed) dot.className = 'pulse-dot failed';
    else if (isPartial) dot.className = 'pulse-dot warning';
    else dot.className = 'pulse-dot completed';
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
        completeFakeProgressBar(job);
        const finishEl = document.getElementById('custFinishMsg');
        finishEl.style.display = 'block';

        let sCount = Number(job.success_count ?? 0);
        let fCount = Number(job.fail_count ?? 0);
        let skCount = Number(job.skip_count ?? 0);

        if (job.accounts_detail && Array.isArray(job.accounts_detail) && job.accounts_detail.length > 0) {
          let accS = 0, accF = 0, accSk = 0;
          for (const a of job.accounts_detail) {
            const st = String(a.status || '').trim().toUpperCase();
            if (['COMPLETED', 'SUCCESS'].includes(st)) accS++;
            else if (['SKIP', 'SKIPPED', 'NO_CAPTCHA'].includes(st)) accSk++;
            else if (['FAILED', 'FAIL', 'ERROR', 'COOKIE_BROKEN', 'FACE_LOCK', 'FACELOCK', 'WRONG_PASSWORD', 'INVALID', 'INV', 'TWO_STEP', '2STEP', '2FA', 'BANNED', 'BAN'].includes(st)) accF++;
            else if (st && !['PENDING', 'PROCESSING', 'QUEUED'].includes(st)) accF++;
          }
          if (accS > 0 || accF > 0 || accSk > 0) {
            sCount = accS;
            fCount = accF;
            skCount = accSk;
          }
        }

        const isAllFailed = (fCount > 0 && sCount === 0 && skCount === 0) || (job.status === 'FAILED' && sCount === 0 && skCount === 0);
        const isAllSkipped = skCount > 0 && sCount === 0 && fCount === 0;
        const isPartial = sCount > 0 && fCount > 0;

        if (isAllFailed) {
          const failedAccounts = (job.accounts_detail || []).filter(a => {
            const st = String(a.status || '').trim().toUpperCase();
            return !['COMPLETED', 'SUCCESS', 'SKIP', 'SKIPPED', 'NO_CAPTCHA'].includes(st);
          });

          const failedItemsHtml = failedAccounts.length > 0
            ? failedAccounts.map(a => {
                const info = getAccountStatusInfo(a.status);
                return `
                  <div style="background:rgba(0,0,0,0.4);border:1px solid rgba(239,68,68,0.4);border-radius:10px;padding:12px;margin-bottom:10px;text-align:left;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
                      <span style="font-family:var(--font-mono);font-weight:700;color:#fff;font-size:14px;">👤 ${a.username}</span>
                      <span class="job-status-badge ${info.cls}" style="font-size:11px;padding:4px 10px;">${info.text}</span>
                    </div>
                    <div style="font-size:12px;color:#fecaca;line-height:1.6;margin-bottom:6px;">
                      ⚠️ <b>สาเหตุที่ไม่สำเร็จ:</b> ${info.desc}
                    </div>
                    <div style="font-size:12px;color:#fef08a;line-height:1.5;">
                      💡 <b>วิธีแก้ไข:</b> ${info.solution}
                    </div>
                  </div>
                `;
              }).join('')
            : `
              <div style="background:rgba(0,0,0,0.4);border:1px solid rgba(239,68,68,0.4);border-radius:10px;padding:12px;margin-bottom:10px;font-size:12px;color:#fecaca;line-height:1.6;text-align:left;">
                ⚠️ <b>สาเหตุ:</b> ตรวจพบสถานะ <b>Inv (Cookie แตก / เปลี่ยนรหัสผ่าน)</b> หรือปัญหาความปลอดภัยของไอดี ทำให้ระบบไม่สามารถเข้าสู่ระบบไปแก้แคปช่าได้<br>
                💡 <b>วิธีแก้ไข:</b> ต้องนำ Cookie ปัจจุบันมาอัปเดตใหม่ในระบบก่อนส่งแก้
              </div>
            `;

          finishEl.style.background = 'rgba(239, 68, 68, 0.12)';
          finishEl.style.borderColor = 'rgba(239, 68, 68, 0.55)';
          finishEl.style.color = '#f87171';
          finishEl.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;font-weight:700;font-size:16px;margin-bottom:10px;color:#f87171;">
              <span style="font-size:24px;line-height:1;">❌</span>
              <span>การแก้แคปช่าไม่สำเร็จ (ไม่สามารถเข้าเล่นเกมได้)</span>
            </div>
            
            <div style="font-size:13px;color:#fca5a5;line-height:1.6;margin-bottom:14px;text-align:left;">
              ระบบไม่สามารถดำเนินการแก้แคปช่าได้ เนื่องจากตรวจพบข้อผิดพลาดที่ตัวบัญชี Roblox ของท่าน
            </div>

            <div style="margin-bottom:12px;">
              <div style="font-size:11px;font-weight:700;color:#fca5a5;text-transform:uppercase;margin-bottom:8px;letter-spacing:0.5px;text-align:left;">
                📋 สาเหตุและวิธีแก้ไขของแต่ละไอดี:
              </div>
              ${failedItemsHtml}
            </div>

            <div style="background:rgba(239,68,68,0.18);border-left:4px solid #ef4444;padding:12px 14px;border-radius:6px;font-size:12px;color:#fecaca;line-height:1.6;margin-bottom:16px;text-align:left;">
              📌 <b>สรุปเหตุผล:</b> Cookie หมดอายุ / รหัสถูกเปลี่ยน (ติดต่อแอดมินส่ง ชื่อ:รหัส และรับการอัพเดทใหม่)
            </div>

            <button class="btn btn-secondary btn-sm" onclick="resetCustomerJobSection()" style="font-size:13px;cursor:pointer;padding:10px 20px;font-weight:600;">
              🔄 ตรวจสอบไอดีอื่น / ลองใหม่อีกครั้ง
            </button>
          `;
          showToast('แก้แคปช่าไม่สำเร็จ กรุณาดูสาเหตุและคำแนะนำด้านล่าง', 'error');
        } else if (isAllSkipped) {
          finishEl.style.background = 'rgba(20, 184, 166, 0.12)';
          finishEl.style.borderColor = 'rgba(45, 212, 191, 0.45)';
          finishEl.style.color = '#2dd4bf';
          finishEl.innerHTML = `
            <div style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:16px;margin-bottom:8px;color:#2dd4bf;">
              <span style="font-size:22px;">✨</span>
              <span>บัญชีนี้ไม่มีแคปช่าติดค้าง หรือผ่านการแก้ไขแล้ว!</span>
            </div>
            <div style="font-size:13px;opacity:0.95;line-height:1.6;margin-bottom:14px;text-align:left;">
              ระบบตรวจพบว่าบัญชีนี้เข้าเล่นเกมได้ตามปกติ ไม่พบด่านแคปช่า Roblox ที่ต้องแก้ สามารถเปิดเกมและเข้าเล่นแมพได้ทันทีครับ
            </div>
            <button class="btn btn-secondary btn-sm" onclick="resetCustomerJobSection()" style="font-size:12px;cursor:pointer;padding:8px 18px;">
              ✨ ตรวจสอบไอดีอื่น / เริ่มรายการใหม่
            </button>
          `;
          showToast('บัญชีนี้ไม่มีแคปช่า พร้อมเข้าเล่นได้ทันที', 'success');
        } else if (isPartial) {
          const failedAccounts = (job.accounts_detail || []).filter(a => {
            const st = String(a.status || '').trim().toUpperCase();
            return !['COMPLETED', 'SUCCESS', 'SKIP', 'SKIPPED', 'NO_CAPTCHA'].includes(st);
          });

          const failedItemsHtml = failedAccounts.map(a => {
            const info = getAccountStatusInfo(a.status);
            return `
              <div style="background:rgba(0,0,0,0.35);border:1px solid rgba(245,158,11,0.35);border-radius:8px;padding:10px;margin-bottom:8px;text-align:left;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                  <span style="font-family:var(--font-mono);font-weight:700;color:#fff;font-size:13px;">👤 ${a.username}</span>
                  <span class="job-status-badge ${info.cls}" style="font-size:11px;padding:2px 8px;">${info.text}</span>
                </div>
                <div style="font-size:11px;color:#fed7aa;line-height:1.4;">
                  ⚠️ <b>สาเหตุ:</b> ${info.desc}
                </div>
                <div style="font-size:11px;color:#fde68a;line-height:1.4;margin-top:2px;">
                  💡 <b>วิธีแก้:</b> ${info.solution}
                </div>
              </div>
            `;
          }).join('');

          finishEl.style.background = 'rgba(245, 158, 11, 0.12)';
          finishEl.style.borderColor = 'rgba(245, 158, 11, 0.45)';
          finishEl.style.color = '#fbbf24';
          finishEl.innerHTML = `
            <div style="font-weight:700;font-size:15px;margin-bottom:6px;color:#fbbf24;">
              ⚠️ แก้แคปช่าสำเร็จบางส่วน (${sCount} ผ่าน / ${fCount} ไม่ผ่าน)
            </div>
            <div style="font-size:12px;opacity:0.95;line-height:1.6;margin-bottom:12px;text-align:left;">
              ไอดีที่ผ่านสามารถเข้าเล่นเกมได้ทันที ส่วนไอดีที่ไม่ผ่านระบบไม่สามารถแก้ได้เนื่องจากปัญหาของตัวบัญชี
            </div>
            ${failedItemsHtml ? `<div style="margin-bottom:12px;">${failedItemsHtml}</div>` : ''}
            <button class="btn btn-secondary btn-sm" onclick="resetCustomerJobSection()" style="font-size:12px;cursor:pointer;padding:8px 18px;">
              ✨ เริ่มรายการใหม่
            </button>
          `;
          showToast(`แก้สำเร็จ ${sCount} ไอดี / ไม่ผ่าน ${fCount} ไอดี`, 'warning');
        } else if (sCount > 0) {
          finishEl.style.background = 'rgba(52, 211, 153, 0.1)';
          finishEl.style.borderColor = 'rgba(52, 211, 153, 0.35)';
          finishEl.style.color = 'var(--green)';
          finishEl.innerHTML = `
            <div style="font-weight:700;font-size:16px;margin-bottom:6px;color:var(--green);">🎉 การแก้แคปช่าเสร็จสิ้นเรียบร้อยแล้ว!</div>
            <div style="font-size:13px;opacity:0.9;line-height:1.6;margin-bottom:12px;">สามารถเข้าเกม Roblox ได้ทันที หากในอนาคตหลุดหรือติดแคปช่าอีก สามารถนำมากดส่งแก้ใหม่ได้ตลอดเวลา</div>
            <button class="btn btn-secondary btn-sm" onclick="resetCustomerJobSection()" style="font-size:12px;cursor:pointer;padding:8px 18px;">✨ ส่งแก้ไอดีอื่น / เริ่มรายการใหม่</button>
          `;
          showToast('แก้แคปช่าเสร็จสิ้นเรียบร้อยแล้ว!', 'success');
        } else {
          finishEl.style.background = 'rgba(248, 113, 113, 0.1)';
          finishEl.style.borderColor = 'rgba(248, 113, 113, 0.35)';
          finishEl.style.color = 'var(--red)';
          finishEl.innerHTML = `
            <div style="font-weight:700;font-size:15px;margin-bottom:6px;">❌ การประมวลผลล้มเหลว หรือถูกยกเลิก</div>
            <div style="font-size:12px;opacity:0.9;line-height:1.5;margin-bottom:12px;">เซิร์ฟเวอร์แจ้งว่าคำขอนี้ไม่สามารถดำเนินการได้ กรุณาตรวจสอบความถูกต้องของบัญชี หรือลองใหม่อีกครั้ง</div>
            <button class="btn btn-secondary btn-sm" onclick="resetCustomerJobSection()" style="font-size:12px;cursor:pointer;padding:8px 18px;">🔄 ลองใหม่อีกครั้ง</button>
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
  const st = String(status || 'PENDING').trim().toUpperCase();

  if (st === 'COMPLETED' || st === 'SUCCESS') {
    return {
      cls: 'status-COMPLETED',
      text: 'สำเร็จ ✅',
      title: 'แก้แคปช่าสำเร็จ',
      desc: 'แก้แคปช่าผ่านเรียบร้อย สามารถเข้าเล่นแมพได้ทันที',
      solution: 'สามารถเปิดเกม Roblox เข้าเล่นได้เลย'
    };
  }

  if (st === 'PROCESSING' || st === 'RUNNING' || st === 'SOLVING') {
    return {
      cls: 'status-PROCESSING',
      text: 'กำลังแก้... ⚡',
      title: 'กำลังแก้แคปช่า',
      desc: 'AI กำลังวิเคราะห์โจทย์รูปภาพและส่งคำตอบ',
      solution: 'กรุณารอสักครู่ ห้ามปิดหน้าต่างนี้'
    };
  }

  if (st === 'PENDING' || st === 'QUEUED' || st === 'WAITING') {
    return {
      cls: 'status-PENDING',
      text: 'รอคิว... ⏳',
      title: 'อยู่ในคิวรอเริ่ม',
      desc: 'อยู่ในคิวรอระบบเริ่มประมวลผลตามลำดับ',
      solution: 'ระบบจะเริ่มทำงานอัตโนมัติเมื่อถึงคิว'
    };
  }

    if (st === 'COOKIE_BROKEN' || st === 'INVALID' || st === 'INV' || st === 'WRONG_PASSWORD' || st === 'EXPIRED' || st === 'UNAUTHORIZED') {
    return {
      cls: 'status-COOKIE_BROKEN',
      text: 'Cookie หมดอายุ 🔑',
      title: 'Cookie หมดอายุ / รหัสถูกเปลี่ยน',
      desc: 'ติดต่อแอดมินส่ง ชื่อ:รหัส และรับการอัพเดทใหม่',
      solution: 'แอดมินจะทำการแก้ไขรหัสผ่านและคุกกี้ให้ใหม่'
    };
  }

  if (st === 'FACE_LOCK' || st === 'FACELOCK' || st === 'FACE') {
    return {
      cls: 'status-FACE_LOCK',
      text: 'ติดสแกนหน้า (Face Lock) 👤',
      title: 'ติดยืนยันตัวตนด้วยใบหน้า (Face Lock)',
      desc: 'บัญชีนี้ติดระบบยืนยันตัวตนสแกนใบหน้าของ Roblox ซึ่ง AI ภายนอกไม่สามารถผ่านด่านนี้แทนได้',
      solution: 'เจ้าของไอดีต้องล็อกอินผ่านมือถือหรือคอมเพื่อสแกนใบหน้าปลดล็อคด้วยตนเองก่อน'
    };
  }

  if (st === 'TWO_STEP' || st === '2STEP' || st === '2FA' || st === 'TWOSTEP') {
    return {
      cls: 'status-TWO_STEP',
      text: 'ติดรหัส 2 ชั้น (2-Step Verification) 📱',
      title: 'ติดรหัสยืนยัน 2 ขั้นตอน (2FA)',
      desc: 'บัญชีเปิดระบบความปลอดภัย 2 ชั้น (Authenticator / Email OTP) ทำให้ระบบภายนอกเข้าล็อกอินไม่ได้',
      solution: 'กรุณาเข้าไปปิด 2-Step Verification ในการตั้งค่า Roblox ชั่วคราวก่อนส่งแก้'
    };
  }

  if (st === 'BANNED' || st === 'BAN' || st === 'TERMINATED' || st === 'RESTRICTED') {
    return {
      cls: 'status-BANNED',
      text: 'บัญชีถูกแบน (Banned) ⛔',
      title: 'บัญชีถูกระงับการใช้งาน',
      desc: 'บัญชีนี้ถูกทาง Roblox สั่งระงับหรือแบนชั่วคราว/ถาวร ไม่สามารถเข้าใช้งานได้',
      solution: 'ไม่สามารถดำเนินการได้ กรุณาติดต่อฝ่ายสนับสนุนของ Roblox'
    };
  }

  if (st === 'RATE_LIMIT') {
    return {
      cls: 'status-PENDING',
      text: 'ติด Rate Limit ชั่วคราว ⏳',
      title: 'ติดจำกัดความถี่คำขอ',
      desc: 'มีการส่งคำขอแก้แคปช่าถี่เกินไป ระบบกำลังเว้นช่วงเพื่อความปลอดภัยและจะลองใหม่',
      solution: 'กรุณารอสักครู่ ระบบจะทำงานต่ออัตโนมัติ'
    };
  }

  if (st === 'SKIP' || st === 'SKIPPED' || st === 'NO_CAPTCHA') {
    return {
      cls: 'status-SKIP',
      text: 'ไม่ต้องแก้ (ไม่มีแคปช่า / ผ่านแล้ว) ✨',
      title: 'ไม่มีแคปช่าติดค้าง',
      desc: 'ระบบตรวจพบว่าบัญชีนี้ไม่มีด่านแคปช่าติดค้าง หรือผ่านการแก้ไขแล้ว สามารถเข้าเล่นเกมได้ทันที',
      solution: 'สามารถเปิดเกม Roblox และเข้าเล่นได้ทันที'
    };
  }

  if (st === 'FAILED' || st === 'FAIL' || st === 'ERROR') {
    return {
      cls: 'status-FAILED',
      text: 'แก้ไม่สำเร็จ ❌',
      title: 'AI ไม่สามารถแก้โจทย์ได้',
      desc: 'AI ทำการแก้โจทย์แคปช่าไม่สำเร็จ หรือระบบรักษาความปลอดภัยของ Roblox ปฏิเสธคำตอบ',
      solution: 'สามารถกดลองส่งใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบเพื่อตรวจสอบ'
    };
  }

  // Generic Fallback
  return {
    cls: 'status-FAILED',
    text: `${status} ❌`,
    title: `ข้อผิดพลาด: ${status}`,
    desc: 'เกิดข้อผิดพลาดในการตรวจสอบบัญชี หรือไม่ผ่านการตรวจสอบจากเซิร์ฟเวอร์',
    solution: 'กรุณาตรวจสอบความถูกต้องของบัญชี และลองใหม่อีกครั้ง'
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

// ============================================================
// SERVICE TAB SWITCHING
// ============================================================
function switchServiceTab(tab) {
  document.getElementById('paneCaptcha').classList.toggle('active', tab === 'captcha');
  document.getElementById('paneFace').classList.toggle('active', tab === 'face');
  document.getElementById('tabBtnCaptcha').classList.toggle('active', tab === 'captcha');
  document.getElementById('tabBtnFace').classList.toggle('active', tab === 'face');
}

// ============================================================
// MEMBER AUTH MODAL
// ============================================================
function openMemberModal(tab = 'login') {
  document.getElementById('modalMemberAuth').classList.add('active');
  switchModalTab(tab);
}

function closeMemberModal() {
  document.getElementById('modalMemberAuth').classList.remove('active');
}

function switchModalTab(tab) {
  document.getElementById('modalPaneLogin').classList.toggle('active', tab === 'login');
  document.getElementById('modalPaneRegister').classList.toggle('active', tab === 'register');
  document.getElementById('modalTabLogin').classList.toggle('active', tab === 'login');
  document.getElementById('modalTabRegister').classList.toggle('active', tab === 'register');
}

// Close modal on backdrop click
document.getElementById('modalMemberAuth')?.addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeMemberModal();
});

// ============================================================
// MEMBER STATE MANAGEMENT
// ============================================================
let memberState = { loggedIn: false, status: null, email: null, credits: 0 };

async function loadMyCredits() {
  try {
    const res = await fetch('/api.php?action=get_my_credits');
    const data = await res.json();
    if (data.success) {
      memberState.credits = data.credits;
      document.getElementById('memberCreditsDisplay').textContent = memberState.credits;
      document.getElementById('topupCreditsDisplay').textContent = memberState.credits;
      
      const badge = document.getElementById('memberCreditsBadge');
      if (memberState.credits > 0) {
        badge.classList.remove('low');
      } else {
        badge.classList.add('low');
      }
      updateFaceCreditsNeeded();
    }
  } catch (e) {}
}

let currentFaceCost = 1;

async function loadSettings() {
  try {
    const res = await fetch('/api.php?action=get_site_settings');
    const data = await res.json();
    if (data.success && data.data) {
      currentFaceCost = parseInt(data.data.face_scan_cost || 1);
      const costEl = document.getElementById('faceScanCostDisplay');
      if (costEl) costEl.textContent = currentFaceCost;
      updateFaceCreditsNeeded();
    }
  } catch (e) {}
}

function updateFaceUI() {
  const { loggedIn, status, email } = memberState;
  const authBox = document.getElementById('faceAuthBox');
  const pendingBox = document.getElementById('facePendingBox');
  const rejectedBox = document.getElementById('faceRejectedBox');
  const mainArea = document.getElementById('faceMainArea');

  authBox.style.display = 'none';
  pendingBox.style.display = 'none';
  rejectedBox.style.display = 'none';
  mainArea.style.display = 'none';

  if (!loggedIn) {
    authBox.style.display = 'block';
  } else if (status === 'approved') {
    mainArea.style.display = 'block';
    const emailEl = document.getElementById('memberEmailDisplay');
    if (emailEl) emailEl.textContent = email || '';
    loadMyCredits();
    loadSettings();
  } else if (status === 'rejected') {
    rejectedBox.style.display = 'block';
  } else {
    // pending
    pendingBox.style.display = 'block';
  }
}

// ── FACE SUB-TABS ──
window.switchFaceSubTab = function(tabId) {
  document.querySelectorAll('.face-subtab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.face-subpane').forEach(p => p.classList.remove('active'));
  
  if (tabId === 'scan') {
    document.getElementById('faceSubBtnScan').classList.add('active');
    document.getElementById('faceSubPaneScan').classList.add('active');
  } else if (tabId === 'topup') {
    document.getElementById('faceSubBtnTopup').classList.add('active');
    document.getElementById('faceSubPaneTopup').classList.add('active');
    loadMyCredits();
  }
};

async function checkMemberSession() {
  try {
    const res = await fetch('/api.php?action=member_check');
    const data = await res.json();
    if (data.logged_in) {
      memberState = { loggedIn: true, status: data.status, email: data.email };
    } else {
      memberState = { loggedIn: false, status: null, email: null };
    }
  } catch (e) {
    memberState = { loggedIn: false, status: null, email: null };
  }
  updateFaceUI();
}

async function memberLogout() {
  await fetch('/api.php?action=member_logout', { method: 'POST' });
  memberState = { loggedIn: false, status: null, email: null };
  updateFaceUI();
  showToast('ออกจากระบบสมาชิกแล้ว', 'info');
}

// Logout buttons
document.getElementById('btnMemberLogout')?.addEventListener('click', memberLogout);
document.getElementById('btnMemberLogout2')?.addEventListener('click', memberLogout);
document.getElementById('btnMemberLogout3')?.addEventListener('click', memberLogout);

// ============================================================
// MEMBER REGISTER
// ============================================================
document.getElementById('formMemberRegister')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = document.getElementById('regEmail').value.trim();
  const password = document.getElementById('regPassword').value;
  const confirm = document.getElementById('regPasswordConfirm').value;

  if (password !== confirm) return showToast('รหัสผ่านไม่ตรงกัน', 'error');

  const btn = document.getElementById('btnRegisterSubmit');
  btn.disabled = true;
  btn.textContent = '⏳ กำลังสมัคร...';

  try {
    const res = await fetch('/api.php?action=member_register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
    const data = await res.json();
    if (data.success) {
      closeMemberModal();
      showToast(data.message, 'success');
      // auto login after register
      const loginRes = await fetch('/api.php?action=member_login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      const loginData = await loginRes.json();
      if (loginData.success) {
        memberState = { loggedIn: true, status: loginData.status, email: loginData.email };
        updateFaceUI();
      }
    } else {
      showToast(data.error || 'สมัครสมาชิกไม่สำเร็จ', 'error');
    }
  } catch (err) {
    showToast('เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
  }

  btn.disabled = false;
  btn.textContent = '📝 สมัครสมาชิก';
});

// ============================================================
// MEMBER LOGIN
// ============================================================

document.getElementById('formMemberLogin')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;

  const btn = document.getElementById('btnLoginSubmit');
  btn.disabled = true;
  btn.textContent = '⏳ กำลังเข้าสู่ระบบ...';

  try {
    const res = await fetch('/api.php?action=member_login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
    const data = await res.json();
    if (data.success) {
      memberState = { loggedIn: true, status: data.status, email: data.email };
      closeMemberModal();
      updateFaceUI();
      showToast('เข้าสู่ระบบสำเร็จ!', 'success');
    } else {
      showToast(data.error || 'อีเมลหรือรหัสผ่านไม่ถูกต้อง', 'error');
    }
  } catch (err) {
    showToast('เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
  }

  btn.disabled = false;
  btn.textContent = '🔑 เข้าสู่ระบบ';
});

// ============================================================
// FACE UNLOCK SUBMIT & POLL
// ============================================================
let faceJobId = null;
let facePollTimer = null;

window.updateFaceCreditsNeeded = function() {
  const text = document.getElementById('faceUsernames')?.value.trim() || '';
  const lines = text ? text.split(/\r\n|\n|\r/).map(l => l.trim()).filter(l => l) : [];
  const count = lines.length;
  
  const countBadge = document.getElementById('faceCountBadge');
  const creditsBadge = document.getElementById('faceCreditsNeeded');
  
  if (countBadge) countBadge.textContent = count;
  if (creditsBadge) {
    const needed = count * currentFaceCost;
    creditsBadge.textContent = needed;
    creditsBadge.style.color = needed > (memberState.credits || 0) ? '#ef4444' : '#34d399';
  }
};

document.getElementById('faceUsernames')?.addEventListener('input', updateFaceCreditsNeeded);

document.getElementById('formFaceSubmit')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const text = document.getElementById('faceUsernames').value.trim();
  if (!text) return showToast('กรุณาระบุชื่อตัวละครอย่างน้อย 1 ชื่อ', 'error');

  const usernames = text.split(/\r\n|\n|\r/).map(l => l.trim()).filter(l => l);
  if (!usernames.length) return;

  const btn = document.getElementById('btnFaceSubmit');
  btn.disabled = true;
  btn.innerHTML = '<span>⏳</span><span>กำลังส่งงานสแกนหน้า...</span>';

  try {
    const res = await fetch('/api.php?action=face_submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ usernames })
    });
    const data = await res.json();
    btn.disabled = false;
    btn.innerHTML = '<span>😊</span><span>เริ่มสแกนหน้าทันที (Face Unlock)</span>';

    if (res.ok && data.success) {
      if (data.data.not_found && data.data.not_found.length > 0) {
        showToast(`⚠️ ไม่พบในระบบ: ${data.data.not_found.join(', ')}`, 'error');
      }
      showToast('ส่งงานสแกนหน้าสำเร็จ! กำลังประมวลผล...', 'success');
      
      // Update credits immediately
      if (data.data.credits_remaining !== undefined) {
        memberState.credits = data.data.credits_remaining;
        document.getElementById('memberCreditsDisplay').textContent = memberState.credits;
        document.getElementById('topupCreditsDisplay').textContent = memberState.credits;
        updateFaceCreditsNeeded();
      }

      startFaceTracking(data.data.job_id, data.data.total_accounts);
    } else {
      showToast(data.error || 'ส่งงานไม่สำเร็จ', 'error');
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<span>😊</span><span>เริ่มสแกนหน้าทันที (Face Unlock)</span>';
    showToast('เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
  }
});

// ============================================================
// TOPUP TRUEWALLET VOUCHER
// ============================================================
document.getElementById('formTopupVoucher')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const link = document.getElementById('voucherLink').value.trim();
  if (!link) return;

  const btn = document.getElementById('btnTopupSubmit');
  const resMsg = document.getElementById('topupResultMsg');
  
  btn.disabled = true;
  btn.innerHTML = '<span>⏳</span><span>กำลังดำเนินการ...</span>';
  resMsg.style.display = 'none';

  try {
    const res = await fetch('/api.php?action=topup_voucher', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ link })
    });
    const data = await res.json();
    
    resMsg.style.display = 'block';
    if (res.ok && data.success) {
      resMsg.style.background = 'rgba(52,211,153,0.1)';
      resMsg.style.border = '1px solid rgba(52,211,153,0.3)';
      resMsg.style.color = '#34d399';
      resMsg.textContent = data.message;
      document.getElementById('voucherLink').value = '';
      showToast('เติมเครดิตสำเร็จ!', 'success');
      loadMyCredits();
    } else {
      resMsg.style.background = 'rgba(239,68,68,0.1)';
      resMsg.style.border = '1px solid rgba(239,68,68,0.3)';
      resMsg.style.color = '#ef4444';
      resMsg.textContent = data.error || 'การเติมเครดิตล้มเหลว';
    }
  } catch (err) {
    resMsg.style.display = 'block';
    resMsg.style.background = 'rgba(239,68,68,0.1)';
    resMsg.style.border = '1px solid rgba(239,68,68,0.3)';
    resMsg.style.color = '#ef4444';
    resMsg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่';
  }

  btn.disabled = false;
  btn.innerHTML = '<span>💳</span><span>เติมเครดิตด้วยซอง</span>';
});

// ============================================================
// TOPUP PROMPTPAY (QR)
// ============================================================

window.switchTopupMethod = function(method) {
  const formTw = document.getElementById('formTopupVoucher');
  const formQr = document.getElementById('formTopupQr');
  const btnTw = document.getElementById('btnMethodTw');
  const btnQr = document.getElementById('btnMethodQr');
  const qrDisplayArea = document.getElementById('qrDisplayArea');
  const resMsg = document.getElementById('topupResultMsg');
  
  resMsg.style.display = 'none';

  if (method === 'tw') {
    formTw.style.display = 'block';
    formQr.style.display = 'none';
    qrDisplayArea.style.display = 'none';
    btnTw.style.background = 'linear-gradient(135deg,#d97706,#f59e0b)';
    btnTw.classList.replace('btn-secondary', 'btn-primary');
    btnQr.style.background = '';
    btnQr.classList.replace('btn-primary', 'btn-secondary');
  } else {
    formTw.style.display = 'none';
    formQr.style.display = 'block';
    btnQr.style.background = 'linear-gradient(135deg,#0284c7,#38bdf8)';
    btnQr.classList.replace('btn-secondary', 'btn-primary');
    btnTw.style.background = '';
    btnTw.classList.replace('btn-primary', 'btn-secondary');
  }
};

let qrPollingInterval = null;

document.getElementById('formTopupQr')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const amount = parseFloat(document.getElementById('qrAmount').value);
  if (isNaN(amount) || amount <= 0) return;

  const btn = document.getElementById('btnQrGenerate');
  const resMsg = document.getElementById('topupResultMsg');
  const qrDisplay = document.getElementById('qrDisplayArea');
  const qrImg = document.getElementById('qrImage');
  const qrAmtDisplay = document.getElementById('qrAmountDisplay');
  const qrLoading = document.getElementById('qrLoading');
  
  btn.disabled = true;
  btn.innerHTML = '<span>⏳</span><span>กำลังสร้าง...</span>';
  resMsg.style.display = 'none';
  qrDisplay.style.display = 'none';

  // Stop old polling if exists
  if (qrPollingInterval) clearInterval(qrPollingInterval);

  try {
    const res = await fetch('/api.php?action=promptpay_generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount })
    });
    const data = await res.json();
    
    if (res.ok && data.success) {
      qrImg.src = data.qr_url;
      qrAmtDisplay.textContent = amount;
      qrDisplay.style.display = 'block';
      qrLoading.innerHTML = '⏳ กำลังรอการชำระเงิน...';
      
      const txId = data.transactionId;
      
      // Start polling
      qrPollingInterval = setInterval(async () => {
        try {
          const chk = await fetch('/api.php?action=promptpay_check', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transactionId: txId })
          });
          const chkData = await chk.json();
          if (chkData.success && chkData.status === 'success') {
            clearInterval(qrPollingInterval);
            qrDisplay.style.display = 'none';
            resMsg.style.display = 'block';
            resMsg.style.background = 'rgba(52,211,153,0.1)';
            resMsg.style.border = '1px solid rgba(52,211,153,0.3)';
            resMsg.style.color = '#34d399';
            resMsg.textContent = chkData.message || 'ชำระเงินสำเร็จ!';
            showToast('เติมเครดิตสำเร็จ!', 'success');
            loadMyCredits();
            document.getElementById('qrAmount').value = '';
          }
        } catch(e) {}
      }, 5000); // Check every 5 seconds

    } else {
      resMsg.style.display = 'block';
      resMsg.style.background = 'rgba(239,68,68,0.1)';
      resMsg.style.border = '1px solid rgba(239,68,68,0.3)';
      resMsg.style.color = '#ef4444';
      resMsg.textContent = data.error || 'ไม่สามารถสร้าง QR Code ได้';
    }
  } catch (err) {
    resMsg.style.display = 'block';
    resMsg.style.background = 'rgba(239,68,68,0.1)';
    resMsg.style.border = '1px solid rgba(239,68,68,0.3)';
    resMsg.style.color = '#ef4444';
    resMsg.textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
  }

  btn.disabled = false;
  btn.innerHTML = '<span>📱</span><span>สร้าง QR Code</span>';
});



function startFaceTracking(jobId, totalAccounts) {
  faceJobId = jobId;
  if (facePollTimer) clearInterval(facePollTimer);

  const section = document.getElementById('faceJobSection');
  const idEl = document.getElementById('faceJobId');
  const totalEl = document.getElementById('faceTotal');
  const finishEl = document.getElementById('faceFinishMsg');


  if (section) section.style.display = 'block';
  if (idEl) idEl.textContent = jobId;
  if (totalEl) totalEl.textContent = totalAccounts || '?';
  if (finishEl) finishEl.style.display = 'none';
  const fakeLoader = document.getElementById('faceFakeLoading');
  if (fakeLoader) fakeLoader.style.display = 'block';

  section?.scrollIntoView({ behavior: 'smooth', block: 'start' });

  pollFaceStatus();
  facePollTimer = setInterval(pollFaceStatus, 4000);
}

async function pollFaceStatus() {
  if (!faceJobId) return;
  try {
    const res = await fetch(`/api.php?action=face_status&job_id=${encodeURIComponent(faceJobId)}`);
    const data = await res.json();
    if (!data.success) return;
    updateFaceStatusUI(data.data);
  } catch (e) { /* ignore */ }
}

function updateFaceStatusUI(job) {
  const status = (job.status || '').toLowerCase();
  const badgeEl = document.getElementById('faceJobStatusBadge');
  const successEl = document.getElementById('faceSuccess');
  const failedEl = document.getElementById('faceFailed');
  const totalEl = document.getElementById('faceTotal');
  const logWrapper = document.getElementById('faceLogWrapper');
  const logTable = document.getElementById('faceLogTable');
  const finishEl = document.getElementById('faceFinishMsg');

  // Badge text
  const statusText = { pending: '⏳ รอคิว', processing: '🔄 กำลังประมวลผล', completed: '✅ เสร็จสิ้น', failed: '❌ ล้มเหลว', cancelled: '🚫 ยกเลิก' };
  if (badgeEl) {
    badgeEl.textContent = statusText[status] || status.toUpperCase();
    badgeEl.className = `job-status-badge status-${status === 'completed' ? 'COMPLETED' : status === 'processing' ? 'PROCESSING' : 'PENDING'}`;
  }

  if (totalEl && job.total_accounts) totalEl.textContent = job.total_accounts;
  if (successEl) successEl.textContent = job.successful || 0;
  if (failedEl) failedEl.textContent = (job.failed || 0) + (job.other_failed || 0);

  // Fake Loading screen
  const fakeLoader = document.getElementById('faceFakeLoading');
  if (fakeLoader) {
    if (status === 'processing' || status === 'pending') {
      fakeLoader.style.display = 'block';
    } else {
      fakeLoader.style.display = 'none';
    }
  }

  // Done
  if (status === 'completed' || status === 'failed' || status === 'cancelled') {
    if (facePollTimer) clearInterval(facePollTimer);
    if (finishEl) {
      finishEl.style.display = 'block';
      const sCount = job.successful || 0;
      const fCount = (job.failed || 0) + (job.other_failed || 0);
      if (status === 'completed' && sCount > 0 && fCount === 0) {
        finishEl.style.background = 'rgba(52,211,153,0.1)';
        finishEl.style.border = '1px solid rgba(52,211,153,0.3)';
        finishEl.style.color = '#34d399';
        finishEl.innerHTML = `🎉 สแกนหน้าสำเร็จ ${sCount} ไอดี! เข้าเกมได้เลย!`;
      } else if (fCount > 0 && sCount === 0) {
        finishEl.style.background = 'rgba(239,68,68,0.1)';
        finishEl.style.border = '1px solid rgba(239,68,68,0.3)';
        finishEl.style.color = '#ef4444';
        finishEl.innerHTML = `❌ ไม่สำเร็จ ${fCount} ไอดี — กรุณาตรวจสอบ Cookie และลองใหม่`;
      } else {
        finishEl.style.background = 'rgba(250,204,21,0.1)';
        finishEl.style.border = '1px solid rgba(250,204,21,0.3)';
        finishEl.style.color = '#facc15';
        finishEl.innerHTML = `⚡ เสร็จสิ้น: สำเร็จ ${sCount} ไม่สำเร็จ ${fCount} ไอดี`;
      }
    }
  }
}

// ============================================================
// INIT — check member session on page load
// ============================================================
checkMemberSession();
