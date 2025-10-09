Teammate Setup (Windows) — Step by Step

1) Install the tools (one time)
	1.	Install Git for Windows (accept defaults).
	2.	Install GitHub Desktop and sign in with your GitHub account (accept Mike’s repo invite).
      Jackson use your gmail.
   	  Mike B. use your school email.
	4.	Install XAMPP for Windows. Open XAMPP Control Panel (you’ll start Apache/MySQL later).

⸻

2) Get the code (no terminal)
	1.	Open GitHub Desktop → File → Clone repository…
	2.	Choose theSpacePope91/SystemsProject.
	3.	Pick a folder, e.g. C:\Users\<YourName>\Documents\SystemsProject.
      (replace <YourName> with your name. The Path must match you documents folder path)
	5.	Click Clone.

⸻

3) Set up your local database (one time)
	1.	Open XAMPP Control Panel → click Start on MySQL.
	2.	Click Admin next to MySQL (opens phpMyAdmin at http://localhost/phpmyadmin).
	3.	In phpMyAdmin:
      • DROP DATABASE University
    	•	Left sidebar → New → create database University → Create.
    	•	Click the new University DB → Import → Choose file → select the University.sql Mike sent → Go.
    	•	Wait for the green success banner.

4) Run the site locally

   	_IMPORTANT_
   		You will have to change the database info at the top of .php files!
   		Anywhere you see one of the following:
   			$DB_HOST = "127.0.0.1";
			$DB_PORT = 3306;
			$DB_USER = "phpuser";
			$DB_PASS = "SystemsFall2025!";
			$DB_NAME = "University";
   		OR
   			$mysqli = new mysqli("127.0.0.1", "phpuser", "SystemsFall2025!", "University", 3306);
   		Change to your HOST, PORT, USER, PASSWORD. DB_NAME should remain University

  You have two easy choices. Pick ONE.

  Option A (simplest): copy into XAMPP web root
  	1.	Open C:\xampp\htdocs\
  	2.	Copy your SystemsProject folder into htdocs → now you have: C:\xampp\htdocs\SystemsProject\
  	3.	In XAMPP Control Panel, click Start on Apache (and MySQL if not already running).
  	4.	Open your browser: http://localhost/SystemsProject/login.html
  	    •	You’re now running the site locally, using your local database.
  
  Option B (no copying): point XAMPP to your Documents folder
  	1.	Delete C:\xampp\htdocs\SystemsProject if it exists.
  	2.	Open Command Prompt as Administrator.
  	3.	Run (replace <YourName> to Match your Documents Folder pathname):
    mklink /D C:\xampp\htdocs\SystemsProject C:\Users\<YourName>\Documents\SystemsProject
    
   4.	Start Apache in XAMPP (and MySQL).
   5.	Open: http://localhost/SystemsProject/login.html
   •	Now you edit in Documents and see changes immediately.
    
7) Daily workflow (repeat these steps whenever you work)
	1.	_Important_: Pull the latest code (avoid conflicts):
	    •	Open GitHub Desktop → Fetch origin → Pull origin (if available).
	2.	Start services: XAMPP → Start Apache + MySQL.
	3.	Edit code: HTML/PHP/CSS/JS inside your working folder (From Option A or B in Step 4).
	4.	Test locally: refresh http://localhost/SystemsProject/login.html
	    •	Use a hard refresh (Ctrl+Shift+R) if your browser caches.
	5.	Commit & Push (save work to GitHub):
		•	Make sure the site doesn't break before commiting/pushing changes
	    •	Open GitHub Desktop → verify changed files under Changes
	    •	Enter a short message, e.g., “Fix student dashboard layout”
	    •	Click Commit to main → Push origin.
	7.	Tell Mike C.: “Ready to deploy.”
	    •	Mike C. will publish the changes to the live site.
   	    •	Changes wont appear on the live site until Mike C. deloys

	The live site is http://3.223.132.79/login.html. This will update once Mike C. deploys the changes
