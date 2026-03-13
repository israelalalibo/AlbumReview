# Fix: "could not find driver" (SQLite)

Your project uses **SQLite** (`pdo_sqlite`). The error means PHP’s **PDO SQLite extension** is disabled.

---

## What to do

### 1. Open `php.ini` as Administrator

- Location (from your system): **`C:\Program Files\php-8.4.6\php.ini`**
- **Right‑click** the file → **Open with** → Notepad (or your editor).
- If Windows asks for admin rights, choose **Yes** (or open Notepad as Administrator first, then File → Open and go to that path).

### 2. Enable the SQLite extension

- Press **Ctrl+F** and search for: **`pdo_sqlite`**
- You should see a line like:
  ```ini
  ;extension=pdo_sqlite
  ```
- **Remove the semicolon** so it becomes:
  ```ini
  extension=pdo_sqlite
  ```
- **Save** the file (Ctrl+S). If it won’t save, close and open Notepad (or the editor) **as Administrator**, then edit and save again.

### 3. Restart the web server / PHP

- If you use **Symfony CLI** (`symfony serve`): stop it (Ctrl+C) and run `symfony serve` again.
- If you use **XAMPP / WAMP / Apache**: restart Apache from the control panel.
- If you use **PHP built-in server**: stop it and start it again.

### 4. Check that it worked

In a terminal (PowerShell or CMD) run:

```powershell
php -m
```

You should see **`pdo_sqlite`** in the list. Then open your app again in the browser; the "could not find driver" error should be gone.

---

## Optional: enable `sqlite3` as well

Some setups use the `sqlite3` extension. In the same `php.ini`, find:

```ini
;extension=sqlite3
```

and change it to:

```ini
extension=sqlite3
```

Then save and restart as above. For Doctrine with SQLite, **`pdo_sqlite`** is the one that fixes the "could not find driver" error.
