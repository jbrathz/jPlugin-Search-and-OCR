# jSearch – ระบบค้นหาสำหรับ WordPress และ PDF Content

[![License](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/wordpress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)](https://php.net/)

ปลั๊กอิน WordPress สำหรับค้นหาเนื้อหาแบบเต็มรูปแบบในบทความ หน้าเพจ และไฟล์ PDF พร้อมรองรับ OCR

---

## รายละเอียดเวอร์ชั่น

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](#)

### คุณสมบัติหลัก
- รองรับ **Built-in PDF Parser** ([Smalot/PdfParser v2.9.0](https://github.com/smalot/pdfparser))
- รองรับ 3 ช่องทาง (Google Drive File, Google Drive Folder, WordPress Media)
- ค้นหาแบบ global (รวมบทความและหน้าเพจทั้งหมด)
- ค้นหาแบบเต็มรูปแบบใน PDF และเนื้อหา WordPress
- ประมวลผลเป็นชุดพร้อมหยุด/ทำงานต่อ
- REST API พร้อม Rate Limiting
- Dashboard สำหรับผู้ดูแลระบบ

---

## Dependencies

### Built-in PDF Parser
- **Library:** [Smalot/PdfParser v2.9.0](https://github.com/smalot/pdfparser)
- **License:** LGPL v3
- **Location:** `includes/libs/vendor/`
- **Purpose:** แยกข้อความจาก PDF ดิจิทัล (ไม่สามารถใช้กับไฟล์ Scan ได้)

### OCR API (Optional - สำหรับ PDF สแกน)
สำหรับ PDF ที่เป็นภาพสแกน จำเป็นต้องใช้ OCR API Service:

**ตัวเลือก:**
1. **ใช้ SaaS Service** → Ready to use & auto-update → [it's me 🐘✈️](https://www.jirathsoft.com)
2. **พัฒนาเองง่าย ๆ** → ดูตัวอย่าง code เพื่อทำ API ที่ `includes/class-ocr-service.php`
3. **ใช้ API อื่น** → ปรับแต่ง class-ocr-service.php ให้เชื่อมต่อกับ API ที่ต้องการ

---

## Screenshots

### ตัวอย่างหน้า Manual OCR - จัดการงาน OCR และประมวลผลไฟล์

![Manual OCR Page](screenshot-manual-ocr.png)

- ตารางแสดง Active Jobs (งานที่กำลังทำ)
- สถานะงาน: COMPLETED, PAUSED พร้อม progress (%)
- ปุ่ม Continue/Cancel สำหรับควบคุมงาน
- 3 โหมด OCR: Google Drive File, Google Drive Folder, WordPress Media
- แสดง Job ID และเวลาที่สร้างงาน

---

## ความสามารถหลักของ Plugin

### การค้นหา
- ค้นหาแบบเต็มรูปแบบในเนื้อหา PDF, บทความ และหน้าเพจ
- 2 โหมดค้นหา: เฉพาะ PDF หรือค้นหาทั้งหมด (รวมบทความและหน้าเพจ)
- ไฮไลท์คำค้นหาในผลลัพธ์
- แคชผลการค้นหาเพื่อความเร็ว

### การประมวลผล PDF

**2 วิธีในการประมวลผล PDF:**

| วิธี | เหมาะสำหรับ | ต้องใช้ OCR API |
|------|------------|----------------|
| **Built-in Parser** | PDF ดิจิทัล (มีข้อความ) | ❌ ไม่ต้อง |
| **OCR API** | PDF สแกน (เป็นภาพ) | ✅ ต้องมี |

**3 โหมดให้เลือก:**

| โหมด | คำอธิบาย |
|------|----------|
| Google Drive File | ประมวลผลทีละไฟล์ (OCR API เท่านั้น) |
| Google Drive Folder | ประมวลผลทั้งโฟลเดอร์พร้อมกัน (OCR API เท่านั้น) |
| WordPress Media | ประมวลผล PDF จาก Media Library (เลือก Parser หรือ OCR API ได้) |

**คุณสมบัติของการประมวลผล:**
- ตรวจจับไฟล์ซ้ำอัตโนมัติ (ข้ามไฟล์ที่ประมวลผลแล้ว)
- ประมวลผลเป็นชุด (5 ไฟล์ต่อชุด)
- ติดตามความคืบหน้าแบบเรียลไทม์
- หยุดและทำงานต่อได้ทุกเมื่อ

### Auto-OCR (OCR อัตโนมัติ)
> เลือกเปิด / ปิด ได้ที่ Settings → Automation

Auto-OCR จะตรวจจับและประมวลผล PDF อัตโนมัติเมื่อบันทึกบทความจาก:
- URL ของ Google Drive (OCR API mode เท่านั้น)
- การฝัง Google Drive (embeds/iframes) (OCR API mode เท่านั้น)
- ไฟล์ PDF แนบ (ใช้ Parser หรือ OCR API ตามการตั้งค่า)
- PDF ฝังในเนื้อหา (ใช้ Parser หรือ OCR API ตามการตั้งค่า)

---

## ความต้องการขั้นต่ำ

| ระบบ | เวอร์ชันขั้นต่ำ |
|------|----------------|
| WordPress | 5.0+ |
| PHP | 7.4+ |
| MySQL | 5.6+ |

---

## การติดตั้ง

```bash
# 1. อัปโหลดโฟลเดอร์ jsearch ไปที่
/wp-content/plugins/

# 2. เปิดใช้งานปลั๊กอินใน WordPress

# 3. ตั้งค่า API ที่
jSearch → Settings → API
```

---

## วิธีใช้งาน

### Shortcode

แบบพื้นฐาน:
```php
[jsearch]
```

พร้อมพารามิเตอร์:
```php
[jsearch limit="20" show_popular="yes" show_thumbnail="yes"]
```

### REST API

#### ตัวอย่าง API Endpoints

**ค้นหา:**
```http
GET /wp-json/jsearch/v1/query?q=คำค้นหา&limit=10&offset=0
```

**สถิติ:**
```http
GET /wp-json/jsearch/v1/stats
```

---

## ตารางฐานข้อมูล

### โครงสร้างฐานข้อมูล (ตัวอย่าง)

#### `wp_jsearch_pdf_index`
เก็บเนื้อหา PDF และข้อมูลเมตา

| คอลัมน์ | คำอธิบาย |
|---------|----------|
| `file_id` (UNIQUE) | Google Drive ID หรือ `media_{attachment_id}` |
| `content` | เนื้อหาจาก OCR (มี full-text index) |
| `post_id` | เชื่อมโยงกับบทความ WordPress |
| `folder_id` | หมวดหมู่ |

#### `wp_jsearch_jobs`
จัดการงาน OCR

| สถานะ | คำอธิบาย |
|-------|----------|
| `processing` | กำลังทำงาน |
| `paused` | หยุดชั่วคราว |
| `completed` | เสร็จสิ้น (ลบอัตโนมัติหลัง 1 ชั่วโมง) |

#### `wp_jsearch_job_batches`
แบ่งงานเป็นชุด (5 ไฟล์ต่อชุด)

#### `wp_jsearch_folders`
หมวดหมู่โฟลเดอร์ Google Drive (Custom ชื่อเองได้)

---

## กระบวนการทำงาน (Job)

```
ผู้ใช้เริ่มงาน OCR
    ↓
สร้างงานและแบ่งเป็นชุด (5 ไฟล์ต่อชุด)
    ↓
ประมวลผลทีละชุด (JavaScript)
    ↓
สำหรับแต่ละไฟล์:
    - ตรวจสอบว่าประมวลผลแล้วหรือไม่
    - ถ้ายัง → OCR ผ่าน API → บันทึกลงฐานข้อมูล
    - ถ้าแล้ว → ข้าม
    ↓
อัปเดตความคืบหน้าแบบเรียลไทม์
    ↓
งานเสร็จสิ้น → ลบอัตโนมัติหลัง 1 ชั่วโมง
```

---

## ระบบ Rate Limiting

ปลั๊กอินมีระบบป้องกัน spam และ bot attacks ในตัว แบบง่าย:

### คุณสมบัติ
- **จำกัด 20 requests ต่อนาที** ต่อ IP address
- **ใช้ WordPress Transients API** (ไม่ต้องสร้างตารางใหม่)
- **รองรับ Cloudflare** - ดึง real IP ผ่าน proxy/CDN
- **ส่ง HTTP 429** (Too Many Requests) เมื่อเกินจำกัด
- **Auto cleanup** - ข้อมูลหมดอายุอัตโนมัติ

### การทำงาน
```
Request → เช็ค IP → นับจำนวน requests
    ↓
ถ้า < 20 requests/นาที → อนุญาต
ถ้า ≥ 20 requests/นาที → บล็อค (HTTP 429)
```

### การปรับแต่ง
แก้ไขใน `includes/class-rate-limiter.php`:
```php
private static $max_requests = 20;  // จำนวน requests สูงสุด
private static $time_window = 60;   // ช่วงเวลา (วินาที)
```

### การเก็บข้อมูลผู้ใช้
- เก็บใน **`wp_options`** table เป็น transients
- Key: `_transient_jsearch_rl_{md5_hash_of_ip}`
- Value: จำนวน requests
- หมดอายุ: 60 วินาที (auto-delete)


### ดูสถานะ Rate Limit
```php
$status = PDFS_Rate_Limiter::get_status();
// Returns: IP, current_requests, max_requests, remaining, time_window
```

### ล้าง Rate Limit (สำหรับ Admin/Testing)
```php
PDFS_Rate_Limiter::clear_rate_limit($ip);
```

---

## การแก้ปัญหาเบื้องต้นใน Plug in นี้

### REST API ขึ้น 404

1. ไปที่ Settings → Permalinks แล้วคลิก "Save Changes"
2. ตรวจสอบที่ jSearch → REST API Debug

### OCR ไม่ทำงาน

1. ตรวจสอบการตั้งค่า API ที่ jSearch → Settings → API
2. ทดสอบการเชื่อมต่อ
3. เปิด Debug Mode และตรวจสอบ log ที่ `/wp-content/uploads/jsearch/jsearch.log`

### ค้นหาแล้วไม่มีผลลัพธ์

1. ตรวจสอบข้อมูลที่ Dashboard
2. ตรวจสอบหน้าที่ไม่รวมในการค้นหาที่ Settings
3. เปิด "Include All Posts/Pages" ถ้าต้องการ
4. ล้างแคชการค้นหา

### งานหยุดชั่วคราว

1. กลับไปที่ jSearch → Manual OCR
2. ดูตาราง Active Jobs
3. คลิก "Continue" เพื่อทำงานต่อจากจุดเดิม

---

## ถาม-ตอบ

### ต้องมี OCR API หรือไม่?

ไม่จำเป็นสำหรับ PDF ดิจิทัล (มีข้อความ) - ใช้ Built-in Parser ได้เลย
แต่ต้องมีสำหรับ PDF ที่เป็นภาพสแกน (ต้องใช้ OCR API)

### การประมวลผลเป็นชุดจะทำ OCR ไฟล์ซ้ำไหม?

ไม่ ระบบตรวจสอบฐานข้อมูลก่อนประมวลผลแต่ละไฟล์และข้ามไฟล์ที่ทำแล้วอัตโนมัติ

### ถ้าออกจากหน้าตอนกำลัง OCR จะเป็นยังไง?

งานจะหยุดอัตโนมัติ กลับมาแล้วคลิก "Continue" เพื่อทำงานต่อจากจุดเดิม

### หยุดและทำงานต่อได้ไหม?

ได้ทุกโหมด เมื่อหยุด ระบบจะรอให้ชุดปัจจุบัน (5 ไฟล์) เสร็จก่อน

### งานที่เสร็จแล้วจะหายไปไหม?

งานที่มีสถานะ `completed` จะถูกลบอัตโนมัติหลัง 1 ชั่วโมง แต่ข้อมูล PDF ที่ทำ OCR แล้วยังคงอยู่ในฐานข้อมูลและไม่ได้รับผลกระทบ

---

## โครงสร้างไฟล์

```
jsearch/
├── admin/              # ส่วนจัดการ
├── assets/             # CSS และ JavaScript
├── includes/           # คลาสหลัก
├── public/             # ส่วนแสดงผลหน้าเว็บ
└── jsearch.php         # ไฟล์หลักของปลั๊กอิน
```

---

## Contributing

ยินดีรับ Pull Requests! นะครับ

```bash
# Fork และสร้าง feature branch
git checkout -b feature/awesome-feature

# Commit การเปลี่ยนแปลง
git commit -m 'Add awesome feature'

# Push ไปยัง branch
git push origin feature/awesome-feature

# เปิด Pull Request
```

---

**Developed by จิรัถ บุรพรัตน์**
[jirathsoft.com](https://jirathsoft.com) | [dev.jirath.com](https://dev.jirath.com)
