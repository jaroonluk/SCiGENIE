(function () {
  const form = document.getElementById("user-form");
  const csrf = form.csrf.value;
  const search = document.getElementById("user-search");
  const suggest = document.getElementById("user-suggest");
  const email = document.getElementById("user-email");
  const name = document.getElementById("user-name");
  const role = document.getElementById("user-role");
  const active = document.getElementById("user-active");
  const userId = document.getElementById("user-id");
  const msg = document.getElementById("user-form-msg");
  const body = document.getElementById("user-body");
  const listMsg = document.getElementById("list-msg");
  const catBox = document.getElementById("cat-checks");
  let timer = 0;

  function toast(text, ok) {
    listMsg.hidden = false;
    listMsg.innerHTML = `<div class="alert ${ok ? "ok" : "err"}">${escapeHtml(text)}</div>`;
    setTimeout(() => { listMsg.hidden = true; }, 4000);
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  }

  function selectedCats() {
    return [...form.querySelectorAll('input[name="categories[]"]:checked')].map((el) => el.value);
  }

  function setCats(list) {
    form.querySelectorAll('input[name="categories[]"]').forEach((el) => {
      el.checked = (list || []).includes(el.value);
    });
  }

  function toggleCats() {
    catBox.hidden = role.value === "super";
  }
  role.addEventListener("change", toggleCats);
  toggleCats();

  search.addEventListener("input", () => {
    clearTimeout(timer);
    const q = search.value.trim();
    if (q.length < 2) {
      suggest.hidden = true;
      suggest.innerHTML = "";
      return;
    }
    timer = setTimeout(async () => {
      const res = await fetch("../api/eoffice-lookup.php?q=" + encodeURIComponent(q));
      const json = await res.json();
      const items = json.items || [];
      if (!items.length) {
        suggest.hidden = false;
        suggest.innerHTML = `<button type="button" class="suggest-empty" disabled>ไม่พบใน eoffice</button>`;
        return;
      }
      suggest.hidden = false;
      suggest.innerHTML = items.map((item) => (
        `<button type="button" class="suggest-item" data-email="${escapeHtml(item.email)}" data-name="${escapeHtml(item.name)}">
          <strong>${escapeHtml(item.name)}</strong>
          <span>${escapeHtml(item.email)}${item.position ? " · " + escapeHtml(item.position) : ""}</span>
        </button>`
      )).join("");
    }, 250);
  });

  suggest.addEventListener("click", (e) => {
    const btn = e.target.closest(".suggest-item");
    if (!btn) return;
    email.value = btn.dataset.email || "";
    name.value = btn.dataset.name || "";
    search.value = btn.dataset.name || "";
    suggest.hidden = true;
  });

  document.addEventListener("click", (e) => {
    if (!suggest.contains(e.target) && e.target !== search) suggest.hidden = true;
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    msg.innerHTML = "";
    const payload = {
      csrf,
      id: userId.value,
      email: email.value,
      name: name.value,
      role: role.value,
      is_active: active.checked ? 1 : 0,
      categories: selectedCats()
    };
    try {
      const res = await fetch("../api/admin-users.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (!json.ok) {
        msg.innerHTML = `<div class="alert err">${escapeHtml(json.error || "บันทึกไม่สำเร็จ")}</div>`;
        return;
      }
      toast(json.message || "บันทึกแล้ว", true);
      renderUsers(json.users || []);
      resetForm();
    } catch (err) {
      msg.innerHTML = `<div class="alert err">เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ</div>`;
    }
  });

  document.getElementById("user-reset").addEventListener("click", resetForm);

  body.addEventListener("click", async (e) => {
    const tr = e.target.closest("tr");
    if (!tr) return;
    const item = JSON.parse(tr.dataset.item || "{}");
    if (e.target.closest(".edit-user")) {
      userId.value = item.id || "";
      email.value = item.email || "";
      name.value = item.name || "";
      role.value = item.role || "manager";
      active.checked = Number(item.is_active) === 1;
      setCats(item.categories || []);
      toggleCats();
      document.getElementById("user-form-title").textContent = "แก้ไขผู้ใช้";
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }
    if (e.target.closest(".del-user")) {
      if (!confirm("ลบสิทธิ์ของ " + (item.email || "") + " ?")) return;
      const res = await fetch("../api/admin-users.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ csrf, id: item.id })
      });
      const json = await res.json();
      if (!json.ok) {
        toast(json.error || "ลบไม่สำเร็จ", false);
        return;
      }
      toast(json.message || "ลบแล้ว", true);
      renderUsers(json.users || []);
    }
  });

  function resetForm() {
    userId.value = "";
    email.value = "";
    name.value = "";
    search.value = "";
    role.value = "manager";
    active.checked = true;
    setCats([]);
    toggleCats();
    document.getElementById("user-form-title").textContent = "เพิ่มผู้ใช้";
    msg.innerHTML = "";
  }

  function renderUsers(users) {
    body.innerHTML = users.map((row) => {
      const cats = row.role === "super" ? "ทุกหมวด" : (row.categories || []).join(", ");
      return `<tr data-item="${escapeHtml(JSON.stringify(row))}">
        <td>${escapeHtml(row.name || "")}</td>
        <td>${escapeHtml(row.email || "")}</td>
        <td>${row.role === "super" ? "ผู้ดูแลระบบ" : "ผู้จัดการหมวด"}</td>
        <td>${escapeHtml(cats)}</td>
        <td>${Number(row.is_active) === 1 ? "เปิด" : "ปิด"}</td>
        <td><div class="actions">
          <button type="button" class="icon-act edit-user" aria-label="แก้ไข">แก้</button>
          <button type="button" class="icon-act del del-user" aria-label="ลบ">ลบ</button>
        </div></td>
      </tr>`;
    }).join("");
  }
})();
