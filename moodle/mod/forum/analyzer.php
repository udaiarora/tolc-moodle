<?php

function score($sentence)
{
	$scores_file = file_get_contents("../../emotion.json");
	$scores = json_decode($scores_file, true);

	//$sample = "this is so cool and awesome dude.";

	$arr = explode(" ", $sentence);

	$total_score = 0;
	foreach ($arr as $word) {
		$word_last=substr($word, 0, -1);
		$word_lasttwo=substr($word_last, 0, -1);
		$word_lastthree=substr($word_lastthree, 0, -1);
		if(!empty($scores[$word]))
		{
			$total_score += $scores[$word];
		}
		elseif(!empty($scores[$word_last]))
		{
			$total_score += $scores[$word_last];
		}
		elseif(!empty($scores[$word_lasttwo]))
		{
			$total_score += $scores[$word_lasttwo];
		}
		elseif(!empty($scores[$word_lastthree]))
		{
			$total_score += $scores[$word_lastthree];
		}
	}
	return $total_score;
}

?>