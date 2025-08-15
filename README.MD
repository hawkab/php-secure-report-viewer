# PHP Secure Report Viewer
EN | [RU](README_ru.MD)

## 📋 Purpose

This script is a simple PHP-based web application for **secure access to files, using PDF as an example**.  
Features include:
- Authorization via secret key
- Client geolocation check and country-based access restriction
- Logging and emailing all access attempts with client information (IP, city, provider, User-Agent)
- Displaying a list of files with optional month-based filtering
- Downloading one or multiple files (ZIP archive for multiple selections)
- Simple design and ability to upload an organization logo

---

## 🖥 Minimum System Requirements

- **PHP**: version 7.4 or higher (8.0+ recommended)
- **PHP extensions**:
  - `zip` (for packaging multiple files into an archive)
  - `openssl` (recommended for HTTPS support)
- **Web server**: Apache / Nginx
- Internet access to the `ip-api.com` service (for geolocation detection)
- Write permissions for the following directories:
  - `reports/`
  - `temp/`

---

## ⚙ Installation

1. Clone the repository into your web server's directory:
   ```bash
   git clone https://github.com/<your-username>/php-secure-report-viewer.git
   ```
2. Navigate to the project folder:
   ```bash
   cd php-secure-report-viewer
   ```
3. Create the required directories:
   ```bash
   mkdir reports temp
   chmod 755 reports temp
   ```
4. Make sure the web server has write permissions for these folders.

---

## 🔧 Configuration

1. Open the `index.php` file in any text editor.
2. Specify your secret keys in the `$SECRET_KEYS` array:
   ```php
   $SECRET_KEYS = [
       '123345' => [
           'code' => 'test',      // organization code (reports folder)
           'name' => 'Display name' // display name
       ],
   ];
   ```
3. Configure directories:
   ```php
   $BASE_DIR = __DIR__ . '/reports/';
   $TEMP_DIR = __DIR__ . '/temp/';
   ```
4. Set email addresses for notifications:
   ```php
   mail("receiver@example.com", ...);
   ```
5. For each client, create a separate subfolder in `reports/`:
   ```
   reports/
   └── test/
       ├── logo.png
       ├── work_report_2025-07-07_2025-07-11.pdf
       └── work_report_2025-07-14_2025-07-18.pdf
   ```

---

## 🖼 Screenshots

**Main page with file list:**

![File list](screenshots/list.png)

**Month-based filtering:**

![Filter](screenshots/filter.png)

**Selecting and downloading files:**

![Download](screenshots/download.png)

---

## 📜 License
This project is distributed under the MIT license. You are free to use, modify, and distribute the script while retaining copyright.

