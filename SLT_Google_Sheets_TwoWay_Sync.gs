/**
 * ============================================================================
 * SMART LIFE TECK (SLT) — 2-WAY GOOGLE SHEETS ↔ WORDPRESS SYNC ENGINE
 * ============================================================================
 * Connected Sheets:
 * 1) Master Sheet (SLT IMS KSA): PASTE_YOUR_MASTER_SHEET_ID_HERE
 *    Tabs: 1_Purchase_Entry, 2_Sales_Entry, 3_Live_Stock, 6_WooCommerce
 * 2) Team Live Stock Sheet: PASTE_YOUR_TEAM_SHEET_ID_HERE
 * 3) SLT Auto-Post Queue Sheet: PASTE_YOUR_QUEUE_SHEET_ID_HERE
 *
 * HOW TO ACTIVATE IN 1 MINUTE:
 * 1. Open SLT IMS KSA in Google Sheets -> Click Extensions -> Apps Script.
 * 2. Paste this entire file into Code.gs and click Save.
 * 3. Update WP_SITE_URL and SLT_SECRET_TOKEN below with the values from Tab 6 of your WordPress Plugin.
 * 4. Click Deploy -> New deployment -> Web app -> Execute as: Me -> Who has access: Anyone -> Deploy.
 * 5. Copy the Web App URL and paste it into Tab 6 of your WordPress Plugin.
 * ============================================================================
 */

const WP_SITE_URL       = 'https://smartlifeteck.com';
const SLT_SECRET_TOKEN  = 'PASTE_YOUR_WP_PLUGIN_SECRET_TOKEN_HERE';
const MASTER_SHEET_ID   = 'PASTE_YOUR_MASTER_SHEET_ID_HERE';
const TEAM_SHEET_ID     = 'PASTE_YOUR_TEAM_SHEET_ID_HERE';
const QUEUE_SHEET_ID    = 'PASTE_YOUR_QUEUE_SHEET_ID_HERE';

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('🚀 SLT WordPress Sync')
    .addItem('🔄 Sync All Inventory & Sales to WordPress Now', 'pushAllSheetsToWordPress')
    .addItem('📋 Update Team Live Stock Sheet Now', 'syncMasterToTeamLiveStock')
    .addToUi();
}

/**
 * Automatic trigger when any row in 1_Purchase_Entry or 2_Sales_Entry is edited
 */
function onEdit(e) {
  if (!e || !e.range) return;
  const sheet = e.range.getSheet();
  const sheetName = sheet.getName();
  const row = e.range.getRow();
  if (row < 2) return;

  if (sheetName === '1_Purchase_Entry' || sheetName === '2_Sales_Entry') {
    syncMasterToTeamLiveStock();
    pushAllSheetsToWordPress();
  }
}

/**
 * Handles GET requests when WordPress clicks "1-Click Pull from Google Sheet"
 */
function doGet(e) {
  const ss = SpreadsheetApp.openById(MASTER_SHEET_ID);
  const payload = {
    success: true,
    purchases: readPurchaseRows(ss),
    sales: readSalesRows(ss)
  };
  return ContentService.createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * Handles POST webhooks from WordPress when a Purchase, Sale, or Post Queue item is added in WP
 */
function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    const action = body.action || '';
    const ss = SpreadsheetApp.openById(MASTER_SHEET_ID);

    if (action === 'upsert_purchase' && body.item) {
      upsertPurchaseRowInSheet(ss, body.item);
      syncMasterToTeamLiveStock();
      return jsonResponse({ success: true, message: 'Purchase synced to 1_Purchase_Entry & Team Live Stock' });
    }

    if (action === 'upsert_sale' && body.sale) {
      upsertSaleRowInSheet(ss, body.sale);
      syncMasterToTeamLiveStock();
      return jsonResponse({ success: true, message: 'Sale synced to 2_Sales_Entry & Team Live Stock' });
    }

    if (action === 'append_post_queue' && body.post_item) {
      appendPostQueueInSheet(body.post_item);
      return jsonResponse({ success: true, message: 'Post added to SLT Auto-Post Queue' });
    }

    if (action === 'full_push_from_wp') {
      if (Array.isArray(body.purchases)) {
        body.purchases.forEach(function(item) { upsertPurchaseRowInSheet(ss, item); });
      }
      if (Array.isArray(body.sales)) {
        body.sales.forEach(function(sale) { upsertSaleRowInSheet(ss, sale); });
      }
      syncMasterToTeamLiveStock();
      return jsonResponse({ success: true, message: 'Full WordPress database synced to Google Sheets' });
    }

    return jsonResponse({ success: false, message: 'Unknown action' });
  } catch (err) {
    return jsonResponse({ success: false, message: err.toString() });
  }
}

