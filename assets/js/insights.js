(function () {
  const coachText = document.getElementById("coach-text");
  const DEFAULT = (coachText && coachText.textContent.trim()) || "เริ่มที่แท็บ «ไม่พบคำตอบ» เพื่อดูคำถามที่ควรถูกเพิ่มในคลัง";
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

  const logBody = document.getElementById("log-body");
  if (logBody) logBody.addEventListener("click", (e) => {
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

  function mountChart(id, config) {
    const canvas = document.getElementById(id);
    return chartsReady && canvas ? new Chart(canvas, config) : null;
  }

  const dayChart = mountChart("chart-day", {
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
  });

  const hourChart = mountChart("chart-hour", {
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
  });

  const categoryValuePlugin = {
    id: "categoryValue",
    afterDatasetsDraw(chart) {
      const { ctx } = chart;
      const meta = chart.getDatasetMeta(0);
      if (!meta || meta.hidden) return;
      ctx.save();
      ctx.fillStyle = brown;
      ctx.font = "600 12px Prompt, Sarabun, sans-serif";
      ctx.textAlign = "left";
      ctx.textBaseline = "middle";
      meta.data.forEach((bar, index) => {
        const value = Number(chart.data.datasets[0].data[index] || 0);
        if (value <= 0) return;
        ctx.fillText(String(value), bar.x + 6, bar.y);
      });
      ctx.restore();
    }
  };

  const catChart = mountChart("chart-cat", {
    type: "bar",
    data: {
      labels: (data.summary.by_category || []).map((item) => item.label),
      datasets: [{
        label: "จำนวนครั้งที่ถาม",
        data: (data.summary.by_category || []).map((item) => item.count),
        backgroundColor: yellow,
        borderRadius: 8,
        maxBarThickness: 22
      }]
    },
    options: categoryOptions(),
    plugins: [categoryValuePlugin]
  });

  const channelChart = mountChart("chart-channel", {
    type: "bar",
    data: channelData(data.summary.by_channel || {}),
    options: channelOptions()
  });

  let outcomePick = null;
  const outcomeAnswered = "#1f7a4d";
  const outcomeMissed = "#c0392b";

  const outcomeChart = mountChart("chart-outcome", {
    type: "bar",
    data: outcomeChartData(((data.summary && data.summary.by_category) || []).filter((item) => Number(item.matched || 0) + Number(item.unmatched || 0) > 0)),
    options: outcomeOptions()
  });

  function categoryOptions() {
    return {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { right: 36 } },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const values = ctx.chart.data.datasets[0].data || [];
              const total = values.reduce((sum, n) => sum + Number(n || 0), 0);
              const n = Number(ctx.parsed.x || 0);
              const pct = total ? Math.round(n * 100 / total) : 0;
              return ` ${n} ครั้ง (${pct}%)`;
            }
          }
        }
      },
      scales: {
        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "rgba(184,134,11,.15)" } },
        y: {
          grid: { display: false },
          ticks: {
            autoSkip: false,
            font: { size: 12 },
            callback(value) {
              const label = this.getLabelForValue(value);
              return String(label).length > 24 ? String(label).slice(0, 23) + "…" : label;
            }
          }
        }
      }
    };
  }

  function channelOptions() {
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: "top", labels: { boxWidth: 14, usePointStyle: true } },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y}`
          }
        }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "rgba(184,134,11,.15)" } },
        x: { grid: { display: false } }
      }
    };
  }

  function channelData(bundle) {
    const labels = bundle.labels || ["เว็บไซต์", "LINE", "Facebook"];
    return {
      labels,
      datasets: [
        {
          label: "จำนวนครั้งที่ถาม",
          data: bundle.questions || [0, 0, 0],
          backgroundColor: yellow,
          borderRadius: 8,
          maxBarThickness: 36
        },
        {
          label: "จำนวนคน",
          data: bundle.people || [0, 0, 0],
          backgroundColor: "#1f6b4a",
          borderRadius: 8,
          maxBarThickness: 36
        }
      ]
    };
  }

  function outcomeItems() {
    const selected = (filterCat.value || "").trim();
    let items = ((data.summary && data.summary.by_category) || []).filter((item) => {
      return Number(item.matched || 0) + Number(item.unmatched || 0) > 0;
    });
    if (selected) items = items.filter((item) => item.label === selected);
    return items;
  }

  function outcomeChartData(items) {
    return {
      labels: items.map((item) => item.label),
      datasets: [
        {
          label: "ตอบได้",
          data: items.map((item) => Number(item.matched || 0)),
          backgroundColor: outcomeAnswered,
          borderRadius: 6,
          maxBarThickness: 22
        },
        {
          label: "ตอบไม่ได้",
          data: items.map((item) => Number(item.unmatched || 0)),
          backgroundColor: outcomeMissed,
          borderRadius: 6,
          maxBarThickness: 22
        }
      ]
    };
  }

  function outcomeOptions() {
    return {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      onHover(event, elements) {
        const target = event.native && event.native.target;
        if (target) target.style.cursor = elements.length ? "pointer" : "default";
      },
      onClick(event, elements, chart) {
        const points = elements.length ? elements : chart.getElementsAtEventForMode(event, "nearest", { intersect: true }, true);
        if (!points.length) return;
        const point = points[0];
        const label = chart.data.labels[point.index];
        showOutcome(String(label || ""), point.datasetIndex === 0, true);
      },
      plugins: {
        legend: { position: "top", labels: { boxWidth: 14, usePointStyle: true } },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.x} ครั้ง`
          }
        }
      },
      scales: {
        x: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: "rgba(184,134,11,.15)" } },
        y: {
          stacked: true,
          grid: { display: false },
          ticks: {
            autoSkip: false,
            font: { size: 12 },
            callback(value) {
              const label = this.getLabelForValue(value);
              return String(label).length > 24 ? String(label).slice(0, 23) + "…" : label;
            }
          }
        }
      }
    };
  }

  function outcomeSummaryText(items) {
    if (!items.length) return "ยังไม่มีคำถามในช่วงนี้";
    const matched = items.reduce((sum, item) => sum + Number(item.matched || 0), 0);
    const unmatched = items.reduce((sum, item) => sum + Number(item.unmatched || 0), 0);
    return `ตอบได้ ${matched} ครั้ง · ตอบไม่ได้ ${unmatched} ครั้ง ใน ${items.length} หมวด`;
  }

  function showOutcome(label, matched, scroll) {
    outcomePick = { label, matched };
    const panel = document.getElementById("outcome-detail");
    const title = document.getElementById("outcome-title");
    const note = document.getElementById("outcome-note");
    const list = document.getElementById("outcome-list");
    if (!panel || !title || !note || !list) return;
    const details = (data.summary && data.summary.category_details && data.summary.category_details[label]) || {};
    const side = matched ? (details.matched || { total: 0, items: [] }) : (details.unmatched || { total: 0, items: [] });
    const items = side.items || [];
    title.textContent = matched
      ? `หมวด ${label} · คำถามที่ตอบได้`
      : `หมวด ${label} · คำถามที่ตอบไม่ได้`;
    const hidden = Math.max(0, Number(side.total || 0) - items.length);
    note.textContent = items.length
      ? (hidden ? `แสดง ${items.length} รายการที่ถูกถามบ่อยสุด จากทั้งหมด ${side.total} แบบ` : "เรียงจากถูกถามบ่อยสุด")
      : (matched ? "ไม่มีคำถามที่ตอบได้ในหมวดนี้" : "ไม่มีคำถามที่ตอบไม่ได้ในหมวดนี้");
    list.className = "outcome-list " + (matched ? "is-ok" : "is-no");
    list.innerHTML = items.map((item) => {
      const samples = (item.samples || []).filter((sample) => sample && sample !== item.question);
      const answer = matched && item.answer
        ? `<div class="a"><b>คำตอบ</b><br>${escapeHtml(item.answer)}</div>`
        : "";
      const sampleLine = samples.length
        ? `<div class="samples">ผู้ใช้พิมพ์ว่า ${escapeHtml(samples.join(" · "))}</div>`
        : "";
      const add = matched ? "" : `<a class="btn" href="${addUrl(label === "ไม่ระบุหมวด" ? "" : label, item.question)}">เพิ่มคำตอบ</a>`;
      return `<li>
        <div class="q">${escapeHtml(item.question || "-")}</div>
        ${sampleLine}
        ${answer}
        <div class="meta">${item.count} ครั้ง · ล่าสุด ${escapeHtml(thaiWhen(item.last_time || ""))}</div>
        ${add}
      </li>`;
    }).join("");
    panel.hidden = false;
    if (scroll) panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function closeOutcome() {
    outcomePick = null;
    const panel = document.getElementById("outcome-detail");
    if (panel) panel.hidden = true;
  }

  function channelOf(row) {
    const channel = String(row.channel || "").toLowerCase();
    if (channel === "web" || channel === "line" || channel === "facebook") return channel;
    const visitor = String(row.visitor || "").toUpperCase();
    if (visitor.startsWith("LINE")) return "line";
    if (visitor.startsWith("FB")) return "facebook";
    return "web";
  }

  function channelsFromRows(rows) {
    const keys = ["web", "line", "facebook"];
    const questions = { web: 0, line: 0, facebook: 0 };
    const people = { web: new Set(), line: new Set(), facebook: new Set() };
    rows.forEach((row) => {
      const channel = channelOf(row);
      questions[channel] += 1;
      if (row.visitor) people[channel].add(String(row.visitor));
    });
    return {
      labels: ["เว็บไซต์", "LINE", "Facebook"],
      keys,
      questions: keys.map((key) => questions[key]),
      people: keys.map((key) => people[key].size)
    };
  }

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
        x: { grid: { display: false }, ticks: hour ? { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } : { maxRotation: 0, autoSkip: true, maxTicksLimit: 16 } }
      }
    };
  }

  const rangeFrom = document.getElementById("range-from");
  const rangeTo = document.getElementById("range-to");
  const rangeForm = document.getElementById("range-custom");

  function ymd(date) {
    const z = (n) => String(n).padStart(2, "0");
    return `${date.getFullYear()}-${z(date.getMonth() + 1)}-${z(date.getDate())}`;
  }

  function fillPresetDates(n) {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - Math.max(1, n));
    rangeFrom.value = ymd(start);
    rangeTo.value = ymd(end);
  }

  function thaiDayLabel(iso) {
    const parts = String(iso || "").split("-");
    if (parts.length !== 3) return iso || "-";
    const months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    const month = months[Number(parts[1]) - 1] || "";
    return `${Number(parts[2])} ${month} ${Number(parts[0]) + 543}`;
  }

  function rangeLabel(summary) {
    if (summary && summary.mode === "custom" && summary.from && summary.to) {
      return `กำลังแสดง ${thaiDayLabel(summary.from)} – ${thaiDayLabel(summary.to)} จากคำถามจริง`;
    }
    return `กำลังแสดง ${days} วันล่าสุด จากคำถามจริง`;
  }

  async function loadRange(query, pendingText) {
    document.getElementById("range-hint").textContent = pendingText;
    try {
      const res = await fetch(`../api/insights.php?${query}`);
      const json = await res.json();
      if (!json.ok) {
        document.getElementById("range-hint").textContent = json.error || "โหลดสถิติไม่สำเร็จ ลองกดอีกครั้ง";
        return;
      }
      data.summary = json.summary;
      if (json.rows) data.rows = json.rows;
      days = Number(json.summary.days) || days;
      applySummary(json.summary);
      renderAll();
      let label = rangeLabel(json.summary);
      if ((json.summary.total || 0) > (json.rows || []).length) {
        label += ` ตารางแสดง ${(json.rows || []).length} รายการล่าสุดในช่วงนี้`;
      }
      document.getElementById("range-hint").textContent = label;
    } catch (e) {
      document.getElementById("range-hint").textContent = "โหลดสถิติไม่สำเร็จ ลองกดอีกครั้ง";
    }
  }

  fillPresetDates(14);
  document.querySelectorAll(".range").forEach((btn) => {
    btn.classList.toggle("is-on", btn.dataset.days === "14");
    btn.addEventListener("click", () => {
      document.querySelectorAll(".range").forEach((b) => b.classList.remove("is-on"));
      btn.classList.add("is-on");
      days = Number(btn.dataset.days);
      fillPresetDates(days);
      loadRange(`days=${days}`, `กำลังโหลดสถิติ ${days} วันล่าสุด...`);
    });
  });

  rangeForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const from = rangeFrom.value;
    const to = rangeTo.value;
    if (!from || !to) {
      document.getElementById("range-hint").textContent = "ระบุวันที่เริ่มและวันที่สิ้นสุดให้ครบ";
      return;
    }
    if (from > to) {
      document.getElementById("range-hint").textContent = "วันที่เริ่มต้องไม่เกินวันที่สิ้นสุด";
      return;
    }
    const start = new Date(from + "T00:00:00");
    const end = new Date(to + "T00:00:00");
    const span = Math.round((end - start) / 86400000) + 1;
    if (span > 366) {
      document.getElementById("range-hint").textContent = "เลือกช่วงได้ไม่เกิน 366 วัน";
      return;
    }
    document.querySelectorAll(".range").forEach((b) => b.classList.remove("is-on"));
    loadRange(`from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`, "กำลังโหลดสถิติตามช่วงวันที่...");
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
    const tabUnmatched = document.getElementById("tab-unmatched");
    const tabMatched = document.getElementById("tab-matched");
    if (tabUnmatched) tabUnmatched.textContent = s.unmatched;
    if (tabMatched) tabMatched.textContent = s.matched;
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
    syncCompareCharts();
  }

  function compareText(items) {
    if (!items.length) return "ยังไม่มีหมวดให้เปรียบเทียบ";
    const total = items.reduce((sum, item) => sum + Number(item.count || 0), 0);
    if (!total) return "ยังไม่มีคำถามในช่วงนี้";
    const top = items[0];
    const low = items[items.length - 1];
    const zeros = items.filter((item) => Number(item.count || 0) === 0).length;
    const pct = Math.round(Number(top.count || 0) * 100 / total);
    let text = `ถามมากสุด ${top.label} ${top.count} ครั้ง (${pct}%)`;
    if (low.label !== top.label) text += ` · น้อยสุด ${low.label} ${low.count} ครั้ง`;
    if (zeros) text += ` · มี ${zeros} หมวดที่ยังไม่มีคนถาม`;
    return text;
  }

  function selectedChannelBundle() {
    const cat = (filterCat.value || "").trim();
    const q = (filterQ.value || "").trim();
    const summary = data.summary || {};
    if (q) return channelsFromRows(visibleRows());
    if (cat) {
      const item = (summary.category_channels && summary.category_channels[cat]) || {};
      return {
        labels: ["เว็บไซต์", "LINE", "Facebook"],
        keys: ["web", "line", "facebook"],
        questions: ["web", "line", "facebook"].map((key) => Number(item.questions?.[key] || 0)),
        people: ["web", "line", "facebook"].map((key) => Number(item.people?.[key] || 0))
      };
    }
    return summary.by_channel || { labels: ["เว็บไซต์", "LINE", "Facebook"], keys: ["web", "line", "facebook"], questions: [0, 0, 0], people: [0, 0, 0] };
  }

  function paintChannelStats(bundle) {
    const list = document.getElementById("channel-stats");
    if (!list) return;
    const labels = bundle.labels || ["เว็บไซต์", "LINE", "Facebook"];
    const keys = bundle.keys || ["web", "line", "facebook"];
    const questions = bundle.questions || [0, 0, 0];
    const people = bundle.people || [0, 0, 0];
    const total = questions.reduce((sum, n) => sum + Number(n || 0), 0);
    list.innerHTML = labels.map((label, i) => {
      const count = Number(questions[i] || 0);
      const users = Number(people[i] || 0);
      const pct = total ? Math.round(count * 100 / total) : 0;
      return `<li data-channel="${escapeHtml(keys[i] || "")}"><span>${escapeHtml(label)}</span><b>${users} คน</b><small>${count} ครั้ง · ${pct}% ของคำถาม</small></li>`;
    }).join("");
  }

  function syncCompareCharts() {
    const items = (data.summary && data.summary.by_category) || [];
    const compare = document.getElementById("cat-compare");
    if (compare) compare.textContent = compareText(items);
    const selected = (filterCat.value || "").trim();
    const box = document.getElementById("chart-cat-box");
    if (box) box.style.height = Math.min(560, Math.max(220, items.length * 34 + 28)) + "px";
    if (catChart) {
      catChart.data.labels = items.map((item) => item.label);
      catChart.data.datasets[0].data = items.map((item) => item.count);
      catChart.data.datasets[0].backgroundColor = items.map((item) => item.label === selected ? brown : yellow);
      catChart.resize();
      catChart.update();
    }
    const bundle = selectedChannelBundle();
    paintChannelStats(bundle);
    const hint = document.getElementById("channel-hint");
    if (hint) {
      const q = (filterQ.value || "").trim();
      hint.textContent = selected
        ? `เฉพาะหมวด ${selected} · คนเดิมถามซ้ำนับเป็น 1 คน`
        : q
          ? "ตามคำที่ค้นหา · คนเดิมถามซ้ำนับเป็น 1 คน"
          : "เทียบเว็บไซต์ LINE และ Facebook ทั้งจำนวนคนและจำนวนครั้งที่ถาม คนเดิมถามซ้ำนับเป็น 1 คน";
    }
    if (channelChart) {
      const next = channelData(bundle);
      channelChart.data.labels = next.labels;
      channelChart.data.datasets[0].data = next.datasets[0].data;
      channelChart.data.datasets[1].data = next.datasets[1].data;
      channelChart.update();
    }
    const outcomes = outcomeItems();
    const outcomeLine = document.getElementById("outcome-compare");
    if (outcomeLine) outcomeLine.textContent = outcomeSummaryText(outcomes);
    const outcomeBox = document.getElementById("chart-outcome-box");
    if (outcomeBox) outcomeBox.style.height = Math.min(560, Math.max(220, outcomes.length * 36 + 36)) + "px";
    if (outcomeChart) {
      const nextOutcome = outcomeChartData(outcomes);
      outcomeChart.data.labels = nextOutcome.labels;
      outcomeChart.data.datasets[0].data = nextOutcome.datasets[0].data;
      outcomeChart.data.datasets[1].data = nextOutcome.datasets[1].data;
      outcomeChart.resize();
      outcomeChart.update();
    }
    if (outcomePick && !outcomes.some((item) => item.label === outcomePick.label)) closeOutcome();
    else if (outcomePick) showOutcome(outcomePick.label, outcomePick.matched);
  }

  function inSelectedRange(ts) {
    const fromTs = Number(data.summary.from_ts || 0) * 1000;
    const toTs = Number(data.summary.to_ts || 0) * 1000;
    if (fromTs > 0 && toTs > 0) return ts >= fromTs && ts <= toTs;
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() - Math.max(1, days));
    return ts >= d.getTime();
  }

  function thaiWhen(iso) {
    const t = new Date(iso);
    if (Number.isNaN(t.getTime())) return iso || "-";
    const months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    const hh = String(t.getHours()).padStart(2, "0");
    const mm = String(t.getMinutes()).padStart(2, "0");
    return `${t.getDate()} ${months[t.getMonth()]} ${t.getFullYear() + 543} เวลา ${hh}:${mm} น.`;
  }

  function rowCategory(row) {
    const name = String(row.category || "").trim();
    return name || "ไม่ระบุหมวด";
  }

  function visibleRows() {
    const cat = (filterCat.value || "").trim();
    const q = (filterQ.value || "").trim().toLowerCase();
    return (data.rows || []).filter((row) => {
      const ts = new Date(row.time || "").getTime();
      if (!Number.isFinite(ts) || !inSelectedRange(ts)) return false;
      if (cat && rowCategory(row) !== cat) return false;
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
    syncCompareCharts();
    const cat = (filterCat.value || "").trim();
    const q = (filterQ.value || "").trim();
    const rows = visibleRows();
    const unmatched = groupRows(rows, false);
    const matched = groupRows(rows, true);
    const matchedN = rows.filter((r) => r.matched).length;
    const unmatchedN = rows.length - matchedN;
    const useServer = cat === "" && q === "" && data.summary && Number.isFinite(Number(data.summary.total));
    document.getElementById("kpi-total").textContent = useServer ? data.summary.total : rows.length;
    document.getElementById("kpi-matched").textContent = useServer ? data.summary.matched : matchedN;
    document.getElementById("kpi-unmatched").textContent = useServer ? data.summary.unmatched : unmatchedN;
    document.getElementById("kpi-rate").textContent = (useServer ? data.summary.match_rate : (rows.length ? Math.round(matchedN * 100 / rows.length) : 0)) + "%";
    const tabUnmatched = document.getElementById("tab-unmatched");
    const tabMatched = document.getElementById("tab-matched");
    if (tabUnmatched) tabUnmatched.textContent = useServer ? data.summary.unmatched : unmatchedN;
    if (tabMatched) tabMatched.textContent = useServer ? data.summary.matched : matchedN;

    const um = document.getElementById("unmatched-body");
    if (um) um.innerHTML = unmatched.length ? unmatched.map((item) => `
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
    if (mb) mb.innerHTML = matched.length ? matched.map((item) => `
      <tr class="faq-row">
        <td><span class="count-pill ok">${item.count}</span></td>
        <td>${escapeHtml(item.category || "-")}</td>
        <td><div class="cell-q">${escapeHtml(item.question || "")}</div></td>
        <td><div class="cell-samples">${escapeHtml((item.samples || []).slice(0, 3).join(" · "))}</div></td>
        <td>${escapeHtml(thaiWhen(item.last_time || ""))}</td>
      </tr>
    `).join("") : '<tr><td colspan="5" class="hint">ยังไม่มีคำถามที่ตอบได้ในช่วงนี้</td></tr>';

    const body = document.getElementById("log-body");
    if (!body) return;
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

  const outcomeClose = document.getElementById("outcome-close");
  if (outcomeClose) outcomeClose.addEventListener("click", closeOutcome);

  syncCompareCharts();
})();
