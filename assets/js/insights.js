(function () {
  const DEFAULT = "ชี้ที่กราฟหรือแถวในตาราง เพื่อดูคำอธิบาย กด 7 / 14 / 30 วัน เพื่อเปลี่ยนช่วงสถิติ";
  const coachText = document.getElementById("coach-text");
  const data = window.SCIGENIE_INSIGHTS || { summary: {}, rows: [] };
  const gold = "#d4a017";
  const yellow = "#f4c430";
  const brown = "#5c4314";

  function setCoach(text) {
    coachText.textContent = text || DEFAULT;
  }
  document.addEventListener("mouseover", (e) => {
    const tip = e.target.closest("[data-tip]");
    if (tip) setCoach(tip.dataset.tip);
  });
  document.addEventListener("mouseout", (e) => {
    if (!e.relatedTarget || !e.relatedTarget.closest("[data-tip]")) setCoach(DEFAULT);
  });

  document.getElementById("log-body").addEventListener("click", (e) => {
    const tr = e.target.closest("tr");
    if (!tr) return;
    document.querySelectorAll(".log-row.is-open").forEach((row) => {
      if (row !== tr) row.classList.remove("is-open");
    });
    tr.classList.toggle("is-open");
    setCoach(tr.classList.contains("is-open")
      ? "กำลังดูคำตอบเต็มของครั้งนี้ กดแถวอีกครั้งเพื่อย่อ"
      : DEFAULT);
  });

  if (typeof Chart === "undefined") {
    document.querySelectorAll(".chart-box").forEach((el) => {
      el.innerHTML = '<p class="hint">โหลดกราฟไม่สำเร็จ ตรวจสอบการเชื่อมต่ออินเทอร์เน็ตแล้วรีเฟรชหน้านี้</p>';
    });
    return;
  }
  Chart.defaults.font.family = "Prompt, Sarabun, sans-serif";
  Chart.defaults.color = brown;

  const dayChart = new Chart(document.getElementById("chart-day"), {
    type: "bar",
    data: {
      labels: data.summary.by_day?.labels || [],
      datasets: [{
        label: "จำนวนครั้งที่ถาม",
        data: data.summary.by_day?.values || [],
        backgroundColor: yellow,
        borderRadius: 8,
        maxBarThickness: 28
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.parsed.y} ครั้ง`
          }
        }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "rgba(184,134,11,.15)" } },
        x: { grid: { display: false } }
      }
    }
  });

  const hourChart = new Chart(document.getElementById("chart-hour"), {
    type: "bar",
    data: {
      labels: data.summary.by_hour?.labels || [],
      datasets: [{
        label: "จำนวนครั้งที่ถาม",
        data: data.summary.by_hour?.values || [],
        backgroundColor: gold,
        borderRadius: 6,
        maxBarThickness: 18
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (items) => items[0]?.label ? `ช่วง ${items[0].label} น.` : "",
            label: (ctx) => ` ${ctx.parsed.y} ครั้ง`
          }
        }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "rgba(184,134,11,.15)" } },
        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } }
      }
    }
  });

  const topChart = new Chart(document.getElementById("chart-top"), {
    type: "bar",
    data: {
      labels: (data.summary.top || []).map((x) => trimLabel(x.label)),
      datasets: [{
        label: "ครั้ง",
        data: (data.summary.top || []).map((x) => x.count),
        backgroundColor: ["#f4c430", "#e0a800", "#c9920a", "#b8860b", "#9a6f09", "#7a5a12", "#5c4314", "#3d2e0f", "#d4a017", "#ffd54a"],
        borderRadius: 8,
        maxBarThickness: 22
      }]
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (items) => {
              const i = items[0]?.dataIndex ?? 0;
              return data.summary.top?.[i]?.label || "";
            },
            label: (ctx) => ` ถูกถาม ${ctx.parsed.x} ครั้ง`
          }
        }
      },
      scales: {
        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "rgba(184,134,11,.15)" } },
        y: { grid: { display: false } }
      }
    }
  });

  function trimLabel(s) {
    s = String(s || "");
    return s.length > 36 ? s.slice(0, 34) + "…" : s;
  }

  document.querySelectorAll(".range").forEach((btn) => {
    btn.classList.toggle("is-on", btn.dataset.days === "14");
    btn.addEventListener("click", async () => {
      document.querySelectorAll(".range").forEach((b) => b.classList.remove("is-on"));
      btn.classList.add("is-on");
      const days = btn.dataset.days;
      document.getElementById("range-hint").textContent = `กำลังโหลดสถิติ ${days} วันล่าสุด...`;
      setCoach(`กำลังเปลี่ยนมุมมองเป็น ${days} วันล่าสุด`);
      try {
        const res = await fetch(`../api/insights.php?days=${days}`);
        const json = await res.json();
        if (!json.ok) return;
        data.summary = json.summary;
        if (json.rows) data.rows = json.rows;
        applySummary(json.summary);
        renderLog(Number(days));
        document.getElementById("range-hint").textContent = `กำลังแสดง ${days} วันล่าสุด เลื่อนลงเพื่อดูกราฟและประวัติรายคน`;
        setCoach(`อัปเดตแล้ว นี่คือภาพรวม ${days} วันล่าสุด`);
      } catch (e) {
        document.getElementById("range-hint").textContent = "โหลดสถิติไม่สำเร็จ ลองกดอีกครั้ง";
      }
    });
  });

  function applySummary(s) {
    document.getElementById("kpi-total").textContent = s.total;
    document.getElementById("kpi-visitors").textContent = s.unique_visitors;
    document.getElementById("kpi-rate").textContent = s.match_rate + "%";
    document.getElementById("kpi-peak").textContent = String(s.peak_hour).padStart(2, "0") + ":00";
    dayChart.data.labels = s.by_day.labels;
    dayChart.data.datasets[0].data = s.by_day.values;
    dayChart.update();
    hourChart.data.labels = s.by_hour.labels;
    hourChart.data.datasets[0].data = s.by_hour.values;
    hourChart.update();
    topChart.data.labels = (s.top || []).map((x) => trimLabel(x.label));
    topChart.data.datasets[0].data = (s.top || []).map((x) => x.count);
    topChart.update();
    const list = document.getElementById("top-list");
    list.innerHTML = (s.top || []).map((item) =>
      `<li><span class="rank">${item.rank}</span><span class="label">${escapeHtml(item.label)}</span><span class="count">${item.count} ครั้ง</span></li>`
    ).join("") || '<li class="empty">ยังไม่มีคำถามในช่วงนี้</li>';
  }

  function sinceDays(days) {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() - Math.max(1, days));
    return d.getTime();
  }

  function thaiWhen(iso) {
    const t = new Date(iso);
    if (Number.isNaN(t.getTime())) return iso || "-";
    const months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    const hh = String(t.getHours()).padStart(2, "0");
    const mm = String(t.getMinutes()).padStart(2, "0");
    return `${t.getDate()} ${months[t.getMonth()]} ${t.getFullYear() + 543} เวลา ${hh}:${mm} น.`;
  }

  function renderLog(days) {
    const since = sinceDays(days);
    const rows = (data.rows || []).filter((row) => {
      const ts = new Date(row.time || "").getTime();
      return Number.isFinite(ts) && ts >= since;
    });
    const body = document.getElementById("log-body");
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="6" class="hint">ยังไม่มีประวัติการถามในช่วงนี้ ลองเปิดวิดเจ็ตแล้วถามคำถามดูค่ะ</td></tr>';
      return;
    }
    body.innerHTML = rows.slice(0, 80).map((row) => `
      <tr class="faq-row log-row" data-tip="คลิกเพื่อขยายดูคำตอบเต็มของครั้งนี้">
        <td>${escapeHtml(thaiWhen(row.time || ""))}</td>
        <td><span class="tag">ผู้ใช้ ${escapeHtml(String(row.visitor || "-"))}</span></td>
        <td>${escapeHtml(String(row.category || "-"))}</td>
        <td><div class="cell-q">${escapeHtml(String(row.question || ""))}</div></td>
        <td><div class="cell-a">${escapeHtml(String(row.answer || ""))}</div></td>
        <td>${row.matched ? '<span class="ok-dot">ตอบได้</span>' : '<span class="no-dot">ไม่พบ</span>'}</td>
      </tr>
    `).join("");
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  }
})();
