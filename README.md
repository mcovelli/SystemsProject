# Northport University Systems Project

A web-based university management system built with PHP, HTML, CSS, JavaScript, and MySQL/MariaDB.

TO DOWNLOAD AND EXPLORE THE WEBSITE LOCALLY FOLLOW THE STEPS BELOW:

## Prerequisites

Download and install **XAMPP** before getting started:
- [https://www.apachefriends.org/download.html](https://www.apachefriends.org/download.html)

---

## Setup Instructions

### 1. Clone the Repository

Open a terminal, navigate to your XAMPP `htdocs` folder, and clone the project:

**Mac:**
```bash
cd /Applications/XAMPP/htdocs
git clone https://github.com/theSpacePope91/SystemsProject
```

**Windows:**
```bash
cd C:/xampp/htdocs
git clone https://github.com/theSpacePope91/SystemsProject
```

---

### 2. Start XAMPP Services

- **Mac:** Open XAMPP and click **Manager-osx**, then start **Apache Web Server** and **MySQL Database**
- **Windows:** Open the XAMPP Control Panel and click **Start** next to **Apache** and **MySQL**

---

### 3. Set Up the Database

1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar to create a new database
3. Name it `University` and click **Create**
4. Select the `University` database, click the **Import** tab
5. Click **Choose File**, select the `University.sql` file, and click **Go**

---

### 4. Configure the Database Connection

1. Inside the project folder, find `config.example.php`
2. Make a copy of it and rename the copy to `config.php`
3. Open `config.php` and update the database credentials to match your local setup:

```php
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_USER = 'root';       // or 'phpuser' if you have it set up
$DB_PASS = '';           // XAMPP root has no password by default
$DB_NAME = 'University';
```

---

### 5. Access the Website

Open your browser and go to:

```
http://localhost/SystemsProject/login.html
```

---

## Notes

- `config.php` is listed in `.gitignore` and will not be pushed to GitHub — keep your credentials safe
- If you're using the `phpuser` account instead of `root`, make sure it has the correct permissions on the `University` database
- This project was developed and tested on **MariaDB 10.4** — if you encounter collation errors on import, open `University.sql` in a text editor and replace all instances of `utf8mb4_0900_ai_ci` with `utf8mb4_general_ci`