function upsertPurchaseRowInSheet(ss, item) {
  const ws = ss.getSheetByName('1_Purchase_Entry');
  if (!ws) return;
  const lastRow = Math.max(ws.getLastRow(), 1);
  const skus = lastRow > 1 ? ws.getRange(2, 1, lastRow - 1, 1).getValues().map(function(r) { return String(r[0]).trim().toUpperCase(); }) : [];
  const targetSku = String(item.sku || '').trim().toUpperCase();
  let rowIdx = skus.indexOf(targetSku);
  const r = (rowIdx !== -1) ? (rowIdx + 2) : (skus.filter(Boolean).length + 2);

  ws.getRange(r, 1).setValue(targetSku);
  ws.getRange(r, 2).setValue(item.brand || 'Microsoft');
  ws.getRange(r, 3).setValue(item.model_name || '');
  ws.getRange(r, 4).setValue(item.touch_type || 'Touch');
  ws.getRange(r, 5).setValue(item.processor || '');
  ws.getRange(r, 6).setValue(item.generation || '');
  ws.getRange(r, 7).setValue(item.ram || '');
  ws.getRange(r, 8).setValue(item.storage || '');
  ws.getRange(r, 9).setValue(item.display_size || '');
  ws.getRange(r, 10).setValue(item.graphics || '');
  ws.getRange(r, 11).setValue(item.software || 'Windows 11 Pro, MS Office, PDF Editor');
  ws.getRange(r, 12).setFormula('=IF(A' + r + '="","",B' + r + '&" "&C' + r + '&" , "&E' + r + '&" "&F' + r + '&"th "&G' + r + '&"/"&H' + r + '&" "&I' + r + '&" "&D' + r + '&"  "&J' + r + ')');
  ws.getRange(r, 13).setValue(Number(item.purchased_qty || 0));
  ws.getRange(r, 14).setValue(Number(item.unit_cost || 0));
  ws.getRange(r, 15).setValue(Number(item.shipping_cost || 200));
  ws.getRange(r, 16).setFormula('=IF(A' + r + '="","",N' + r + '+O' + r + ')');
  ws.getRange(r, 17).setFormula('=IF(A' + r + '="","",M' + r + '*P' + r + ')');
  ws.getRange(r, 18).setValue(Number(item.retail_price || 0));
  ws.getRange(r, 19).setValue(Number(item.discount || 0));
  ws.getRange(r, 20).setValue(Number(item.regular_price || 0));
  ws.getRange(r, 21).setValue(item.supplier_name || 'Authorized Supplier');
  ws.getRange(r, 22).setValue(item.purchase_date || new Date());
  ws.getRange(r, 23).setFormula('=IF(A' + r + '="","",M' + r + '*N' + r + ')');
  ws.getRange(r, 24).setValue(item.long_spec || '');
  ws.getRange(r, 25).setValue(item.arabic_spec || '');
  if (item.photos_url) {
    ws.getRange(r, 26).setFormula('=HYPERLINK("' + item.photos_url + '", "PICS")');
  }
  if (item.video_url) {
    ws.getRange(r, 27).setFormula('=HYPERLINK("' + item.video_url + '", "Watch Video")');
  }
  if (item.zip_url) {
    ws.getRange(r, 28).setFormula('=HYPERLINK("' + item.zip_url + '", "📥 Download ZIP")');
  }
  ws.getRange(r, 29).setValue(item.product_status || 'Active');
  SpreadsheetApp.flush();
}

