Bootstrap admin (one-time):

1) Start Apache/MySQL in XAMPP.
2) Import api/init.sql at server level in phpMyAdmin (Server > Import) if not already applied.
3) Create a manager user via the secure bootstrap endpoint:
    Option A (Windows PowerShell):
       Invoke-RestMethod -Method Post -Uri "http://localhost/Schools-sites/api/bootstrap.php" -Headers @{ 'X-Bootstrap-Secret'='change-this-once' } -ContentType 'application/json' -Body '{"email":"admin@example.com","password":"Admin123!","first_name":"مدير","last_name":"النظام","school_id":1}'
    Option B (curl.exe):
       curl -X POST -H "X-Bootstrap-Secret: change-this-once" -H "Content-Type: application/json" -d '{"email":"admin@example.com","password":"Admin123!","first_name":"مدير","last_name":"النظام","school_id":1}' http://localhost/Schools-sites/api/bootstrap.php
    - The response includes ok=true and the created credentials (if not already bootstrapped).
   - IMPORTANT: After success, change BOOTSTRAP_SECRET (set env) or delete api/bootstrap.php.

4) Sign in:
   - Use your existing login flow (school-login.html) or integrate API login in your preferred page.
   - The admin panel is at: manager/school-admin/index.html

Notes:
- The manager admin pages call /api/admin.php and require the Authorization: Bearer token stored by the manager login.
- School-specific legacy login (manager/school-login.html) still works and guards with ?sid=.
- To disable bootstrap permanently, delete api/bootstrap.php.
