(function () {
  const form = document.getElementById("add-form");
  const cat = document.getElementById("category");
  const newWrap = document.getElementById("new-cat-wrap");
  const newCat = document.getElementById("new-category");
  const msg = document.getElementById("form-msg");
  const saveBtn = document.getElementById("save-btn");
  const filterCat = document.getElementById("filter-cat");
  const filterQ = document.getElementById("filter-q");

  cat.addEventListener("change", () => {
    newWrap.style.display = cat.value === "__new__" ? "block" : "none";
    if (cat.value === "__new__") newCat.focus();
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    if (data.category === "__new__") {
      data.category = (newCat.value || "").trim();
    }
    saveBtn.disabled = true;
    msg.innerHTML = "";
    try {
      const res = await fetch("../api/add.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
      });
      const json = await res.json();
      if (!json.ok) {
        msg.innerHTML = `<div class="alert err">${json.error || "บันทึกไม่สำเร็จ"}</div>`;
        return;
      }
      const mode = json.sheet && json.sheet.ok ? "ถูกเพิ่มลง Google Sheet แล้ว" : json.message;
      msg.innerHTML = `<div class="alert ok">${mode}</div>`;
      form.question.value = "";
      form.keyword.value = "";
      form.keyword_back.value = "";
      form.answer.value = "";
      prependRow(json.item);
    } catch (err) {
      msg.innerHTML = `<div class="alert err">เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ</div>`;
    } finally {
      saveBtn.disabled = false;
    }
  });

  function prependRow(item) {
    const tbody = document.getElementById("faq-body");
    const tr = document.createElement("tr");
    const text = `${item.category} ${item.question} ${item.keyword} ${item.keyword_back || ""}`.toLowerCase();
    tr.dataset.cat = item.category;
    tr.dataset.text = text;
    tr.innerHTML = `<td><span class="tag">${item.category}</span></td><td>${escapeHtml(item.question)}</td><td>${escapeHtml(item.keyword)}</td>`;
    tbody.prepend(tr);
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  }

  function applyFilter() {
    const c = filterCat.value;
    const q = (filterQ.value || "").toLowerCase().trim();
    document.querySelectorAll("#faq-body tr").forEach((tr) => {
      const okCat = !c || tr.dataset.cat === c;
      const okQ = !q || (tr.dataset.text || "").includes(q);
      tr.style.display = okCat && okQ ? "" : "none";
    });
  }
  filterCat.addEventListener("change", applyFilter);
  filterQ.addEventListener("input", applyFilter);
})();