function upsertSaleRowInSheet(ss, sale) {
  const ws = ss.getSheetByName('2_Sales_Entry');
  if (!ws) return;
  const lastRow = Math.max(ws.getLastRow(), 1);
  const ids = lastRow > 1 ? ws.getRange(2, 1, lastRow - 1, 1).getValues().map(function(r) { return String(r[0]).trim(); }) : [];
  const targetId = String(sale.order_id || '').trim();
  let rowIdx = ids.indexOf(targetId);
  const r = (rowIdx !== -1) ? (rowIdx + 2) : (ids.filter(Boolean).length + 2);

  ws.getRange(r, 1).setValue(targetId);
  ws.getRange(r, 2).setValue(sale.customer_name || '');
  ws.getRange(r, 3).setValue(sale.customer_city || '');
  ws.getRange(r, 4).setValue(sale.sku || '');
  ws.getRange(r, 5).setFormula('=IF(D' + r + '="","",IFERROR(VLOOKUP(D' + r + ",'1_Purchase_Entry'!$A$2:$AB$501,12,FALSE),\"\"))");
  ws.getRange(r, 6).setValue(Number(sale.sold_qty || 1));
  ws.getRange(r, 7).setValue(Number(sale.unit_sold_price || 0));
  ws.getRange(r, 8).setValue(Number(sale.shipping_fee || 0));
  ws.getRange(r, 9).setFormula('=IF(A' + r + '="","",IF(OR(P' + r + '="Cancelled",P' + r + '="Returned"),0,F' + r + '*G' + r + '))');
  ws.getRange(r, 10).setValue(Number(sale.amount_received || 0));
  ws.getRange(r, 11).setFormula('=IF(A' + r + '="","",I' + r + '-J' + r + ')');
  ws.getRange(r, 12).setValue(sale.sale_date || new Date());
  ws.getRange(r, 13).setFormula('=IF(A' + r + '="","",IF(K' + r + '<=0,"",IF(L' + r + '="","",L' + r + '+30)))');
  ws.getRange(r, 14).setFormula('=IF(A' + r + '="","",IF(K' + r + '<=0,"Paid",IF(J' + r + '>0,"Partial","Pending")))');
  ws.getRange(r, 16).setValue(sale.order_status || 'Completed');
  ws.getRange(r, 17).setValue(sale.sold_by || 'Shahzad');
  ws.getRange(r, 18).setValue(sale.sales_channel || 'WhatsApp');
  ws.getRange(r, 19).setValue(sale.payment_method || 'COD');
  ws.getRange(r, 20).setFormula('=IF(A' + r + '="","",IF(P' + r + '="Completed",F' + r + '*IFERROR(VLOOKUP(D' + r + ",'1_Purchase_Entry'!$A$2:$AB$501,16,FALSE),0),0))");
  ws.getRange(r, 21).setFormula('=IF(A' + r + '="","",IF(P' + r + '="Completed",I' + r + '-T' + r + '-H' + r + ',0))');
  ws.getRange(r, 22).setValue(sale.notes || '');
  ws.getRange(r, 23).setFormula('=IF(L' + r + '="","",TEXT(L' + r + ',"yyyy-mm"))');
  ws.getRange(r, 24).setFormula('=IF(L' + r + '="","",YEAR(L' + r + '))');
  ws.getRange(r, 25).setValue(sale.customer_contact || '');
  SpreadsheetApp.flush();
}

function appendPostQueueInSheet(postItem) {
  try {
    const qss = SpreadsheetApp.openById(QUEUE_SHEET_ID);
    const ws = qss.getSheets()[0];
    ws.appendRow([
      postItem.row_id || 'POST-NEW',
      postItem.schedule_time || '',
      postItem.sku || '',
      '',
      postItem.video_url || '',
      postItem.title || '',
      postItem.short_caption || '',
      postItem.full_desc || '',
      postItem.platforms || 'YouTube, TikTok, Facebook, Instagram',
      postItem.status || 'Ready'
    ]);
  } catch (e) {}
}

/**
 * Syncs live public columns from SLT IMS KSA (3_Live_Stock) to Team Live Stock sheet
 */
