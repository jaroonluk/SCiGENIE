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

    return json_({ ok: false, error: 'unknown action' });
  } catch (err) {
    return json_({ ok: false, error: String(err) });
  }
}

function json_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
