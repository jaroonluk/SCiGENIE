(function () {
  const form = document.getElementById("site-form");
  const body = document.getElementById("site-body");
  const msg = document.getElementById("site-msg");
  const titleInput = document.getElementById("site-title");
  const urlInput = document.getElementById("site-url");
  const noteInput = document.getElementById("site-note");
  const sortInput = document.getElementById("site-sort");
  const activeInput = document.getElementById("site-active");
  const idInput = document.getElementById("site-id");
  const heading = document.getElementById("site-form-title");
  const saveLabel = document.getElementById("site-save-label");
  if (!form || !body) return;

  const csrf = form.querySelector('[name="csrf"]')?.value || "";

  function show(text, ok) {
    if (!msg) return;
    msg.hidden = false;
    msg.className = ok ? "alert ok" : "alert err";
    msg.textContent = text;
  }

  function resetForm() {
    form.reset();
    idInput.value = "";
    activeInput.checked = true;
    sortInput.value = "0";
    if (heading) heading.textContent = "เพิ่มเว็บไซต์";
    if (saveLabel) saveLabel.textContent = "บันทึก";
  }

  function rowHtml(item) {
    const status = item.is_active ? "เปิดใช้" : "ปิด";
    return `<tr data-item="${escapeAttr(JSON.stringify(item))}">
      <td>${escapeHtml(item.title)}</td>
      <td><a href="${escapeAttr(item.url)}" target="_blank" rel="noopener">${escapeHtml(item.url)}</a></td>
      <td>${escapeHtml(item.note || "")}</td>
      <td>${escapeHtml(status)}</td>
      <td>${Number(item.sort_order) || 0}</td>
      <td>
        <div class="actions">
          <button type="button" class="icon-act edit" title="แก้ไข" aria-label="แก้ไข"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 3 3L8 21l-4 1 1-4Z"/></svg></button>
          <button type="button" class="icon-act del" title="ลบ" aria-label="ลบ"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
        </div>
      </td>
    </tr>`;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/"/g, "&quot;");
  }

  function paint(items) {
    body.innerHTML = items.length
      ? items.map(rowHtml).join("")
      : '<tr><td colspan="6">ยังไม่มีเว็บไซต์ กดบันทึกเพื่อเพิ่มลิงก์แรก</td></tr>';
  }

  body.addEventListener("click", async (event) => {
    const button = event.target.closest("button");
    const row = event.target.closest("tr");
    if (!button || !row) return;
    const item = JSON.parse(row.dataset.item || "{}");
    if (button.classList.contains("edit")) {
      idInput.value = item.id || "";
      titleInput.value = item.title || "";
      urlInput.value = item.url || "";
      noteInput.value = item.note || "";
      sortInput.value = item.sort_order || 0;
      activeInput.checked = !!item.is_active;
      if (heading) heading.textContent = "แก้ไขเว็บไซต์";
      if (saveLabel) saveLabel.textContent = "บันทึกการแก้ไข";
      titleInput.focus();
      return;
    }
    if (!button.classList.contains("del")) return;
    if (!window.confirm("ลบลิงก์นี้ออกจากแหล่งค้นหาของ SCiGENIE?")) return;
    const res = await fetch("../api/web-sources.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ csrf, action: "delete", id: item.id })
    });
    const data = await res.json();
    if (!data.ok) {
      show(data.error || "ลบไม่สำเร็จ", false);
      return;
    }
    paint(data.items || []);
    if (String(idInput.value) === String(item.id)) resetForm();
    show(data.message || "ลบแล้ว", true);
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const res = await fetch("../api/web-sources.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        csrf,
        action: "save",
        id: idInput.value,
        title: titleInput.value,
        url: urlInput.value,
        note: noteInput.value,
        sort_order: sortInput.value,
        is_active: activeInput.checked ? 1 : 0
      })
    });
    const data = await res.json();
    if (!data.ok) {
      show(data.error || "บันทึกไม่สำเร็จ", false);
      return;
    }
    paint(data.items || []);
    resetForm();
    show(data.message || "บันทึกแล้ว", true);
  });

  document.getElementById("site-cancel")?.addEventListener("click", resetForm);
})();