function syncMasterToTeamLiveStock() {
  try {
    const masterSS = SpreadsheetApp.openById(MASTER_SHEET_ID);
    const liveSheet = masterSS.getSheetByName('3_Live_Stock');
    const teamSS = SpreadsheetApp.openById(TEAM_SHEET_ID);
    const teamSheet = teamSS.getSheetByName('Live Stock') || teamSS.getSheets()[0];
    if (!liveSheet || !teamSheet) return;

    const values = liveSheet.getRange(1, 1, 15, 12).getValues();
    const formulas = liveSheet.getRange(1, 1, 15, 12).getFormulas();
    teamSheet.getRange(1, 1, 15, 12).setValues(values);
  } catch (e) {}
}

function readPurchaseRows(ss) {
  const ws = ss.getSheetByName('1_Purchase_Entry');
  const liveWs = ss.getSheetByName('3_Live_Stock');
  if (!ws) return [];
  const data = ws.getDataRange().getValues();
  const formulas = ws.getDataRange().getFormulas();
  const liveMap = {};
  if (liveWs) {
    const lData = liveWs.getDataRange().getValues();
    for (let i = 1; i < lData.length; i++) {
      if (lData[i][0]) liveMap[String(lData[i][0]).trim()] = String(lData[i][10] || '');
    }
  }
  const list = [];
  for (let r = 1; r < data.length; r++) {
    const sku = String(data[r][0] || '').trim();
    if (!sku) continue;
    const statusStr = liveMap[sku] || '';
    const m = statusStr.match(/\((\d+)\)/);
    const liveQty = m ? parseInt(m[1], 10) : Number(data[r][12] || 0);
    list.push({
      sku: sku,
      brand: data[r][1],
      model_name: data[r][2],
      touch_type: data[r][3],
      processor: data[r][4],
      generation: String(data[r][5] || ''),
      ram: String(data[r][6] || ''),
      storage: data[r][7],
      display_size: data[r][8],
      graphics: data[r][9],
      software: data[r][10],
      short_spec: data[r][11],
      purchased_qty: Number(data[r][12] || 0),
      live_qty: liveQty,
      unit_cost: Number(data[r][13] || 0),
      shipping_cost: Number(data[r][14] || 200),
      retail_price: Number(data[r][17] || 0),
      discount: Number(data[r][18] || 0),
      regular_price: Number(data[r][19] || 0),
      supplier_name: data[r][20],
      long_spec: data[r][23],
      arabic_spec: data[r][24],
      photos_url: extractLink(formulas[r][25]),
      video_url: extractLink(formulas[r][26]),
      zip_url: extractLink(formulas[r][27]),
      product_status: data[r][28] || 'Active'
    });
  }
  return list;
}

function readSalesRows(ss) {
  const ws = ss.getSheetByName('2_Sales_Entry');
  if (!ws) return [];
  const data = ws.getDataRange().getValues();
  const list = [];
  for (let r = 1; r < data.length; r++) {
    const orderId = String(data[r][0] || '').trim();
    if (!orderId) continue;
    list.push({
      order_id: orderId,
      customer_name: data[r][1],
      customer_city: data[r][2],
      sku: data[r][3],
      short_spec: data[r][4],
      sold_qty: Number(data[r][5] || 1),
      unit_sold_price: Number(data[r][6] || 0),
      shipping_fee: Number(data[r][7] || 0),
      amount_received: Number(data[r][9] || 0),
      order_status: data[r][15] || 'Pending',
      sold_by: data[r][16] || 'Shahzad',
      sales_channel: data[r][17] || 'WhatsApp',
      payment_method: data[r][18] || 'COD',
      notes: data[r][21] || '',
      customer_contact: data[r][24] || ''
    });
  }
  return list;
}

function pushAllSheetsToWordPress() {
  const ss = SpreadsheetApp.openById(MASTER_SHEET_ID);
  const payload = {
    action: 'bulk_sync_from_sheets',
    purchases: readPurchaseRows(ss),
    sales: readSalesRows(ss)
  };
  UrlFetchApp.fetch(WP_SITE_URL + '/wp-json/slt-ims/v1/sync?token=' + encodeURIComponent(SLT_SECRET_TOKEN), {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });
}

function extractLink(formula) {
  if (!formula) return '';
  const m = String(formula).match(/HYPERLINK\(\s*"([^"]+)"/i);
  return m ? m[1] : '';
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
