(function () {
  const body = document.body;
  const token = body.dataset.token || "";
  const form = document.getElementById("ask-form");
  const input = document.getElementById("ask-input");
  const answerBox = document.getElementById("answer-box");
  let category = body.dataset.category || "";

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
    return s.replace(/\n/g, "<br>");
  }

  function grow() {
    if (!input) return;
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 96) + "px";
  }

  function renderAnswer(question, data) {
    if (!answerBox) return;
    if (data.select_category) {
      window.location.href = "menu.php?t=" + encodeURIComponent(token) + "&c=" + encodeURIComponent(data.select_category);
      return;
    }
    answerBox.hidden = false;
    const asked = question ? `<p class="asked"><span>คำถาม</span>${escapeHtml(question)}</p>` : "";
    if (data.matched && data.item) {
      const item = data.item;
      const other = data.other_category ? `<div class="answer-kicker">พบในหมวด «${escapeHtml(item.category || "")}»</div>` : "";
      answerBox.innerHTML = `${asked}<article class="answer">${other}<div class="answer-kicker">คำตอบ</div><div class="answer-body">${formatAnswer(item.answer || "")}</div></article>`;
    } else {
      answerBox.innerHTML = `${asked}<article class="answer"><div class="answer-kicker">คำตอบ</div><div class="answer-body">${formatAnswer(data.message || "ยังไม่พบคำตอบที่ตรงกัน")}</div></article>`;
    }
    answerBox.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  async function ask(text) {
    const q = (text || "").trim();
    if (!q || !token || !category) return;
    answerBox.hidden = false;
    answerBox.innerHTML = '<article class="answer"><p class="answer-body">กำลังค้นหาคำตอบ...</p></article>';
    try {
      const res = await fetch("menu.php?t=" + encodeURIComponent(token), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ question: q, category: category })
      });
      const data = await res.json();
      if (data.category) {
        category = data.category;
        body.dataset.category = category;
      }
      renderAnswer(q, data);
    } catch (e) {
      answerBox.innerHTML = '<article class="answer"><div class="answer-kicker">คำตอบ</div><p class="answer-body">ระบบตอบคำถามขัดข้องชั่วคราว กรุณาลองอีกครั้ง หรือโทร 043-009-700</p></article>';
    }
  }

  if (form && input) {
    input.addEventListener("input", grow);
    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        form.requestSubmit();
      }
    });
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const q = input.value.trim();
      input.value = "";
      grow();
      ask(q);
    });
  }
})();
