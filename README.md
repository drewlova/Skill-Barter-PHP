# Skill-Barter-PHP (SkillSwap)

A PHP + MySQL skill-bartering web app, built for XAMPP.

## Setup

1. Copy this project folder into your XAMPP `htdocs` directory.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, click **Import**, select
   `skillswap_schema.sql`, and click **Go**.
   This creates the `skillswap` database, all tables, and seed data.
4. Check `db_connect.php` — defaults (`localhost` / `root` / no password)
   work with a standard XAMPP install.
5. Visit `http://localhost/<project-folder-name>/index.php`.

