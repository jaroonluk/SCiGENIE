(function () {
  const DEFAULT = "เริ่มที่แท็บ «ไม่พบคำตอบ» เพื่อดูคำถามที่ควรถูกเพิ่มในคลัง";
  const coachText = document.getElementById("coach-text");
  const data = window.SCIGENIE_INSIGHTS || { summary: {}, rows: [] };
  const gold = "#d4a017";
  const yellow = "#f4c430";
  const brown = "#5c4314";
  let days = 14;
  let tab = "unmatched";

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

  document.querySelectorAll("[data-tab-jump]").forEach((el) => {
    el.style.cursor = "pointer";
    el.addEventListener("click", () => switchTab(el.dataset.tabJump));
  });

  document.getElementById("log-body").addEventListener("click", (e) => {
    const tr = e.target.closest("tr");
    if (!tr || !tr.classList.contains("log-row")) return;
    document.querySelectorAll(".log-row.is-open").forEach((row) => {
      if (row !== tr) row.classList.remove("is-open");
    });
    tr.classList.toggle("is-open");
  });

  document.querySelectorAll(".review-tab").forEach((btn) => {
    btn.addEventListener("click", () => switchTab(btn.dataset.tab));
  });

  function switchTab(name) {
    tab = name;
    document.querySelectorAll(".review-tab").forEach((b) => b.classList.toggle("is-on", b.dataset.tab === name));
    document.querySelectorAll(".review-panel").forEach((p) => {
      p.hidden = p.dataset.panel !== name;
    });
    setCoach(name === "unmatched"
      ? "นี่คือคำถามที่ระบบยังตอบไม่ได้ เรียงจากถูกถามบ่อยสุด"
      : name === "matched"
        ? "นี่คือคำถามที่ระบบตอบได้แล้ว ใช้ตรวจว่าจับ FAQ ตรงเรื่องหรือไม่"
        : "ประวัติรายครั้ง กดแถวเพื่อดูคำตอบเต็ม");
  }

  if (typeof Chart === "undefined") {
    document.querySelectorAll(".chart-box").forEach((el) => {
      el.innerHTML = '<p class="hint">โหลดกราฟไม่สำเร็จ ตรวจสอบการเชื่อมต่ออินเทอร์เน็ตแล้วรีเฟรชหน้านี้</p>';
    });
  }

  const chartsReady = typeof Chart !== "undefined";
  if (chartsReady) {
    Chart.defaults.font.family = "Prompt, Sarabun, sans-serif";
    Chart.defaults.color = brown;
  }

  const dayChart = chartsReady ? new Chart(document.getElementById("chart-day"), {
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
    options: barOptions("y")
  }) : null;

  const hourChart = chartsReady ? new Chart(document.getElementById("chart-hour"), {
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
    options: barOptions("y", true)
  }) : null;

  function barOptions(axis, hour) {
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: hour ? (items) => items[0]?.label ? `ช่วง ${items[0].label} น.` : "" : undefined,
            label: (ctx) => ` ${ctx.parsed[axis]} ครั้ง`
          }
        }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "rgba(184,134,11,.15)" } },
        x: { grid: { display: false }, ticks: hour ? { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } : {} }
      }
    };
  }

  document.querySelectorAll(".range").forEach((btn) => {
    btn.classList.toggle("is-on", btn.dataset.days === "14");
    btn.addEventListener("click", async () => {
      document.querySelectorAll(".range").forEach((b) => b.classList.remove("is-on"));
      btn.classList.add("is-on");
      days = Number(btn.dataset.days);
      document.getElementById("range-hint").textContent = `กำลังโหลดสถิติ ${days} วันล่าสุด...`;
      try {
        const res = await fetch(`../api/insights.php?days=${days}`);
        const json = await res.json();
        if (!json.ok) return;
        data.summary = json.summary;
        if (json.rows) data.rows = json.rows;
        applySummary(json.summary);
        renderAll();
        document.getElementById("range-hint").textContent = `กำลังแสดง ${days} วันล่าสุด จากคำถามจริง`;
      } catch (e) {
        document.getElementById("range-hint").textContent = "โหลดสถิติไม่สำเร็จ ลองกดอีกครั้ง";
      }
    });
  });

  const filterCat = document.getElementById("filter-cat");
  const filterQ = document.getElementById("filter-q");
  filterCat.addEventListener("change", renderAll);
  filterQ.addEventListener("input", renderAll);

  function applySummary(s) {
    document.getElementById("kpi-total").textContent = s.total;
    document.getElementById("kpi-matched").textContent = s.matched;
    document.getElementById("kpi-unmatched").textContent = s.unmatched;
    document.getElementById("kpi-rate").textContent = s.match_rate + "%";
    document.getElementById("tab-unmatched").textContent = s.unmatched;
    document.getElementById("tab-matched").textContent = s.matched;
    if (dayChart) {
      dayChart.data.labels = s.by_day.labels;
      dayChart.data.datasets[0].data = s.by_day.values;
      dayChart.update();
    }
    if (hourChart) {
      hourChart.data.labels = s.by_hour.labels;
      hourChart.data.datasets[0].data = s.by_hour.values;
      hourChart.update();
    }
  }

  function sinceDays(n) {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() - Math.max(1, n));
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

  function visibleRows() {
    const cat = (filterCat.value || "").trim();
    const q = (filterQ.value || "").trim().toLowerCase();
    const since = sinceDays(days);
    return (data.rows || []).filter((row) => {
      const ts = new Date(row.time || "").getTime();
      if (!Number.isFinite(ts) || ts < since) return false;
      if (cat && String(row.category || "") !== cat) return false;
      if (!q) return true;
      const hay = `${row.question || ""} ${row.faq || ""} ${row.answer || ""} ${row.category || ""}`.toLowerCase();
      return hay.includes(q);
    });
  }

  function groupRows(list, matched) {
    const buckets = {};
    list.forEach((row) => {
      if (!!row.matched !== matched) return;
      const question = String(row.question || "").trim();
      const faq = String(row.faq || "").trim();
      const label = matched ? (faq || question) : question;
      const key = label.toLowerCase().replace(/\s+/g, " ");
      if (!key) return;
      if (!buckets[key]) {
        buckets[key] = {
          question: label,
          category: row.category || "",
          count: 0,
          last_time: row.time || "",
          samples: []
        };
      }
      buckets[key].count += 1;
      if (String(row.time || "") >= String(buckets[key].last_time || "")) {
        buckets[key].last_time = row.time || "";
        buckets[key].question = label;
        buckets[key].category = row.category || "";
      }
      if (question && buckets[key].samples.length < 4 && !buckets[key].samples.includes(question)) {
        buckets[key].samples.push(question);
      }
    });
    return Object.values(buckets).sort((a, b) => b.count - a.count);
  }

  function addUrl(category, question) {
    return "index.php?add=1&c=" + encodeURIComponent(category || "") + "&q=" + encodeURIComponent(question || "");
  }

  function renderAll() {
    const rows = visibleRows();
    const unmatched = groupRows(rows, false);
    const matched = groupRows(rows, true);
    const matchedN = rows.filter((r) => r.matched).length;
    const unmatchedN = rows.length - matchedN;
    document.getElementById("kpi-total").textContent = rows.length;
    document.getElementById("kpi-matched").textContent = matchedN;
    document.getElementById("kpi-unmatched").textContent = unmatchedN;
    document.getElementById("kpi-rate").textContent = (rows.length ? Math.round(matchedN * 100 / rows.length) : 0) + "%";
    document.getElementById("tab-unmatched").textContent = unmatchedN;
    document.getElementById("tab-matched").textContent = matchedN;

    const um = document.getElementById("unmatched-body");
    um.innerHTML = unmatched.length ? unmatched.map((item) => `
      <tr class="faq-row">
        <td><span class="count-pill">${item.count}</span></td>
        <td>${escapeHtml(item.category || "-")}</td>
        <td>
          <div class="cell-q">${escapeHtml(item.question || "")}</div>
          ${item.samples && item.samples.length && item.samples[0] !== item.question
            ? `<div class="cell-samples">เช่น ${escapeHtml(item.samples.slice(0, 2).join(" · "))}</div>` : ""}
        </td>
        <td>${escapeHtml(thaiWhen(item.last_time || ""))}</td>
        <td><a class="btn" href="${addUrl(item.category, item.question)}">เพิ่มคำตอบ</a></td>
      </tr>
    `).join("") : '<tr><td colspan="5" class="hint">ยังไม่มีคำถามที่ไม่พบคำตอบในช่วงนี้</td></tr>';

    const mb = document.getElementById("matched-body");
    mb.innerHTML = matched.length ? matched.map((item) => `
      <tr class="faq-row">
        <td><span class="count-pill ok">${item.count}</span></td>
        <td>${escapeHtml(item.category || "-")}</td>
        <td><div class="cell-q">${escapeHtml(item.question || "")}</div></td>
        <td><div class="cell-samples">${escapeHtml((item.samples || []).slice(0, 3).join(" · "))}</div></td>
        <td>${escapeHtml(thaiWhen(item.last_time || ""))}</td>
      </tr>
    `).join("") : '<tr><td colspan="5" class="hint">ยังไม่มีคำถามที่ตอบได้ในช่วงนี้</td></tr>';

    const body = document.getElementById("log-body");
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="6" class="hint">ยังไม่มีประวัติการถามจริงในช่วงนี้</td></tr>';
      return;
    }
    body.innerHTML = rows.slice(0, 120).map((row) => `
      <tr class="faq-row log-row">
        <td>${escapeHtml(thaiWhen(row.time || ""))}</td>
        <td><span class="tag">${escapeHtml(String(row.visitor || "-"))}</span></td>
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
