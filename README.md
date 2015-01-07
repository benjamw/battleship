Battleship v2.0

2012-08-07

Benjam Welker (http://iohelix.net)

INSTALLATION
----------------------------------
1. Copy the /includes/config.php.sample file and rename to /inludes/config.php

2. Edit the file to your specifications, taking note of the table prefixes.

3. Upload all files to your server

4. Run install.sql on your MySQL server (via phpMyAdmin or any other method)
This will create the tables and insert some basic settings
NOTE: make sure the table prefixes match the prefixes in your config file
Optionally delete the install.sql file from your server when you are done

5. Register your admin account

6. Get into your MySQL server, and edit the account you just created in the
"players" table, and set both `is_admin` and `is_approved` to 1

7. That's it, you're done


UPGRADING
----------------------------------
1. I apologize, but there is no upgrade script, you will have to manually compare
the given install.sql file with your own tables and make any adjustments needed

2. Copy the /includes/config.php.sample file and rename to /inludes/config.php

3. Edit the file to your specifications

4. Delete all your old files (including the config file)

5. Upload all new files to your server

6. That's it, you're done


SUPPORT
----------------------------------
If you find any bugs, have any feature requests or suggestions, or have
any questions, please use the Issues system on github
https://github.com/benjamw/battleship/issues


