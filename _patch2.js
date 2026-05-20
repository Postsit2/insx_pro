const fs = require('fs');
const path = 'C:\\Users\\tom_d\\Desktop\\webapp\\download\\index.html';
let content = fs.readFileSync(path, 'utf8');

// 1. Remove addLineView HTML section
const addLineStart = content.indexOf('<!-- ADD LINE VIEW -->');
const addLineEnd = content.indexOf('<!-- DATA TABLE VIEW (ข้อมูลจากตาราง DATA) -->');
if (addLineStart >= 0 && addLineEnd >= 0) {
    content = content.substring(0, addLineStart) + content.substring(addLineEnd);
    console.log('OK: addLineView HTML removed');
}

// 2. Remove addLine event listeners
content = content.replace(/\s*document\.getElementById\('dashCardAddLine'\)\.addEventListener\(.*?\}\);/s, '');
content = content.replace(/\s*document\.getElementById\('addLineBackBtn'\)\.addEventListener\(.*?\}\);/s, '');
content = content.replace(/\s*document\.getElementById\('btnClearAddLineView'\)\.addEventListener\(.*?\}\);/s, '');
content = content.replace(/\s*document\.getElementById\('addLineFormView'\)\.addEventListener\('submit'.*?\}\);\s*\n/s, '');
console.log('OK: addLine event listeners removed');

// 3. Fix dtCopyRow - copy to cable_points (insert via API)
const oldCopy = `function dtCopyRow(idx) {
            const row = dataTableFiltered[idx];
            if (!row) return;
            const text = Object.keys(row).map(k => k + ': ' + (row[k] || '-')).join('\\n');
            navigator.clipboard.writeText(text).then(() => {
                showToast('✅ คัดลอกข้อมูลแล้ว', 'success');
            }).catch(() => {
                showToast('❌ ไม่สามารถคัดลอกได้', 'error');
            });
        }`;

const newCopy = `function dtCopyRow(idx) {
            const row = dataTableFiltered[idx];
            if (!row) return;
            // คัดลอกข้อมูลจาก DATA ไปเป็นรายการใหม่ใน cable_points
            const payload = {
                id: uuidv4(),
                'วันที่': new Date().toISOString().split('T')[0],
                'หมวด': row['หมวด'] || '',
                'สายจดหน่วย': row['สายจดหน่วย'] || '',
                'ตำบล': row['ตำบล'] || '',
                'พื้นที่ทำงาน': row['พื้นที่ทำงาน'] || '',
                'หมู่บ้าน': row['หมู่บ้าน'] || '',
                'หมายเหตุ': row['หมายเหตุ'] || '',
                'STATUS': 'รอดำเนินการ',
                'คำแนะนำการวิ่ง': row['คำแนะนำการวิ่ง'] || '',
                'พิกัดต้นสาย': row['พิกัดต้นสาย'] || '',
            };
            fetch(API_URL + '?action=append', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ data: payload })
            }).then(r => r.json()).then(result => {
                if (result.success) {
                    showToast('✅ คัดลอกไปยังจุดรับสายแล้ว', 'success');
                    // รีโหลด main data ถ้าอยู่ใน main view
                    if (typeof loadData === 'function') loadData();
                } else {
                    showToast('❌ ไม่สามารถคัดลอกได้: ' + (result.error || ''), 'error');
                }
            }).catch(err => {
                showToast('❌ ไม่สามารถคัดลอกได้: ' + err.message, 'error');
            });
        }`;

if (content.includes(oldCopy)) {
    content = content.replace(oldCopy, newCopy);
    console.log('OK: dtCopyRow updated to insert into cable_points');
} else {
    console.log('WARN: dtCopyRow not found, trying partial match');
    // Try to find and replace just the function
    const copyIdx = content.indexOf('function dtCopyRow(idx)');
    if (copyIdx >= 0) {
        const copyEnd = content.indexOf('\n        }', copyIdx);
        if (copyEnd >= 0) {
            content = content.substring(0, copyIdx) + newCopy + content.substring(copyEnd + 10);
            console.log('OK: dtCopyRow replaced via partial match');
        }
    }
}

// 4. Fix dtAddRow - insert into cable_points instead of just opening form
const oldAddRow = content.match(/function dtAddRow\(idx\) \{[\s\S]*?\n        \}/);
if (oldAddRow) {
    const newAddRow = `function dtAddRow(idx) {
            const row = dataTableFiltered[idx];
            if (!row) return;
            // เพิ่มข้อมูลจาก DATA ไปยัง cable_points โดยตรง
            const payload = {
                id: uuidv4(),
                'วันที่': new Date().toISOString().split('T')[0],
                'หมวด': row['หมวด'] || '',
                'สายจดหน่วย': row['สายจดหน่วย'] || '',
                'ตำบล': row['ตำบล'] || '',
                'พื้นที่ทำงาน': row['พื้นที่ทำงาน'] || '',
                'หมู่บ้าน': row['หมู่บ้าน'] || '',
                'หมายเหตุ': row['หมายเหตุ'] || '',
                'STATUS': 'รอดำเนินการ',
                'คำแนะนำการวิ่ง': row['คำแนะนำการวิ่ง'] || '',
                'พิกัดต้นสาย': row['พิกัดต้นสาย'] || '',
            };
            fetch(API_URL + '?action=append', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ data: payload })
            }).then(r => r.json()).then(result => {
                if (result.success) {
                    showToast('✅ เพิ่มข้อมูลใหม่แล้ว', 'success');
                    if (typeof loadData === 'function') loadData();
                } else {
                    showToast('❌ ไม่สามารถเพิ่มได้: ' + (result.error || ''), 'error');
                }
            }).catch(err => {
                showToast('❌ ไม่สามารถเพิ่มได้: ' + err.message, 'error');
            });
        }`;
    content = content.replace(oldAddRow[0], newAddRow);
    console.log('OK: dtAddRow updated to insert into cable_points');
}

// 5. Fix dtAddNewFromData - insert blank record into cable_points
const oldAddNew = content.match(/function dtAddNewFromData\(\) \{[\s\S]*?\n        \}/);
if (oldAddNew) {
    const newAddNew = `function dtAddNewFromData() {
            // เพิ่มรายการว่างใหม่ใน cable_points สำหรับวันนี้
            const payload = {
                id: uuidv4(),
                'วันที่': new Date().toISOString().split('T')[0],
                'หมวด': '',
                'สายจดหน่วย': '',
                'ตำบล': '',
                'พื้นที่ทำงาน': '',
                'หมู่บ้าน': '',
                'หมายเหตุ': '',
                'STATUS': 'รอดำเนินการ',
                'คำแนะนำการวิ่ง': '',
                'พิกัดต้นสาย': '',
            };
            fetch(API_URL + '?action=append', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ data: payload })
            }).then(r => r.json()).then(result => {
                if (result.success) {
                    showToast('✅ เพิ่มรายการใหม่แล้ว — ไปที่ "จุดรับสาย" เพื่อแก้ไข', 'success');
                    if (typeof loadData === 'function') loadData();
                } else {
                    showToast('❌ ไม่สามารถเพิ่มได้: ' + (result.error || ''), 'error');
                }
            }).catch(err => {
                showToast('❌ ไม่สามารถเพิ่มได้: ' + err.message, 'error');
            });
        }`;
    content = content.replace(oldAddNew[0], newAddNew);
    console.log('OK: dtAddNewFromData updated to insert into cable_points');
}

fs.writeFileSync(path, content, 'utf8');
console.log('DONE');
