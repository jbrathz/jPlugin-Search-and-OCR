# jSearch – PDF Search Plugin for WordPress

[![License](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/wordpress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)](https://php.net/)

WordPress plugin สำหรับค้นหาเนื้อหาใน PDF และโพสต์ WordPress พร้อมรองรับ OCR จาก Google Drive

## คุณสมบัติหลัก

### 🔍 การค้นหา
- **Full-text Search** - ค้นหาจากเนื้อหา PDF, ชื่อไฟล์, หัวข้อโพสต์
- **2 โหมดค้นหา** - ค้นหาเฉพาะ PDF หรือรวมโพสต์/หน้าเพจทั้งหมด
- **Highlight คำค้น** - ไฮไลท์คำค้นในผลลัพธ์
- **กรองตามโฟลเดอร์** - จัดหมวดหมู่และกรองผลลัพธ์
- **Cache** - แคชผลการค้นหาเพิ่มประสิทธิภาพ

### 📄 OCR Processing (2 โหมด)

#### 1. Single File Mode
- OCR ไฟล์ทีละ 1 ไฟล์
- ได้ผลทันที
- เหมาะสำหรับทดสอบหรือ OCR ไฟล์เดียว

#### 2. Entire Folder Mode
- OCR ทุกไฟล์ในโฟลเดอร์
- ประมวลผล 5 ไฟล์/batch แบบ sequential
- **Smart File Detection** - ข้ามไฟล์ที่ทำแล้วอัตโนมัติ
- Realtime progress bar
- Pause/Resume ได้ทุกเมื่อ
- Admin ต้องอยู่ที่หน้านี้เพื่อให้ทำงานต่อ

### 🎛️ Admin Features
- Dashboard พร้อมสถิติ
- เครื่องมือ OCR 2 โหมด
- Active Jobs Table - ดู job ที่กำลังทำหรือหยุดชั่วคราว
- จัดการโฟลเดอร์
- REST API Debug Tool
- Import/Export Settings

## การทำงานของระบบ

### Smart File Detection
ทั้ง 2 โหมดใช้ระบบตรวจสอบไฟล์ซ้ำด้วย PHP:

```php
// เช็คก่อน OCR ทุกครั้ง
if (is_file_processed($file_id)) {
    skip(); // ข้ามไฟล์ที่ทำแล้ว
} else {
    ocr_file($file_id); // OCR เฉพาะไฟล์ใหม่
}
```

**ประโยชน์:**
- ประหยัดเวลาและค่า API
- ไม่ต้องกังวลเรื่อง duplicate
- Entire Folder สามารถรันซ้ำได้ จะ OCR เฉพาะไฟล์ใหม่

### Job System (Realtime Processing)
Entire Folder Mode ใช้ระบบ Job-based processing:

1. สร้าง Job → แบ่งเป็น batches (5 ไฟล์/batch) → สถานะ `processing`
2. JavaScript ประมวลผล batch ทีละอัน
3. แสดง progress แบบ realtime
4. **Smart Pause** - เมื่อคลิก Pause จะรอให้ batch ปัจจุบันเสร็จก่อน → สถานะ `paused`
5. Resume ทำต่อจากจุดเดิม → กลับสู่สถานะ `processing`
6. Job เสร็จสมบูรณ์ → สถานะ `completed` → ลบอัตโนมัติหลัง 1 ชั่วโมง
7. ถ้าออกจากหน้าระหว่าง OCR = Pause อัตโนมัติ (กลับมากด Continue ได้)

**Active Jobs Table:**
- แสดง job ที่มีสถานะ `processing`, `paused`, หรือ `completed` (ยังไม่ถึง 1 ชั่วโมง)
- ปุ่ม Continue - ทำต่อจาก job ที่ paused (สลับไปแท็บ Entire Folder + แสดงชื่อโฟลเดอร์)
- ปุ่ม Cancel - ลบ job ที่กำลังทำหรือหยุดชั่วคราว
- ปุ่ม Delete - ลบ job ที่เสร็จแล้ว (ไม่กระทบข้อมูล PDF)

## ความต้องการของระบบ

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- Python OCR API Service (สำหรับแปลง PDF)

## การติดตั้ง

1. อัปโหลดโฟลเดอร์ `jsearch` ไปที่ `/wp-content/plugins/`
2. เปิดใช้งานปลั๊กอินใน WordPress
3. ไปที่ **jSearch → Settings** เพื่อตั้งค่า API

## วิธีใช้งาน

### Shortcode
```
[jsearch]
```

พารามิเตอร์เพิ่มเติม:
```
[jsearch placeholder="ค้นหา..." button_text="Search" results_per_page="20"]
```

### REST API

**ค้นหา:**
```
GET /wp-json/jsearch/v1/query?q=คำค้น&limit=10&offset=0
```

**สถิติ:**
```
GET /wp-json/jsearch/v1/stats
```

**OCR Single File (Admin only):**
```
POST /wp-json/jsearch/v1/ocr
{
  "type": "file",
  "file_id": "Google_Drive_File_ID"
}
```

**Realtime Job Processing (Admin only):**
```
POST /wp-json/jsearch/v1/ocr-job/start
{
  "folder_id": "Google_Drive_Folder_ID"
}

GET /wp-json/jsearch/v1/ocr-job/{job_id}/status-detailed

POST /wp-json/jsearch/v1/ocr-job/process-batch
{
  "batch_id": 123
}

POST /wp-json/jsearch/v1/ocr-job/{job_id}/pause
POST /wp-json/jsearch/v1/ocr-job/{job_id}/resume
DELETE /wp-json/jsearch/v1/ocr-job/{job_id}?force=true
```

## ตารางฐานข้อมูล

### `wp_jsearch_pdf_index`
เก็บเนื้อหา PDF ที่ทำดัชนีและข้อมูลเมตา (full-text indexed)

**Columns:**
- `file_id` (UNIQUE) - Google Drive File ID
- `pdf_title`, `pdf_url` - ข้อมูล PDF
- `content` - เนื้อหาที่ OCR ได้ (FULLTEXT INDEX)
- `ocr_method` - วิธี OCR (api/direct)
- `char_count` - จำนวนตัวอักษร
- `folder_id`, `folder_name` - หมวดหมู่
- `post_id`, `post_title`, `post_url` - เชื่อมโยง WordPress post

### `wp_jsearch_folders`
จัดการหมวดหมู่โฟลเดอร์ Google Drive

### `wp_jsearch_jobs`
เก็บข้อมูล OCR jobs

**Statuses (3 statuses only):**
- `processing` - กำลังทำงาน
- `paused` - หยุดชั่วคราว
- `completed` - เสร็จสิ้น

**Auto-cleanup:**
- Job ที่มีสถานะ `completed` จะถูกลบอัตโนมัติหลังจาก 1 ชั่วโมง
- ระบบทำ cleanup ทุก 10 นาที (ใช้ WordPress transient)
- ไม่กระทบกับข้อมูล PDF ที่ OCR เสร็จแล้ว (เก็บในตาราง `pdf_index`)

### `wp_jsearch_job_batches`
แบ่ง job เป็น batches (5 ไฟล์/batch)

## Architecture

### Realtime Processing Flow
```
User clicks "Run OCR on Folder"
    ↓
Create Job (status: processing) + Batches (5 files each)
    ↓
JavaScript processes batches sequentially
    ↓
For each file in batch:
    - Check is_file_processed() in PHP
    - If already processed → Skip
    - If new → OCR via Python API → Save to DB
    ↓
Update progress bar in realtime
    ↓
User can Pause (waits for current batch) or Resume anytime
    ↓
Job completes → status: completed → auto-deleted after 1 hour
```

### Job Lifecycle
```
[Processing] ←→ [Paused]
     ↓
[Completed] → Auto-cleanup after 1 hour
```

**Note:** Pause operation waits for current batch (5 files) to complete before actually pausing. This ensures data integrity and prevents incomplete batches.

### Uploads Directory
`/wp-content/uploads/jsearch/` เก็บ:

- **`jsearch.log`** - Plugin activity logs
  - เก็บ log การทำงานของ plugin
  - ใช้โดย `PDFS_Logger` class
  - Protected by `.htaccess` (ไม่สามารถเข้าถึงจาก web ได้)

- **`.htaccess`** - Security protection
  ```apache
  Options -Indexes
  <Files *.log>
  Order allow,deny
  Deny from all
  </Files>
  ```

- **`index.php`** - Prevent directory browsing

### ความปลอดภัย
- WordPress Nonces ป้องกัน CSRF
- Permission checks สำหรับ Admin endpoints
- Input sanitization และ output escaping
- Prepared statements ป้องกัน SQL Injection
- Rate limiting (ตั้งค่าได้)
- Log files protected from web access

### ประสิทธิภาพ
- WordPress Transients caching
- Full-text search ด้วย MySQL MATCH...AGAINST
- Indexed columns สำหรับ faster queries
- Batch processing (5 files/batch) ป้องกัน timeout
- Smart file skipping ลดการ OCR ซ้ำ
- Pagination support

## การแก้ไขปัญหา

### REST API ขึ้น 404
1. ไปที่ **Settings → Permalinks** และคลิก "Save Changes"
2. ตรวจสอบที่ **jSearch → REST API Debug**
3. ใช้รูปแบบ URL: `/?rest_route=/jsearch/v1/...`

### OCR ไม่ทำงาน
1. ตรวจสอบ API settings ใน **jSearch → Settings → API**
2. Test connection กับ OCR service
3. เปิด Debug Mode ดู logs ที่ `/wp-content/uploads/jsearch/jsearch.log`
4. ทดสอบที่ **jSearch → Manual OCR → Single File**

### การค้นหาไม่แสดงผล
1. ตรวจสอบว่ามีข้อมูลใน **jSearch → Dashboard**
2. ตรวจสอบ excluded pages ใน Settings
3. เปิด "Include All Posts/Pages" หากต้องการค้นหาโพสต์
4. Clear cache ที่ Dashboard

### Job หยุดชั่วคราว
1. กลับมาที่ **jSearch → Manual OCR**
2. ดูตาราง Active Jobs
3. คลิก "Continue" เพื่อทำต่อจากจุดเดิม (จะสลับไปแท็บ Entire Folder + แสดงชื่อโฟลเดอร์)
4. หรือคลิก "Cancel" เพื่อลบ job

### Job เสร็จแล้วหายไป
- Job ที่มีสถานะ `completed` จะถูกลบอัตโนมัติหลัง 1 ชั่วโมง
- นี่เป็นการทำ cleanup เพื่อลดข้อมูลที่ไม่จำเป็นในฐานข้อมูล
- ข้อมูล PDF ที่ OCR เสร็จแล้วยังคงอยู่ในตาราง `pdf_index` (ไม่ถูกลบ)
- หากต้องการเก็บประวัติ job ให้คลิก "Delete" ก่อนจะถึง 1 ชั่วโมง

## คำถามที่พบบ่อย

**Q: ต้องมี OCR service หรือไม่?**
A: ไม่จำเป็น แต่จะต้องมีหาก PDF เป็นภาพสแกน

**Q: Entire Folder จะ OCR ไฟล์ซ้ำหรือไม่?**
A: ไม่ ระบบเช็คไฟล์ใน database ก่อน OCR ทุกครั้ง ไฟล์ที่ทำแล้วจะถูกข้ามอัตโนมัติ

**Q: ถ้าออกจากหน้าตอนกำลัง OCR จะเป็นอย่างไร?**
A: ระบบหยุดชั่วคราว กลับมาใหม่คลิก "Continue" ได้ ระบบจะทำต่อจากจุดเดิม ไม่ซ้ำไฟล์ที่ทำแล้ว

**Q: สามารถ Pause/Resume ได้หรือไม่?**
A: ได้ Entire Folder Mode รองรับ Pause/Resume ทุกเมื่อ โดยเมื่อคลิก Pause ระบบจะรอให้ batch ปัจจุบัน (5 ไฟล์) เสร็จก่อนหยุดจริง เพื่อความสมบูรณ์ของข้อมูล

**Q: Job ที่เสร็จแล้วจะหายไปเองหรือไม่?**
A: ใช่ Job ที่มีสถานะ `completed` จะถูกลบอัตโนมัติหลัง 1 ชั่วโมง แต่ข้อมูล PDF ที่ OCR เสร็จแล้วยังคงอยู่ครบถ้วนในฐานข้อมูล ไม่มีผลกระทบต่อการค้นหา

**Q: จะเกิดอะไรขึ้นเมื่อ Deactivate?**
A: เก็บข้อมูลและ settings ไว้ทั้งหมด เฉพาะ uninstall เท่านั้นจะลบ

**Q: ทำไมไม่มี Incremental Mode?**
A: Entire Folder Mode ทำหน้าที่เดียวกัน เพราะมีระบบ Smart File Detection ที่ข้ามไฟล์ที่ทำแล้วอัตโนมัติ ไม่จำเป็นต้องมี mode แยกอีก

## โครงสร้างไฟล์

```
jsearch/
├── admin/
│   ├── class-admin.php           # Admin page controller
│   ├── dashboard.php              # Dashboard page
│   ├── manual-ocr.php             # Manual OCR page (2 modes)
│   ├── manage-folders.php         # Folder management
│   ├── settings.php               # Settings page
│   ├── debug.php                  # REST API debug
│   └── js/
│       └── folder-ocr.js          # Realtime processing
├── assets/
│   ├── css/admin.css              # Admin styles
│   └── js/
│       ├── admin.js               # Admin scripts
│       └── search.js              # Search functionality
├── includes/
│   ├── class-activator.php        # Plugin activation
│   ├── class-deactivator.php      # Plugin deactivation
│   ├── class-database.php         # Database operations
│   ├── class-folders.php          # Folder management
│   ├── class-helper.php           # Helper functions
│   ├── class-hooks.php            # Auto OCR hooks
│   ├── class-logger.php           # Logging system
│   ├── class-ocr-service.php      # OCR API integration
│   ├── class-queue-service.php    # Job & Batch management
│   ├── class-rest-api.php         # REST API endpoints
│   └── class-settings.php         # Settings management
├── public/
│   ├── class-public.php           # Public functionality
│   └── shortcode.php              # [jsearch] shortcode
└── jsearch.php                     # Main plugin file
```

## การมีส่วนร่วม

Pull Requests ยินดีต้อนรับ!

1. Fork repository
2. Create feature branch (`git checkout -b feature/awesome-feature`)
3. Commit changes (`git commit -m 'Add awesome feature'`)
4. Push to branch (`git push origin feature/awesome-feature`)
5. Open Pull Request

## Changelog

### Version 1.0.0
- Full-text search สำหรับ PDF และ WordPress content
- Google Drive OCR integration (2 modes: Single File, Entire Folder)
- **Smart File Detection** - ข้ามไฟล์ที่ทำแล้วอัตโนมัติ (PHP-based)
- **Realtime batch processing** (5 ไฟล์/batch)
- **Pause/Resume functionality**
- **Active Jobs Table** - ดู job ที่กำลังทำ
- REST API (10+ endpoints)
- Dashboard และ admin tools
- Logging system (`/wp-content/uploads/jsearch/`)
- Import/Export settings
- Multi-language support (TH/EN)

**Architecture Highlights:**
- ✅ JavaScript-driven realtime processing
- ✅ PHP-based duplicate detection (ไม่ใช้ Python API incremental endpoint)
- ✅ Batch processing (5 files per batch)
- ✅ **3-status job system** (processing, paused, completed)
- ✅ **Smart Pause** - รอ batch เสร็จก่อนหยุดจริง
- ✅ **Auto-cleanup** - ลบ completed jobs หลัง 1 ชั่วโมง
- ✅ **Smart snippet** - แสดง snippet รอบๆ คำค้นที่เจอ (±75 chars)
- ✅ **Filter PDFs without posts** - ไม่แสดง PDF ที่ไม่มี post_id
- ✅ Smart file skipping
- ✅ Secure log storage with .htaccess protection

## ใบอนุญาต

GPL v2 หรือใหม่กว่า

## ผู้พัฒนา

**JIRATH BURAPARATH**

## ช่องทางติดต่อ

- [GitHub Issues](../../issues) - รายงานบั๊ก
- [GitHub Discussions](../../discussions) - ถามคำถาม

---

**Made with ❤️ for Thai Pediatrics Community**
