<?php     require_once('../../config.php');
//include '/lib.php';
//print_r($GLOBALS['syndiscuss']);
global $USER, $CFG, $DB;
$dataobject = new stdClass();
$dataobject= unserialize($_POST["dis"]);
$discussion = $DB->get_record('forum_discussions', array('id' => $dataobject->id));
$discussion->synergic=$_POST["syn"];
//print_r($discussion);
$statement="update mdl_forum_discussions set synergic='".$discussion->synergic."' where id='".$discussion->id."'";
$s=$DB->execute($statement);
//$discussion = $DB->get_record('forum_discussions', array('id' => $dataobject->id));
//print_r($discussion);
header("Location:".$_POST["lasturl"]);

?>