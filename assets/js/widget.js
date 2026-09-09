(function () {
  const SCRIPT = document.currentScript;
  const BASE = (SCRIPT && SCRIPT.src ? SCRIPT.src.replace(/\/assets\/js\/widget\.js.*$/, "") : "").replace(/\/$/, "");
  const API = BASE + "/api";

  if (!document.querySelector("link[data-scigenie-font]")) {
    const font = document.createElement("link");
    font.rel = "stylesheet";
    font.href = "https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@400;600&display=swap";
    font.dataset.scigenieFont = "1";
    document.head.appendChild(font);
  }
  if (!document.querySelector("link[data-scigenie-css]")) {
    const css = document.createElement("link");
    css.rel = "stylesheet";
    css.href = BASE + "/assets/css/widget.css?v=20260819";
    css.dataset.scigenieCss = "1";
    document.head.appendChild(css);
  }

  const ICONS = {
    info: '<path d="M12 16v-4"/><path d="M12 8h.01"/><circle cx="12" cy="12" r="10"/>',
    "user-plus": '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
    clipboard: '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
    "file-pen": '<path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 3 3L8 21l-4 1 1-4Z"/>',
    "id-card": '<rect width="20" height="14" x="2" y="5" rx="2"/><circle cx="8.5" cy="12" r="2"/><path d="M14 10h4"/><path d="M14 14h4"/>',
    award: '<circle cx="12" cy="8" r="6"/><path d="M8.2 13.6 7 22l5-3 5 3-1.2-8.4"/>',
    briefcase: '<rect width="20" height="14" x="2" y="7" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M12 12v.01"/>',
    book: '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/><path d="M8 7h8"/><path d="M8 11h6"/>',
    coins: '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.7 14.4.8.8"/>',
    heart: '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
    flask: '<path d="M10 2v7.3a1 1 0 0 1-.2.6L6.5 14a6 6 0 1 0 11 0l-3.3-4.1a1 1 0 0 1-.2-.6V2"/><path d="M8.5 2h7"/>',
    microscope: '<path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h.01"/><path d="M9 9a3 3 0 0 1 6 0"/><path d="M12 6V3"/>',
    newspaper: '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/>',
    sparkles: '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
    school: '<path d="M14 22v-4a2 2 0 0 0-4 0v4"/><path d="m18 10 4 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8l4-2"/><path d="M18 5v17"/><path d="m4 6 8-4 8 4"/><path d="M9 12v.01"/><path d="M15 12v.01"/>',
    folder: '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
    atom: '<circle cx="12" cy="12" r="1"/><path d="M20.2 20.2c2.04-2.03.02-7.36-4.5-11.9-4.54-4.52-9.87-6.54-11.9-4.5-2.04 2.03-.02 7.36 4.5 11.9 4.54 4.52 9.87 6.54 11.9 4.5Z"/><path d="M15.7 15.7c4.52-4.54 6.54-9.87 4.5-11.9-2.03-2.04-7.36-.02-11.9 4.5-4.52 4.54-6.54 9.87-4.5 11.9 2.03 2.04 7.36.02 11.9-4.5Z"/>',
    send: '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
    x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    maximize: '<path d="M15 3h6v6"/><path d="m21 3-7 7"/><path d="m3 21 7-7"/><path d="M3 15v6h6"/>',
    minimize: '<path d="m14 10 7-7"/><path d="M20 10V4h-6"/><path d="m3 21 7-7"/><path d="M4 14v6h6"/>',
    "refresh-ccw": '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    bot: '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>',
    message: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'
  };

  const LOGO = BASE + "/assets/img/logo.jpg";
  function logoImg() {
    return `<img src="${LOGO}" alt="SCiGENIE">`;
  }
  function svg(name) {
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${ICONS[name] || ICONS.folder}</svg>`;
  }

  function el(html) {
    const t = document.createElement("template");
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatAnswer(text) {
    let s = escapeHtml(text).replace(/\r\n/g, "\n");
    s = s.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
    s = s.replace(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/g, '<a href="mailto:$1">$1</a>');
    return s;
  }

  const root = el(`
    <div id="scigenie-root" aria-live="polite">
      <div class="scg-tip">ถาม <b>SCiGENIE</b> ได้ที่นี่</div>
      <section class="scg-panel" role="dialog" aria-label="ตอบคำถามอัจฉริยะ">
        <div class="scg-head">
          <div class="scg-logo">${logoImg()}</div>
          <div>
            <h2>SCiGENIE</h2>
            <p>ผู้ช่วยตอบคำถาม คณะวิทยาศาสตร์ มข.</p>
          </div>
          <div class="scg-head-actions">
            <button class="scg-icon-btn" data-act="home" title="เลือกหมวดใหม่" aria-label="เลือกหมวดใหม่">${svg("refresh-ccw")}</button>
            <button class="scg-icon-btn" data-act="full" title="ขยายเต็มจอ" aria-label="ขยายเต็มจอ" aria-pressed="false">${svg("maximize")}</button>
            <button class="scg-icon-btn" data-act="close" title="ปิด" aria-label="ปิด">${svg("x")}</button>
          </div>
        </div>
        <div class="scg-catbar"></div>
        <div class="scg-body"></div>
        <div class="scg-foot">
          <form class="scg-form">
            <textarea rows="1" placeholder="พิมพ์คำถามได้เลย หรือเลือกหมวดเพื่อความแม่นยำ"></textarea>
            <button class="scg-send" type="submit" title="ส่งคำถาม">${svg("send")}</button>
          </form>
          <div class="scg-note">ตอบจากคลังคำถามคณะวิทยาศาสตร์ • เลือกหมวดถ้าต้องการให้ตอบตรงขึ้น</div>
        </div>
      </section>
      <button class="scg-fab" type="button" aria-label="เปิดผู้ช่วย SCiGENIE">
        <span class="scg-pulse"></span>
        ${logoImg()}
      </button>
    </div>
  `);

  document.body.appendChild(root);

  const panel = root.querySelector(".scg-panel");
  const fab = root.querySelector(".scg-fab");
  const tip = root.querySelector(".scg-tip");
  const body = root.querySelector(".scg-body");
  const catbar = root.querySelector(".scg-catbar");
  const form = root.querySelector(".scg-form");
  const input = form.querySelector("textarea");
  const sendBtn = form.querySelector(".scg-send");
  const fullBtn = root.querySelector('[data-act="full"]');

  let open = false;
  let full = false;
  let categories = [];
  let currentCategory = "";
  let loaded = false;

  try {
    full = localStorage.getItem("scigenie_full") === "1";
  } catch (e) {
    full = false;
  }

  function applyFull() {
    root.classList.toggle("is-full", full && open);
    document.documentElement.classList.toggle("scigenie-noscroll", full && open);
    fullBtn.innerHTML = full ? svg("minimize") : svg("maximize");
    fullBtn.title = full ? "ย่อหน้าต่าง" : "ขยายเต็มจอ";
    fullBtn.setAttribute("aria-label", fullBtn.title);
    fullBtn.setAttribute("aria-pressed", full ? "true" : "false");
  }

  function setFull(on) {
    full = on;
    try {
      localStorage.setItem("scigenie_full", full ? "1" : "0");
    } catch (e) {}
    applyFull();
  }

  function toggle(force) {
    open = typeof force === "boolean" ? force : !open;
    panel.classList.toggle("is-open", open);
    fab.classList.toggle("is-open", open);
    fab.innerHTML = open ? svg("x") : `<span class="scg-pulse"></span>${logoImg()}`;
    tip.style.display = open ? "none" : "";
    applyFull();
    if (open && !loaded) {
      showWelcome();
      loadCategories();
      setComposer(true);
    }
  }

  fab.addEventListener("click", () => toggle());
  root.querySelector('[data-act="close"]').addEventListener("click", () => toggle(false));
  fullBtn.addEventListener("click", () => setFull(!full));
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape" || !open) return;
    if (full) {
      e.preventDefault();
      setFull(false);
      return;
    }
    toggle(false);
  });
  root.querySelector('[data-act="home"]').addEventListener("click", () => {
    currentCategory = "";
    setComposer(true);
    catbar.classList.remove("is-on");
    showWelcome();
    renderCategories();
  });

  async function loadCategories() {
    try {
      const res = await fetch(API + "/categories.php");
      const data = await res.json();
      categories = data.categories || [];
      loaded = true;
      renderCategories();
    } catch (e) {
      body.insertAdjacentHTML("beforeend", `<div class="scg-msg"><div class="scg-avatar">${logoImg()}</div><div class="scg-bubble">เชื่อมต่อคลังคำถามไม่สำเร็จ กรุณาลองใหม่</div></div>`);
    }
  }

  function showWelcome() {
    body.innerHTML = `
      <div class="scg-msg">
        <div class="scg-avatar">${logoImg()}</div>
        <div class="scg-bubble">สวัสดีค่ะ ยินดีต้อนรับสู่ <b>SCiGENIE</b> ผู้ช่วยตอบคำถามคณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น<br><br>พิมพ์คำถามได้เลยค่ะ หรือเลือกหมวดด้านล่างเพื่อให้ตอบตรงขึ้น</div>
      </div>
      <div class="scg-cats" id="scg-cats"></div>
    `;
  }

  function renderCategories() {
    if (!body.querySelector("#scg-cats")) {
      showWelcome();
    }
    const box = body.querySelector("#scg-cats");
    if (box) {
      fillCategoryButtons(box);
    }
  }

  function fillCategoryButtons(box) {
    box.innerHTML = categories.map((c) => `
      <button class="scg-cat" data-cat="${escapeHtml(c.name)}">
        <div class="scg-cat-ico">${svg(c.icon)}</div>
        <b>${escapeHtml(c.short || c.name)}</b>
        <span>${c.count} คำถาม • ${escapeHtml(c.hint || "")}</span>
      </button>
    `).join("");
    box.querySelectorAll(".scg-cat").forEach((btn) => {
      btn.addEventListener("click", () => selectCategory(btn.dataset.cat));
    });
  }

  async function selectCategory(name) {
    applyCategory(name);
    const meta = categories.find((c) => c.name === name) || { short: name, icon: "folder" };
    body.innerHTML = "";
    addBot(`เลือกหมวด <b>${escapeHtml(meta.short || name)}</b> แล้วค่ะ พิมพ์คำถามที่ช่องด้านล่างได้เลย`);
    input.focus();
  }

  function applyCategory(name) {
    currentCategory = name;
    const meta = categories.find((c) => c.name === name) || { short: name, icon: "folder" };
    catbar.classList.add("is-on");
    catbar.innerHTML = `<span class="scg-chip">${svg(meta.icon)}${escapeHtml(meta.short || name)}</span><span>พิมพ์คำถามได้เลย</span>`;
    setComposer(true);
  }

  function setComposer(on) {
    input.disabled = !on;
    sendBtn.disabled = !on;
    input.placeholder = on
      ? "พิมพ์คำถาม เช่น ค่าเทอม เลื่อนสอบ ทุนการศึกษา"
      : "พิมพ์คำถามได้เลย หรือเลือกหมวดเพื่อความแม่นยำ";
  }

  function addBot(html, extra) {
    const node = el(`<div class="scg-msg"><div class="scg-avatar">${logoImg()}</div><div class="scg-bubble">${html}${extra || ""}</div></div>`);
    body.appendChild(node);
    body.scrollTop = body.scrollHeight;
    return node;
  }

  function addUser(text) {
    const node = el(`<div class="scg-msg user"><div class="scg-bubble">${escapeHtml(text)}</div></div>`);
    body.appendChild(node);
    body.scrollTop = body.scrollHeight;
  }

  function addTyping() {
    addBot(`<div class="scg-typing"><i></i><i></i><i></i></div>`).classList.add("scg-wait");
  }

  function removeTyping() {
    body.querySelectorAll(".scg-wait").forEach((n) => n.remove());
  }

  function visitorId() {
    try {
      let id = localStorage.getItem("scigenie_vid");
      if (!id) {
        id = Math.random().toString(36).slice(2, 8).toUpperCase();
        localStorage.setItem("scigenie_vid", id);
      }
      return id;
    } catch (e) {
      return "";
    }
  }

  async function ask(text) {
    const q = (text || "").trim();
    if (!q) return;
    addUser(q);
    addTyping();
    try {
      const res = await fetch(API + "/ask.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ category: currentCategory, question: q, visitor: visitorId() })
      });
      const data = await res.json();
      removeTyping();
      renderAnswer(data);
    } catch (e) {
      removeTyping();
      addBot("ระบบตอบคำถามขัดข้องชั่วคราว กรุณาลองอีกครั้ง หรือโทร 043-009-700");
    }
  }

  function renderAnswer(data) {
    if (data.matched && data.item) {
      const item = data.item;
      if (item.category && (!currentCategory || data.other_category || item.category !== currentCategory)) {
        applyCategory(item.category);
      }
      const other = data.other_category ? `<div class="scg-meta">พบคำตอบในหมวด «${escapeHtml(item.category)}»</div>` : "";
      addBot(`${other}<div class="scg-meta">คำตอบ</div>${formatAnswer(item.answer)}`);
      return;
    }
    addBot(formatAnswer(data.message || "ขออภัยค่ะ ข้อมูลส่วนนี้ไม่มีในระบบที่ตรวจสอบได้ในขณะนี้"));
    if (data.need_category) {
      body.querySelectorAll("#scg-cats").forEach((n) => n.remove());
      const wrap = el(`<div class="scg-cats" id="scg-cats"></div>`);
      body.appendChild(wrap);
      fillCategoryButtons(wrap);
    }
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const q = input.value.trim();
    input.value = "";
    ask(q);
  });

  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  setTimeout(() => { if (!open) tip.style.opacity = "1"; }, 800);
  window.SCiGENIE = {
    open: () => toggle(true),
    close: () => toggle(false),
    expand: () => { toggle(true); setFull(true); },
    collapse: () => setFull(false)
  };
})();
