Link to the Github repo:
https://github.com/udaiarora/tolc-moodle

Which file was changed:
Moodle/Login/index_form.html

Exact part in the file which was changed:
<div class="form-label"><label for="username"><?php print_string("username") ?></label></div>

What was changed:
Echo text just before user name

Code after changing:
<div class="form-label"><label for="username"><?php echo "Enter your "; print_string("username") ?></label></div>

How we installed Moodle and how to run it:
Clone Moodle from Github. Make sure you have wamp isntalled. Move Moodle to the www folder and run it in browser by opening localhost. The first time you run it, an install script will run and automatically install it.