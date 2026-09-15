# Setup Instructions para sa XAMPP (Permanent Solution)

## Problem
Kapag nag-restart ng computer, hindi na ma-access ang system kasi kailangan i-start ulit ang PHP built-in server.

## Solution: Gamitin ang XAMPP Apache Server

### Step 1: Ilipat ang Project sa XAMPP (kung hindi pa)
1. I-copy ang `sales-and-procurement` folder sa:
   ```
   C:\xampp\htdocs\sales-and-procurement
   ```
2. O kung nandito na, okay na.

### Step 2: I-enable ang mod_rewrite sa Apache
1. Buksan ang `C:\xampp\apache\conf\httpd.conf` sa Notepad++ o text editor
2. Hanapin ang line na ito (Ctrl+F):
   ```
   #LoadModule rewrite_module modules/mod_rewrite.so
   ```
3. Tanggalin ang `#` para maging:
   ```
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
4. I-save ang file

### Step 3: I-restart ang Apache
1. Buksan ang XAMPP Control Panel
2. I-stop ang Apache (kung running)
3. I-start ulit ang Apache

### Step 4: I-test ang System
1. I-access ang:
   ```
   http://localhost/sales-and-procurement/
   ```
2. Dapat mag-redirect sa login page

### Step 5: I-setup ang Auto-start (Important!)
Para hindi mo na kailangan i-start manually ang Apache at MySQL:

1. Buksan ang XAMPP Control Panel
2. I-click ang "Config" button (sa tabi ng Apache)
3. Piliin ang "Autostart modules"
4. I-check ang "Apache" at "MySQL"
5. I-save

**O kung wala ang option na yan:**
1. Press `Win + R`
2. Type: `services.msc`
3. Hanapin ang "Apache2.4" (o "Apache")
4. Right-click → Properties
5. Set "Startup type" to "Automatic"
6. I-click ang "Start" button
7. Ganoon din sa "MySQL" service

### Step 6: I-access ang System
Pagkatapos ng restart, i-access:
```
http://localhost/sales-and-procurement/
```

Hindi mo na kailangan i-start manually ang server!

---

## Alternative: Gamitin ang PHP Built-in Server (Auto-start)

Kung gusto mong manatili sa PHP built-in server:

1. I-copy ang `start-server.bat` file sa:
   ```
   C:\Users\[YourUsername]\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup
   ```
2. Palitan ang `[YourUsername]` ng actual username mo
3. I-restart ang computer
4. Automatic na mag-start ang server

**Note:** Mas recommended ang XAMPP Apache kasi mas stable at production-ready.

---

## Troubleshooting

### Hindi gumagana ang routing?
- Check kung naka-enable ang `mod_rewrite` sa Apache
- Check kung naka-copy ang `.htaccess` file sa root ng project
- Check kung naka-setup ang `RewriteBase` sa `.htaccess` (dapat `/sales-and-procurement/`)

### Error 403 Forbidden?
- Check ang Apache `httpd.conf` file
- Hanapin ang `<Directory>` section para sa htdocs
- Siguraduhin na may `AllowOverride All`:
  ```apache
  <Directory "C:/xampp/htdocs">
      Options Indexes FollowSymLinks
      AllowOverride All
      Require all granted
  </Directory>
  ```

### Port 80 already in use?
- I-check kung may ibang application na gumagamit ng port 80
- O i-change ang Apache port sa `httpd.conf`:
  ```apache
  Listen 8080
  ```
- Tapos i-access: `http://localhost:8080/sales-and-procurement/`


