<files wp/xmlrpc.php>
order allow,deny
deny from all
</files>

<Files app/uploads/*>
    Order Deny,Allow
    Deny from all
    Allow from 127.0.0.1
</Files>

# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /sttvapp/web/
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /sttvapp/web/index.php [L]
</IfModule>

# END WordPress