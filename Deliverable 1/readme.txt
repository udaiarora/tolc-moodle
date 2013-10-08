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