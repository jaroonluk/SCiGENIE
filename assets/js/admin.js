(function () {
  const DEFAULT_COACH = "คลิกแถวเพื่อดูคำตอบเต็ม กดดินสอเพื่อแก้ไข กดถังขยะเพื่อลบ หรือกด “เพิ่มคำถาม” เพื่อสร้างรายการใหม่";
  const form = document.getElementById("add-form");
  const cat = document.getElementById("category");
  const newWrap = document.getElementById("new-cat-wrap");
  const newCat = document.getElementById("new-category");
  const msg = document.getElementById("form-msg");
  const listMsg = document.getElementById("list-msg");
  const saveBtn = document.getElementById("save-btn");
  const saveLabel = document.getElementById("save-label");
  const cancelBtn = document.getElementById("cancel-btn");
  const faqId = document.getElementById("faq-id");
  const filterCat = document.getElementById("filter-cat");
  const filterQ = document.getElementById("filter-q");
  const drawer = document.getElementById("drawer");
  const backdrop = document.getElementById("drawer-backdrop");
  const coachText = document.getElementById("coach-text");
  const resultCount = document.getElementById("result-count");
  const emptyState = document.getElementById("empty-state");
  const kwPreview = document.getElementById("kw-preview");
  const csrf = form.csrf.value;

  const ICONS = {
    edit: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 3 3L8 21l-4 1 1-4Z"/></svg>',
    del: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
  };

  function setCoach(text) {
    coachText.textContent = text || DEFAULT_COACH;
  }

  document.addEventListener("mouseover", (e) => {
    const tip = e.target.closest("[data-tip]");
    if (tip) setCoach(tip.dataset.tip);
  });
  document.addEventListener("focusin", (e) => {
    const tip = e.target.closest("[data-tip]");
    if (tip) setCoach(tip.dataset.tip);
    document.querySelectorAll(".field-tip").forEach((el) => el.classList.remove("is-on"));
    const named = e.target.getAttribute("id") || e.target.getAttribute("name");
    if (named) {
      const help = document.querySelector(`.field-tip[data-for="${named}"]`);
      if (help) help.classList.add("is-on");
    }
  });
  document.addEventListener("mouseout", (e) => {
    if (!e.relatedTarget || !e.relatedTarget.closest("[data-tip]")) {
      if (!document.activeElement || !document.activeElement.closest("[data-tip]")) {
        setCoach(drawer.classList.contains("is-open")
          ? "กรอกทีละช่องตามคำแนะนำใต้ฟอร์ม เมื่อครบแล้วกดบันทึก มุมล่างขวา"
          : DEFAULT_COACH);
      }
    }
  });

  function openDrawer(mode) {
    drawer.classList.add("is-open");
    backdrop.hidden = false;
    requestAnimationFrame(() => backdrop.classList.add("is-open"));
    document.getElementById("drawer-title").textContent = mode === "edit" ? "แก้ไขคำถาม" : "เพิ่มคำถามใหม่";
    document.getElementById("drawer-sub").textContent = mode === "edit"
      ? "ตรวจทุกช่องให้ถูกต้อง แล้วกดบันทึกการแก้ไข"
      : "กรอกทีละช่องตามคำแนะนำด้านล่าง แล้วกดบันทึก";
    setCoach(mode === "edit"
      ? "กำลังแก้ไขรายการนี้ ปรับคีย์เวิร์ดและคำตอบให้ตรงกับที่ผู้ใช้มักถาม แล้วกดบันทึก"
      : "เริ่มจากเลือกหมวด แล้วพิมพ์คำถาม คีย์เวิร์ด และคำตอบ");
  }

  function closeDrawer() {
    drawer.classList.remove("is-open");
    backdrop.classList.remove("is-open");
    setTimeout(() => { backdrop.hidden = true; }, 220);
    resetForm();
    setCoach(DEFAULT_COACH);
  }

  document.getElementById("btn-add").addEventListener("click", () => {
    resetForm();
    openDrawer("add");
    cat.focus();
  });
  document.getElementById("drawer-close").addEventListener("click", closeDrawer);
  backdrop.addEventListener("click", closeDrawer);
  cancelBtn.addEventListener("click", closeDrawer);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && drawer.classList.contains("is-open")) closeDrawer();
  });

  cat.addEventListener("change", () => {
    const isNew = cat.value === "__new__";
    newWrap.hidden = !isNew;
    if (isNew) {
      newCat.focus();
      setCoach("พิมพ์ชื่อหมวดใหม่ให้สั้นและชัด เช่น ทุนวิจัย จากนั้นไปกรอกคำถามในช่องถัดไป");
    } else if (cat.value) {
      setCoach("เลือกหมวดแล้ว ต่อไปพิมพ์คำถามในช่องที่ 2 ให้เหมือนที่ผู้ใช้จะถาม");
      form.question.focus();
    } else {
      setCoach("ยังไม่ได้เลือกหมวด กรุณาเลือกก่อน เพื่อให้การตอบคำถามแม่นขึ้น");
    }
  });

  form.question.addEventListener("input", () => {
    const n = form.question.value.trim().length;
    setCoach(n < 8
      ? "คำถามยังสั้นไป ลองเขียนเป็นประโยคที่นักศึกษาพิมพ์จริง เช่น เลื่อนสอบทำอย่างไร"
      : "คำถามชัดแล้ว ต่อไปใส่คีย์เวิร์ดสั้นๆ ที่มักปรากฏในคำถามนี้");
  });

  function renderKw(source) {
    const parts = String(source || "").split(/[,;]+/).map((s) => s.trim()).filter(Boolean);
    kwPreview.innerHTML = parts.map((k) => `<span class="kw-chip">${escapeHtml(k)}</span>`).join("");
  }
  form.keyword.addEventListener("input", () => {
    renderKw(form.keyword.value);
    const n = form.keyword.value.split(/[,;]+/).filter((s) => s.trim()).length;
    setCoach(n === 0
      ? "พิมพ์คีย์เวิร์ดอย่างน้อย 1 คำ คั่นด้วยจุลภาค เช่น ค่าเทอม, ค่าธรรมเนียม"
      : `มีคีย์เวิร์ด ${n} คำ ยิ่งเฉพาะเจาะจง ระบบยิ่งจับคำถามได้แม่น`);
  });
  form.keyword_back.addEventListener("input", () => {
    setCoach("คีย์เวิร์ดสำรองใช้จับคำพ้อง เช่น ที่อยู่ กับ ที่ตั้ง ไม่บังคับถ้าคีย์เวิร์ดหลักครบแล้ว");
  });
  form.answer.addEventListener("input", () => {
    const n = form.answer.value.trim().length;
    setCoach(n < 12
      ? "คำตอบยังสั้น ใส่ขั้นตอน เบอร์โทร หรือลิงก์ให้ผู้ใช้ทำต่อได้เลย"
      : "คำตอบพร้อมแล้ว ตรวจลิงก์และเบอร์โทรอีกครั้ง แล้วกดบันทึก");
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    if (data.category === "__new__") {
      data.category = (newCat.value || "").trim();
    }
    if (!data.category) {
      msg.innerHTML = `<div class="alert err">กรุณาเลือกหมวดก่อนบันทึก</div>`;
      setCoach("ยังไม่ได้เลือกหมวด เลือกจากรายการ หรือเพิ่มหมวดใหม่ด้านบนสุดของแบบฟอร์ม");
      cat.focus();
      return;
    }
    const editing = !!(data.id && String(data.id).trim());
    saveBtn.disabled = true;
    msg.innerHTML = "";
    setCoach("กำลังบันทึก กรุณารอสักครู่...");
    try {
      const res = await fetch(editing ? "../api/update.php" : "../api/add.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
      });
      const json = await res.json();
      if (!json.ok) {
        msg.innerHTML = `<div class="alert err">${json.error || "บันทึกไม่สำเร็จ"}</div>`;
        setCoach(json.error || "บันทึกไม่สำเร็จ ตรวจช่องที่ยังว่างแล้วลองอีกครั้ง");
        return;
      }
      toast(json.message || "บันทึกเรียบร้อย", true);
      if (editing) replaceRow(json.item);
      else prependRow(json.item);
      closeDrawer();
      setCoach(editing
        ? "แก้ไขแล้ว SCiGENIE จะใช้คำตอบใหม่ทันที ลองค้นหารายการนี้ในตารางได้"
        : "เพิ่มคำถามแล้ว รายการใหม่อยู่บนสุดของตาราง สามารถกดแก้ไขหรือลบได้");
      applyFilter();
    } catch (err) {
      msg.innerHTML = `<div class="alert err">เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ</div>`;
      setCoach("เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ ตรวจว่า Docker หรือ Apache ยังทำงานอยู่");
    } finally {
      saveBtn.disabled = false;
    }
  });

  document.getElementById("faq-body").addEventListener("click", async (e) => {
    const editBtn = e.target.closest(".edit");
    const delBtn = e.target.closest(".del");
    const previewBtn = e.target.closest(".preview");
    const tr = e.target.closest("tr");
    if (!tr) return;

    if (editBtn) {
      e.stopPropagation();
      fillForm(readItem(tr));
      openDrawer("edit");
      return;
    }
    if (delBtn) {
      e.stopPropagation();
      const item = readItem(tr);
      if (!item.id) return;
      setCoach("กำลังยืนยันการลบ หากลบแล้วคำถามนี้จะไม่ถูกนำไปตอบอีก");
      if (!confirm(`ต้องการลบคำถามนี้หรือไม่?\n\n${item.question}\n\nหลังลบ SCiGENIE จะไม่ใช้คำตอบนี้`)) {
        setCoach("ยกเลิกการลบแล้ว รายการยังอยู่ในคลัง");
        return;
      }
      try {
        const res = await fetch("../api/delete.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ csrf, id: item.id })
        });
        const json = await res.json();
        if (!json.ok) {
          toast(json.error || "ลบไม่สำเร็จ", false);
          setCoach(json.error || "ลบไม่สำเร็จ ลองใหม่อีกครั้ง");
          return;
        }
        tr.remove();
        if (faqId.value === String(item.id)) closeDrawer();
        toast(json.message || "ลบคำถามแล้ว", true);
        setCoach("ลบแล้ว ตารางอัปเดตทันที สามารถเพิ่มคำถามใหม่แทนได้");
        applyFilter();
      } catch (err) {
        toast("เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ", false);
      }
      return;
    }

    document.querySelectorAll(".faq-row.is-open").forEach((row) => {
      if (row !== tr) row.classList.remove("is-open");
    });
    tr.classList.toggle("is-open");
    setCoach(tr.classList.contains("is-open")
      ? "กำลังดูคำตอบเต็ม กดดินสอหากต้องการแก้ไข หรือกดแถวอีกครั้งเพื่อย่อ"
      : DEFAULT_COACH);
  });

  function readItem(tr) {
    try {
      return JSON.parse(tr.dataset.item || "{}");
    } catch (e) {
      return {};
    }
  }

  function fillForm(item) {
    faqId.value = item.id || "";
    if (item.category && ![...cat.options].some((o) => o.value === item.category)) {
      const opt = document.createElement("option");
      opt.value = item.category;
      opt.textContent = item.category;
      cat.insertBefore(opt, cat.lastElementChild);
    }
    cat.value = item.category || "";
    newWrap.hidden = true;
    form.question.value = item.question || "";
    form.keyword.value = item.keyword || "";
    form.keyword_back.value = item.keyword_back || "";
    form.answer.value = item.answer || "";
    saveLabel.textContent = "บันทึกการแก้ไข";
    renderKw(form.keyword.value);
    msg.innerHTML = `<div class="alert">กำลังแก้ไขรายการนี้ ตรวจคีย์เวิร์ดและคำตอบให้ครบก่อนบันทึก</div>`;
  }

  function resetForm() {
    faqId.value = "";
    saveLabel.textContent = "บันทึกคำถาม";
    newWrap.hidden = true;
    form.question.value = "";
    form.keyword.value = "";
    form.keyword_back.value = "";
    form.answer.value = "";
    cat.value = "";
    kwPreview.innerHTML = "";
    msg.innerHTML = "";
  }

  function rowHtml(item) {
    return `<td><span class="tag">${escapeHtml(item.category || "")}</span></td>
      <td>
        <div class="cell-q">${escapeHtml(item.question || "")}</div>
        <button type="button" class="linkish preview" data-tip="คลิกเพื่อขยายดูคำตอบเต็มในแถวนี้">ดูคำตอบเต็ม</button>
      </td>
      <td><div class="cell-a">${escapeHtml(item.answer || "")}</div></td>
      <td><div class="cell-k">${escapeHtml(item.keyword || "")}</div></td>
      <td><div class="actions">
        <button type="button" class="icon-act edit" data-tip="เปิดแบบฟอร์มด้านขวา พร้อมข้อมูลคำถามนี้ให้แก้ไข" aria-label="แก้ไข">${ICONS.edit}</button>
        <button type="button" class="icon-act del" data-tip="ลบคำถามนี้ออกจากคลัง SCiGENIE จะไม่ตอบข้อนี้อีก" aria-label="ลบ">${ICONS.del}</button>
      </div></td>`;
  }

  function bindRow(tr, item) {
    tr.classList.add("faq-row");
    tr.dataset.id = item.id;
    tr.dataset.cat = item.category || "";
    tr.dataset.text = `${item.category} ${item.question} ${item.keyword} ${item.keyword_back || ""} ${item.answer || ""}`.toLowerCase();
    tr.dataset.item = JSON.stringify(item);
    tr.innerHTML = rowHtml(item);
  }

  function prependRow(item) {
    const tbody = document.getElementById("faq-body");
    const tr = document.createElement("tr");
    bindRow(tr, item);
    tbody.prepend(tr);
  }

  function replaceRow(item) {
    const tr = document.querySelector(`#faq-body tr[data-id="${CSS.escape(String(item.id))}"]`);
    if (tr) bindRow(tr, item);
    else prependRow(item);
  }

  function toast(text, ok) {
    listMsg.hidden = false;
    listMsg.innerHTML = `<div class="alert ${ok ? "ok" : "err"}">${escapeHtml(text)}</div>`;
    setTimeout(() => { listMsg.hidden = true; }, 4200);
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  }

  function applyFilter() {
    const c = filterCat.value;
    const q = (filterQ.value || "").toLowerCase().trim();
    let shown = 0;
    document.querySelectorAll("#faq-body tr").forEach((tr) => {
      const okCat = !c || tr.dataset.cat === c;
      const okQ = !q || (tr.dataset.text || "").includes(q);
      const show = okCat && okQ;
      tr.style.display = show ? "" : "none";
      if (show) shown += 1;
    });
    resultCount.textContent = shown + " รายการ";
    emptyState.hidden = shown !== 0;
  }
  filterCat.addEventListener("change", () => {
    applyFilter();
    setCoach(filterCat.value
      ? `กำลังแสดงเฉพาะหมวดนี้ หากไม่พบรายการ ลองเปลี่ยนเป็นทุกหมวด หรือพิมพ์คำค้น`
      : "แสดงทุกหมวดแล้ว ใช้ช่องค้นหาเพื่อหาคำถามเร็วขึ้น");
  });
  filterQ.addEventListener("input", () => {
    applyFilter();
    setCoach(filterQ.value.trim()
      ? "กำลังกรองตามคำที่พิมพ์ ตารางจะเหลือเฉพาะรายการที่ตรง"
      : "ล้างคำค้นแล้ว แสดงรายการตามหมวดที่เลือก");
  });

  try {
    const params = new URLSearchParams(location.search);
    if (params.get("add") === "1") {
      resetForm();
      const categoryName = (params.get("c") || "").trim();
      const questionText = (params.get("q") || "").trim();
      if (categoryName) {
        const has = Array.from(cat.options).some((o) => o.value === categoryName);
        if (has) cat.value = categoryName;
      }
      if (questionText) {
        form.question.value = questionText;
        if (!form.keyword.value) {
          form.keyword.value = questionText.split(/[\s,]+/).filter((w) => w.length >= 2).slice(0, 6).join(", ");
          renderKw(form.keyword.value);
        }
      }
      openDrawer("add");
      if (form.answer) form.answer.focus();
    }
  } catch (e) {}
})();
