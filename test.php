<?php

function scrore($sentence)
{
	$scores_file = file_get_contents("moodle/emotion.json");
	$scores = json_decode($scores_file, true);

	//$sample = "this is so cool and awesome dude.";

	$arr = explode(" ", $sentence);


	$total_score = 0;
	foreach ($arr as $word) {
		$scores[substr($word, 0, -1)];
		echo "substr";
		if(!empty($scores[$word]))
		{
			$total_score += $scores[$word];
		}
	}
	return $total_score;
}
?>