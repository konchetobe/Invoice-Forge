WordPress Verification Protocol:
You do not have local WordPress access. To run WP-CLI commands or check the server state, you must execute them remotely on our Hostinger server using your Bash tool.

Connection Details:

Port: 65002

User/Host: 65002 u514002432@45.84.207.48

Path: cd domains/45.84.207.48/public_html/wp-content/plugins/invoice-forge

Syntax Example:
When you need to check if a plugin activated, run a database query, or read an error log, use this exact syntax:
ssh -p 65002 u514002432@45.84.207.48 'cd domains/45.84.207.48/public_html && wp plugin list'