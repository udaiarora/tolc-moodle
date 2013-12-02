<?php     require_once('../../config.php');
//include '/lib.php';
//print_r($GLOBALS['syndiscuss']);
global $USER, $CFG, $DB;
$dataobject = new stdClass();
$dataobject= unserialize($_POST["dis2"]);
$discussion = $DB->get_record('forum_discussions', array('id' => $dataobject->id));
//print_r($discussion);
$statement2="update mdl_forum_discussions set synergic_approved='1' where id='".$discussion->id."'";
echo $statement2;
$s=$DB->execute($statement2);
//$discussion = $DB->get_record('forum_discussions', array('id' => $dataobject->id));
//print_r($discussion);
header("Location:".$_POST["lasturl2"]);

?>