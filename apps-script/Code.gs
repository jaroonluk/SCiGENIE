/**
 * SCiGENIE — Google Apps Script
 * วิธีติดตั้ง:
 * 1) เปิดชีต https://docs.google.com/spreadsheets/d/1OrEFXnfDNQawMcLHqwMoO6FqsbLkFjIgeQbWaUSp-9c
 * 2) ส่วนขยาย (Extensions) > Apps Script
 * 3) วางโค้ดนี้ทั้งไฟล์ แล้วบันทึก
 * 4) Deploy > New deployment > Type: Web app
 *    - Execute as: Me
 *    - Who has access: Anyone
 * 5) คัดลอก Web app URL ไปใส่ที่ includes/config.php ในคีย์ apps_script_url
 */

function doGet(e) {
  return json_({ ok: true, service: 'SCiGENIE', action: 'ping' });
}

function doPost(e) {
  try {
    const body = e.postData && e.postData.contents ? JSON.parse(e.postData.contents) : {};
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];
    const action = body.action || 'append';

    if (action === 'append') {
      const category = String(body.category || '').trim();
      const question = String(body.question || '').trim();
      const keyword = String(body.keyword || '').trim();
      const answer = String(body.answer || '').trim();
      const keywordBack = String(body.keyword_back || '').trim();

      if (!category || !question || !keyword || !answer) {
        return json_({ ok: false, error: 'missing fields' });
      }

      sheet.appendRow([category, question, keyword, answer, keywordBack]);
      return json_({ ok: true, row: sheet.getLastRow() });
    }

    if (action === 'update' || action === 'delete') {
      const originalCategory = String(body.original_category || body.category || '').trim();
      const originalQuestion = String(body.original_question || body.question || '').trim();
      const rowIndex = findRow_(sheet, originalCategory, originalQuestion);
      if (!rowIndex) {
        return json_({ ok: false, error: 'row not found' });
      }
      if (action === 'delete') {
        sheet.deleteRow(rowIndex);
        return json_({ ok: true, deleted: rowIndex });
      }
      sheet.getRange(rowIndex, 1, 1, 5).setValues([[
        String(body.category || '').trim(),
        String(body.question || '').trim(),
        String(body.keyword || '').trim(),
        String(body.answer || '').trim(),
        String(body.keyword_back || '').trim()
      ]]);
      return json_({ ok: true, row: rowIndex });
    }

    return json_({ ok: false, error: 'unknown action' });
  } catch (err) {
    return json_({ ok: false, error: String(err) });
  }
}

function findRow_(sheet, category, question) {
  const values = sheet.getDataRange().getValues();
  for (let i = 1; i < values.length; i++) {
    if (String(values[i][0] || '').trim() === category && String(values[i][1] || '').trim() === question) {
      return i + 1;
    }
  }
  return 0;
}

function json_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
