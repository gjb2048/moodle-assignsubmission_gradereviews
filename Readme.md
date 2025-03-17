Assign Submission Grade reviews
===============================

This plugin is a copy of the core submission comment plugin except that you have permissions that allow to restrict people who can:
* create and see the comments
* delete the comments

Common use case of this plugin: grade reviewers enter comments about the submission (content, grade...) for teachers to be aware of potential problems. 
For example the grade reviewer may have detected plagiarism or think that the grade is not optimal. 
The students should not be allowed to see these comments.

Installation
============
1. Ensure you have the version of Moodle as stated above in 'Required version of Moodle'.  This is essential as the
   plugin relies on underlying core code that is out of its control.
2. Login as an administrator and put Moodle in 'Maintenance Mode' so that there are no users using it bar you as the administrator.
3. Copy the extracted 'gradereviews' folder to the '/mod/assign/submission/' folder.
4. Go to 'Site administration' -> 'Notifications' and follow standard the 'plugin' update notification.
5. Put Moodle out of Maintenance Mode.
6. Optional: In the Moodle admin tweak the permissions.  The permission are listed in db/access.php.

Upgrading
=========
1. Ensure you have the version of Moodle as stated above in 'Required version of Moodle'.  This is essential as the
   plugin relies on underlying core code that is out of its control.
2. Login as an administrator and put Moodle in 'Maintenance Mode' so that there are no users using it bar you as the administrator.
3. Make a backup of your old 'gradereviews' folder in '/mod/assign/submission/' and then delete the folder.
4. Copy the replacement extracted 'gradereviews' folder to the '/mod/assign/submission/' folder.
5. Go to 'Site administration' -> 'Notifications' and follow standard the 'plugin' update notification.
6. Put Moodle out of Maintenance Mode.
7. Add the plugin on an assignment page.

Uninstallation
==============
1. Put Moodle in 'Maintenance Mode' so that there are no users using it bar you as the administrator.
2. Go to 'Site administration' -> 'Plugins' -> 'Activity modules' -> 'Assignment' -> 'Submission plugins' ->
   'Manage assignment submission plugins'.
3. Click on 'Uninstall' and follow the on screen instructions.
4. Put Moodle out of Maintenance Mode.

Required version of Moodle
==========================
This version works with:

 - Moodle 4.1 version 2022112800.00 (Build: 20221128) and above within the 4.1 branch.
 - Moodle 4.2 version 2023042400.00 (Build: 20230424) and above within the 4.2 branch.
 - Moodle 4.3 version 2023100900.00 (Build: 20231009) and above within the 4.3 branch.
 - Moodle 4.4 version 2024042200.00 (Build: 20240422) and above within the 4.4 branch.
 - Moodle 4.5 version 2024100700.00 (Build: 20241007) and above within the 4.5 branch.

Installing Moodle links
-----------------------
Please ensure that your hardware and software complies with 'Requirements' in 'Installing Moodle' on:
 - [Moodle 4.1](https://docs.moodle.org/401/en/Installing_Moodle)
 - [Moodle 4.2](https://docs.moodle.org/402/en/Installing_Moodle)
 - [Moodle 4.3](https://docs.moodle.org/403/en/Installing_Moodle)
 - [Moodle 4.4](https://docs.moodle.org/404/en/Installing_Moodle)
 - [Moodle 4.5](https://docs.moodle.org/405/en/Installing_Moodle)

License
=======

Church of England

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
